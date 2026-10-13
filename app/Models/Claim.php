<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\HttpException;
use Cora\Core\Model;

/**
 * One claim transaction submitted by an outlet.
 *
 * Status lifecycle (integers, never words):
 *   Draft (1) -> Submitted (2) -> Pending Review (3) -> Approved (4) | Rejected (5)
 *   Pending Review -> Amendment Required (7) -> (outlet corrects) -> Submitted (2)
 *   Draft / Submitted / Pending Review / Amendment Required -> Cancelled / Void (6)
 *   Approved -> Cancelled / Void (6) by an admin (transaction reversed)
 * Submitted, Pending Review and Amendment Required RESERVE claim_amount from
 * the employee's available balance; Approved CONSUMES it; Rejected and
 * Cancelled release it. All transitions live in Services\ClaimWorkflow.
 * Table: cora_claims.
 */
final class Claim extends Model
{
    public const DRAFT = 1;
    public const SUBMITTED = 2;
    public const PENDING_REVIEW = 3;
    public const APPROVED = 4;
    public const REJECTED = 5;
    public const CANCELLED = 6;
    public const AMENDMENT_REQUIRED = 7;

    public const STATUSES = [
        self::DRAFT              => 'Draft',
        self::SUBMITTED          => 'Submitted',
        self::PENDING_REVIEW     => 'Pending Review',
        self::APPROVED           => 'Approved',
        self::REJECTED           => 'Rejected',
        self::CANCELLED          => 'Cancelled / Void',
        self::AMENDMENT_REQUIRED => 'Amendment Required',
    ];

    /** Statuses that hold (reserve) entitlement until an admin decides. */
    public const RESERVING = [self::SUBMITTED, self::PENDING_REVIEW, self::AMENDMENT_REQUIRED];

    /** Statuses waiting in the admin claim queue. */
    public const IN_QUEUE = [self::SUBMITTED, self::PENDING_REVIEW];

    public const PAY_CORPORATE = 1;
    public const PAY_TPA = 2;
    public const PAY_SELF = 3;
    public const PAY_MIXED = 4;

    public const PAYMENT_METHODS = [
        self::PAY_CORPORATE => 'Corporate Claim',
        self::PAY_TPA       => 'TPA',
        self::PAY_SELF      => 'Self-Pay',
        self::PAY_MIXED     => 'Mixed Payment',
    ];

    /** flags keys -> label shown to the reviewer. */
    public const FLAGS = [
        'outside_period'   => 'Visit date is outside the entitlement period',
        'exceeds_limit'    => 'Claim amount exceeds the available balance',
        'duplicate_so'     => 'Sales Order number already used (authorised duplicate)',
        'not_active'       => 'Employee was not ACTIVE at submission',
    ];

    protected const TABLE = 'cora_claims';

    protected const FILLABLE = [
        'claim_no', 'employee_id', 'company_id', 'outlet_id', 'outlet_name', 'employee_name', 'employee_ic',
        'employee_no', 'company_name', 'visit_date', 'so_no', 'so_norm', 'total_amount', 'claim_amount',
        'self_pay_amount', 'payment_method', 'claim_type', 'remark', 'status', 'flags', 'limit_snapshot',
        'available_snapshot', 'period_start', 'period_end', 'duplicate_authorised', 'resubmit_count',
        'created_by', 'created_by_name', 'submitted_by', 'submitted_by_name', 'submitted_at',
        'reviewed_by', 'reviewed_by_name', 'reviewed_at', 'review_remark',
        'cancelled_by', 'cancelled_by_name', 'cancelled_at', 'cancel_reason',
    ];

    protected const CASTS = [
        'employee_id'          => 'int',
        'company_id'           => 'int',
        'outlet_id'            => 'int',
        'visit_date'           => 'date',
        'total_amount'         => 'decimal',
        'claim_amount'         => 'decimal',
        'self_pay_amount'      => 'decimal',
        'payment_method'       => 'int',
        'status'               => 'int',
        'flags'                => 'json',
        'limit_snapshot'       => 'decimal',
        'available_snapshot'   => 'decimal',
        'period_start'         => 'date',
        'period_end'           => 'date',
        'duplicate_authorised' => 'bool',
        'resubmit_count'       => 'int',
        'submitted_at'         => 'datetime',
        'reviewed_at'          => 'datetime',
        'cancelled_at'         => 'datetime',
    ];

    protected const ORDER = '`id` DESC';

    /** Sales Order number -> comparison key: upper case, no spaces or dashes. */
    public static function normalizeSo(string $so): string
    {
        return strtoupper(preg_replace('/[\s\-\/]+/', '', $so) ?? '');
    }

    /** Human claim number, e.g. CR2610-000042 (year-month of creation + id). */
    public static function makeNumber(int $id): string
    {
        return sprintf('CR%s-%06d', date('ym'), $id);
    }

    /**
     * Another live claim (not rejected / cancelled) already using this SO.
     *
     * @return array<string, mixed>|null
     */
    public static function findDuplicateSo(string $soNorm, ?int $ignoreId = null): ?array
    {
        $sql = '`so_norm` = ? AND `status` NOT IN (' . self::REJECTED . ', ' . self::CANCELLED . ', ' . self::DRAFT . ')';
        $params = [$soNorm];
        if ($ignoreId !== null) {
            $sql .= ' AND `id` != ?';
            $params[] = $ignoreId;
        }

        return self::where($sql, $params, null, 1)[0] ?? null;
    }

    /**
     * The claim, or 404 when the caller may not see it (outlet roles see only
     * their own outlets' claims).
     *
     * @param list<int>|null $outletIds null = no outlet restriction (admin)
     * @return array<string, mixed>
     */
    public static function findVisible(int $id, ?array $outletIds): array
    {
        $claim = self::find($id);
        if ($claim === null || ($outletIds !== null && !in_array((int) $claim['outlet_id'], $outletIds, true))) {
            throw new HttpException('Claim not found.', 404);
        }

        return $claim;
    }

    /**
     * Shapes a claim for the pages: labels for status / payment method and
     * flag descriptions.
     *
     * @param array<string, mixed> $row presented row
     * @return array<string, mixed>
     */
    public static function decorate(array $row): array
    {
        $row['status_label'] = self::STATUSES[(int) $row['status']] ?? (string) $row['status'];
        $row['payment_method_label'] = self::PAYMENT_METHODS[(int) $row['payment_method']] ?? (string) $row['payment_method'];
        $flags = is_array($row['flags'] ?? null) ? $row['flags'] : [];
        $row['flag_labels'] = array_values(array_map(
            static fn (string $k): string => self::FLAGS[$k] ?? $k,
            array_keys(array_filter($flags))
        ));

        return $row;
    }
}
