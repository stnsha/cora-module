<?php

declare(strict_types=1);

namespace Cora\Services;

use Cora\Core\Database;
use Cora\Core\ValidationException;
use Cora\Models\Claim;

/**
 * Company-level utilisation for one month (by visit date):
 * employees enrolled / utilised, claims, approved / pending / rejected RM,
 * average approved claim and the utilisation rate HR asks for.
 */
final class CompanyDashboard
{
    /**
     * @return array<string, mixed> { month, companies: [ row per company ], totals }
     */
    public static function month(string $month, ?int $companyId = null): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::single('month', 'Month must be YYYY-MM.');
        }
        $from = $month . '-01';
        $to = date('Y-m-t', (int) strtotime($from));

        $companyFilter = $companyId !== null && $companyId > 0 ? ' AND c.`id` = ?' : '';
        $reserving = implode(',', array_fill(0, count(Claim::RESERVING), '?'));

        // Claims of the month, joined per company; enrolled = employees whose
        // entitlement period overlaps the month.
        $rows = Database::get()->select(
            'SELECT c.`id`, c.`name`,
                    COALESCE(m.claims, 0) AS claims,
                    COALESCE(m.utilised, 0) AS utilised,
                    COALESCE(m.approved, 0) AS approved,
                    COALESCE(m.approved_count, 0) AS approved_count,
                    COALESCE(m.pending, 0) AS pending,
                    COALESCE(m.rejected, 0) AS rejected,
                    (SELECT COUNT(*) FROM `cora_employees` e
                      WHERE e.`company_id` = c.`id` AND e.`deleted_at` IS NULL
                        AND e.`start_date` <= ? AND e.`end_date` >= ?) AS enrolled
               FROM `cora_companies` c
               LEFT JOIN (
                    SELECT `company_id`,
                           COUNT(*) AS claims,
                           COUNT(DISTINCT CASE WHEN `status` = ? THEN `employee_id` END) AS utilised,
                           SUM(CASE WHEN `status` = ? THEN `claim_amount` ELSE 0 END) AS approved,
                           SUM(CASE WHEN `status` IN (' . $reserving . ') THEN `claim_amount` ELSE 0 END) AS pending,
                           SUM(CASE WHEN `status` = ? THEN `claim_amount` ELSE 0 END) AS rejected,
                           SUM(CASE WHEN `status` = ? THEN 1 ELSE 0 END) AS approved_count
                      FROM `cora_claims`
                     WHERE `deleted_at` IS NULL AND `status` NOT IN (?, ?) AND `visit_date` BETWEEN ? AND ?
                     GROUP BY `company_id`
               ) m ON m.`company_id` = c.`id`
              WHERE c.`deleted_at` IS NULL' . $companyFilter . '
              ORDER BY approved DESC, c.`name` ASC',
            // Placeholders in SQL text order: enrolled sub-select, claim sums, company filter.
            [
                $to, $from,
                Claim::APPROVED, Claim::APPROVED, ...Claim::RESERVING, Claim::REJECTED, Claim::APPROVED,
                Claim::DRAFT, Claim::CANCELLED, $from, $to,
                ...($companyFilter !== '' ? [$companyId] : []),
            ]
        );

        $totals = ['enrolled' => 0, 'utilised' => 0, 'claims' => 0, 'approved' => 0.0, 'approved_count' => 0, 'pending' => 0.0, 'rejected' => 0.0];
        $companies = array_map(static function (array $r) use (&$totals): array {
            $row = [
                'id'             => (int) $r['id'],
                'name'           => (string) $r['name'],
                'enrolled'       => (int) $r['enrolled'],
                'utilised'       => (int) $r['utilised'],
                'claims'         => (int) $r['claims'],
                'approved'       => round((float) $r['approved'], 2),
                'approved_count' => (int) $r['approved_count'],
                'pending'        => round((float) $r['pending'], 2),
                'rejected'       => round((float) $r['rejected'], 2),
            ];
            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $row[$k];
            }
            return self::derive($row);
        }, $rows);

        return ['month' => $month, 'month_label' => date('F Y', (int) strtotime($from)), 'companies' => $companies, 'totals' => self::derive($totals)];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function derive(array $row): array
    {
        $row['approved'] = round((float) $row['approved'], 2);
        $row['pending'] = round((float) $row['pending'], 2);
        $row['rejected'] = round((float) $row['rejected'], 2);
        $row['average'] = $row['approved_count'] > 0 ? round($row['approved'] / $row['approved_count'], 2) : 0.0;
        $row['utilisation_rate'] = $row['enrolled'] > 0 ? round($row['utilised'] / $row['enrolled'] * 100, 1) : 0.0;

        return $row;
    }
}
