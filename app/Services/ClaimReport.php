<?php

declare(strict_types=1);

namespace Cora\Services;

use Cora\Core\Database;
use Cora\Core\ValidationException;
use Cora\Models\Claim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Claim listing for the Claim History, Claim Review queue and the Monthly
 * Claim Report, with one filter set:
 *   month (YYYY-MM of the visit date), date_from / date_to, company_id,
 *   outlet_id, q (employee name / IC / employee ID / SO / claim no),
 *   status (int or comma list), payment_method.
 * Outlet roles always pass their outlet ids, which restricts every query.
 */
final class ClaimReport
{
    /** Report columns: key => heading (Monthly Claim Report minimum fields + amounts). */
    public const COLUMNS = [
        'claim_no'             => 'Claim No.',
        'employee_name'        => 'Employee Name',
        'employee_ic'          => 'IC / Passport',
        'employee_no'          => 'Employee ID',
        'company_name'         => 'Company',
        'outlet_name'          => 'Outlet',
        'visit_date'           => 'Visitation Date',
        'so_no'                => 'Sales Order Number',
        'total_amount'         => 'Purchase Amount (RM)',
        'claim_amount'         => 'Claim Amount (RM)',
        'self_pay_amount'      => 'Self-Pay (RM)',
        'payment_method_label' => 'Payment Method',
        'claim_type'           => 'Claim Type',
        'status_label'         => 'Claim Status',
        'submitted_at'         => 'Submitted At',
        'approval_date'        => 'Approval Date',
        'approved_by'          => 'Approved By',
        'rejection_remark'     => 'Rejection Remark',
    ];

    public const LIMIT = 5000;

    /**
     * @param array<string, mixed> $filters
     * @param list<int>|null $outletIds null = all outlets (admin)
     * @return list<array<string, mixed>>
     */
    public static function rows(array $filters, ?array $outletIds, int $limit = self::LIMIT): array
    {
        [$where, $params] = self::where($filters, $outletIds);
        $rows = Database::get()->select(
            'SELECT * FROM `cora_claims` WHERE ' . $where . ' ORDER BY `id` DESC LIMIT ' . max(1, $limit),
            $params
        );

        return array_map(static function (array $row): array {
            $row = Claim::decorate(Claim::present($row));
            $approved = (int) $row['status'] === Claim::APPROVED;
            $row['approval_date'] = $approved ? $row['reviewed_at'] : null;
            $row['approved_by'] = $approved ? $row['reviewed_by_name'] : null;
            $row['rejection_remark'] = (int) $row['status'] === Claim::REJECTED ? $row['review_remark'] : null;
            return $row;
        }, $rows);
    }

    /**
     * Claim counts per status for the same filters (status itself ignored) - tab counts.
     *
     * @param array<string, mixed> $filters
     * @param list<int>|null $outletIds
     * @return array<int, int>
     */
    public static function counts(array $filters, ?array $outletIds): array
    {
        unset($filters['status']);
        [$where, $params] = self::where($filters, $outletIds);
        $out = array_fill_keys(array_keys(Claim::STATUSES), 0);
        foreach (Database::get()->select('SELECT `status`, COUNT(*) AS n FROM `cora_claims` WHERE ' . $where . ' GROUP BY `status`', $params) as $r) {
            $out[(int) $r['status']] = (int) $r['n'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @param list<int>|null $outletIds
     * @return array{0: string, 1: list<mixed>}
     */
    public static function where(array $filters, ?array $outletIds): array
    {
        $where = ['`deleted_at` IS NULL'];
        $params = [];

        if ($outletIds !== null) {
            if ($outletIds === []) {
                return ['1 = 0', []];
            }
            $where[] = '`outlet_id` IN (' . implode(',', array_fill(0, count($outletIds), '?')) . ')';
            array_push($params, ...$outletIds);
        }

        $month = trim((string) ($filters['month'] ?? ''));
        if ($month !== '') {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
                throw ValidationException::single('month', 'Month must be YYYY-MM.');
            }
            $where[] = '`visit_date` BETWEEN ? AND ?';
            $params[] = $month . '-01';
            $params[] = date('Y-m-t', (int) strtotime($month . '-01'));
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $d = Entitlement::ymd($filters[$key] ?? null);
            if ($d !== null) {
                $where[] = '`visit_date` ' . $op . ' ?';
                $params[] = $d;
            }
        }
        foreach (['company_id', 'outlet_id', 'employee_id', 'payment_method'] as $key) {
            if ((int) ($filters[$key] ?? 0) > 0) {
                $where[] = '`' . $key . '` = ?';
                $params[] = (int) $filters[$key];
            }
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $list = array_values(array_filter(array_map('intval', explode(',', $status)), static fn (int $s): bool => isset(Claim::STATUSES[$s])));
            if ($list !== []) {
                $where[] = '`status` IN (' . implode(',', $list) . ')';
            }
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $where[] = '(`employee_name` LIKE ? OR `employee_ic` LIKE ? OR `employee_no` LIKE ? OR `so_no` LIKE ? OR `claim_no` LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params];
    }

    public static function canXlsx(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    /**
     * Streams the report as CSV or XLSX and exits.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function download(array $rows, string $format, string $baseName): never
    {
        $file = preg_replace('/[^A-Za-z0-9_\-]/', '_', $baseName) . '_' . date('Ymd_His');
        $headings = array_values(self::COLUMNS);
        $matrix = array_map(static fn (array $r): array => array_map(
            static fn (string $k): string|float => in_array($k, ['total_amount', 'claim_amount', 'self_pay_amount'], true)
                ? (float) $r[$k] : (string) ($r[$k] ?? ''),
            array_keys(self::COLUMNS)
        ), $rows);

        if ($format === 'xlsx' && self::canXlsx()) {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Claims');
            $sheet->fromArray($headings, null, 'A1');
            if ($matrix !== []) {
                $sheet->fromArray($matrix, null, 'A2', true);
            }
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $file . '.xlsx"');
            header('Cache-Control: no-store');
            (new Xlsx($book))->save('php://output');
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headings, ',', '"', '\\');
        foreach ($matrix as $line) {
            fputcsv($out, $line, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
