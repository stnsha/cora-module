<?php

declare(strict_types=1);

namespace Cora\Services;

use Cora\Core\Database;
use Cora\Models\Claim;
use Cora\Models\Company;
use Cora\Models\Employee;
use Cora\Models\Setting;
use DateTimeImmutable;

/**
 * Entitlement engine - the single place that answers "is this employee
 * eligible, what are they entitled to, how much is left".
 *
 *   Available Balance = Entitlement Limit - Approved Claims - Pending/Reserved Claims
 *
 * Pending (Submitted, Pending Review, Amendment Required) claims reserve
 * their amount, so two outlets cannot both spend the same balance while
 * claims wait for an admin. A rejected or cancelled claim drops out of both
 * sums, which releases the reservation automatically.
 *
 * Only claims whose visit date lies inside the employee's current
 * entitlement period count; claims outside it are flagged at submission.
 *
 * Limit hierarchy: Individual employee override (active and not past its
 * end date) -> employee limit -> company default -> Default Alpro rule.
 */
final class Entitlement
{
    public const ACTIVE = 'ACTIVE';
    public const INACTIVE = 'INACTIVE';
    public const SUSPENDED = 'SUSPENDED';
    public const NOT_STARTED = 'NOT STARTED';
    public const EXPIRED = 'EXPIRED';
    public const LIMIT_EXHAUSTED = 'LIMIT EXHAUSTED';

    /** Status -> pill class on the pages (plus cora-pill-caps: the labels are upper case). */
    public const PILLS = [
        self::ACTIVE          => 'cora-pill-success',
        self::INACTIVE        => 'cora-pill-secondary',
        self::SUSPENDED       => 'cora-pill-danger',
        self::NOT_STARTED     => 'cora-pill-warning',
        self::EXPIRED         => 'cora-pill-dark',
        self::LIMIT_EXHAUSTED => 'cora-pill-danger',
    ];

    /**
     * @param array<string, mixed> $employee presented employee row
     * @param array<string, mixed>|null $company presented company row (looked up when null)
     * @param int|null $excludeClaimId leave this claim out of the sums (a claim being re-submitted)
     * @param array{0: float, 1: float}|null $utilisation precomputed [approved, pending] (utilisationMany)
     * @return array<string, mixed>
     */
    public static function summary(array $employee, ?array $company = null, ?int $excludeClaimId = null, ?string $today = null, ?array $utilisation = null): array
    {
        $company ??= Company::find((int) $employee['company_id']);
        $todayDate = $today ?? date('Y-m-d');
        $start = self::ymd($employee['start_date'] ?? null);
        $end = self::ymd($employee['end_date'] ?? null);

        [$limit, $limitSource] = self::limit($employee, $company, $todayDate);
        [$approved, $pending] = $utilisation ?? self::utilisation((int) $employee['id'], $start, $end, $excludeClaimId);
        $available = round(max(0.0, $limit - $approved - $pending), 2);

        $status = match (true) {
            (int) ($employee['restriction_type'] ?? 0) === Employee::RESTRICTION_SUSPENDED => self::SUSPENDED,
            (int) ($employee['status'] ?? 0) !== Employee::STATUS_ACTIVE,
            $company === null,
            (int) ($company['status'] ?? 0) !== Company::STATUS_ACTIVE => self::INACTIVE,
            $start !== null && $todayDate < $start => self::NOT_STARTED,
            $end !== null && $todayDate > $end => self::EXPIRED,
            $available <= 0.0 => self::LIMIT_EXHAUSTED,
            default => self::ACTIVE,
        };

        return [
            'status'          => $status,
            'status_pill'     => self::PILLS[$status] . ' cora-pill-caps',
            'eligible'        => $status === self::ACTIVE,
            'limit'           => $limit,
            'limit_source'    => $limitSource,
            'approved'        => $approved,
            'pending'         => $pending,
            'available'       => $available,
            'period_start'    => $employee['start_date'] ?? null,
            'period_end'      => $employee['end_date'] ?? null,
            'limit_mode'      => (int) ($company['limit_mode'] ?? Setting::defaultLimitMode()),
        ];
    }

    /**
     * Effective limit and where it came from.
     *
     * @param array<string, mixed> $employee
     * @param array<string, mixed>|null $company
     * @return array{0: float, 1: string}
     */
    public static function limit(array $employee, ?array $company, string $today): array
    {
        if ((int) ($employee['restriction_type'] ?? 0) === Employee::RESTRICTION_LIMIT && $employee['override_limit'] !== null) {
            $until = self::ymd($employee['override_until'] ?? null);
            if ($until === null || $today <= $until) {
                return [round((float) $employee['override_limit'], 2), 'employee_override'];
            }
        }
        if (isset($employee['entitlement_limit']) && (float) $employee['entitlement_limit'] > 0) {
            return [round((float) $employee['entitlement_limit'], 2), 'employee'];
        }
        if ($company !== null && $company['default_limit'] !== null) {
            return [round((float) $company['default_limit'], 2), 'company'];
        }

        return [Setting::defaultLimit(), 'default'];
    }

    /**
     * Approved and reserved totals inside the period.
     *
     * @return array{0: float, 1: float}
     */
    public static function utilisation(int $employeeId, ?string $start, ?string $end, ?int $excludeClaimId = null): array
    {
        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN `status` = ? THEN `claim_amount` END), 0) AS approved,
                    COALESCE(SUM(CASE WHEN `status` IN (' . implode(',', Claim::RESERVING) . ') THEN `claim_amount` END), 0) AS pending
                  FROM `cora_claims`
                 WHERE `employee_id` = ? AND `deleted_at` IS NULL';
        $params = [Claim::APPROVED, $employeeId];
        if ($start !== null) {
            $sql .= ' AND `visit_date` >= ?';
            $params[] = $start;
        }
        if ($end !== null) {
            $sql .= ' AND `visit_date` <= ?';
            $params[] = $end;
        }
        if ($excludeClaimId !== null) {
            $sql .= ' AND `id` != ?';
            $params[] = $excludeClaimId;
        }
        $row = Database::get()->first($sql, $params) ?? ['approved' => 0, 'pending' => 0];

        return [round((float) $row['approved'], 2), round((float) $row['pending'], 2)];
    }

    /**
     * [approved, pending] for many employees in one query (listings), each
     * inside that employee's own current period.
     *
     * @param list<int> $employeeIds
     * @return array<int, array{0: float, 1: float}> employee id => totals (missing = no claims)
     */
    public static function utilisationMany(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $rows = Database::get()->select(
            'SELECT c.`employee_id`,
                    COALESCE(SUM(CASE WHEN c.`status` = ? THEN c.`claim_amount` END), 0) AS approved,
                    COALESCE(SUM(CASE WHEN c.`status` IN (' . implode(',', Claim::RESERVING) . ') THEN c.`claim_amount` END), 0) AS pending
               FROM `cora_claims` c
               JOIN `cora_employees` e ON e.`id` = c.`employee_id`
              WHERE c.`deleted_at` IS NULL
                AND c.`employee_id` IN (' . implode(',', array_fill(0, count($employeeIds), '?')) . ')
                AND c.`visit_date` BETWEEN e.`start_date` AND e.`end_date`
              GROUP BY c.`employee_id`',
            [Claim::APPROVED, ...$employeeIds]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['employee_id']] = [round((float) $r['approved'], 2), round((float) $r['pending'], 2)];
        }

        return $out;
    }

    /** Whether a visit date (any accepted format) lies inside the employee's period. */
    public static function inPeriod(array $employee, string $visitDate): bool
    {
        $visit = self::ymd($visitDate);
        $start = self::ymd($employee['start_date'] ?? null);
        $end = self::ymd($employee['end_date'] ?? null);

        return $visit !== null && ($start === null || $visit >= $start) && ($end === null || $visit <= $end);
    }

    /** Any accepted date format (d-m-Y from the model, Y-m-d from inputs) -> Y-m-d. */
    public static function ymd(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $d = DateTimeImmutable::createFromFormat('!' . $format, trim((string) $value));
            if ($d !== false && $d->format($format) === trim((string) $value)) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
