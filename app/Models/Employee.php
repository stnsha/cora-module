<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Model;
use Cora\Core\ValidationException;

/**
 * Employee Master: one eligible employee of one company.
 *
 * The information remark (info_remark) is kept apart from the restriction /
 * override (restriction_type + override_limit / override_until +
 * restriction_remark) so an operational instruction never depends on
 * someone noticing free text.
 * Table: cora_employees.
 */
final class Employee extends Model
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public const STATUSES = [
        self::STATUS_ACTIVE   => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    public const RESTRICTION_NONE = 0;
    public const RESTRICTION_LIMIT = 1;
    public const RESTRICTION_SUSPENDED = 2;

    public const RESTRICTIONS = [
        self::RESTRICTION_NONE      => 'None',
        self::RESTRICTION_LIMIT     => 'Entitlement limit override',
        self::RESTRICTION_SUSPENDED => 'Entitlement suspended',
    ];

    protected const TABLE = 'cora_employees';

    protected const FILLABLE = [
        'company_id', 'employee_no', 'name', 'ic_no', 'ic_norm', 'phone', 'address', 'department',
        'entitlement_limit', 'start_date', 'end_date', 'status', 'info_remark', 'restriction_type',
        'override_limit', 'override_until', 'restriction_remark', 'import_id', 'created_by', 'created_by_name',
    ];

    protected const CASTS = [
        'company_id'        => 'int',
        'entitlement_limit' => 'decimal',
        'override_limit'    => 'decimal',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'override_until'    => 'date',
        'status'            => 'int',
        'restriction_type'  => 'int',
        'import_id'         => 'int',
    ];

    protected const ORDER = '`name` ASC';

    /** IC / passport -> comparison key: upper case, letters and digits only. */
    public static function normalizeIc(string $ic): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ic) ?? '');
    }

    /** Phone -> digits only (search). */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    /** IC uniqueness within one company, against active rows. */
    public static function assertUniqueIc(int $companyId, string $icNorm, ?int $ignoreId = null): void
    {
        $sql = '`company_id` = ? AND `ic_norm` = ?';
        $params = [$companyId, $icNorm];
        if ($ignoreId !== null) {
            $sql .= ' AND `id` != ?';
            $params[] = $ignoreId;
        }
        if (self::where($sql, $params, null, 1) !== []) {
            throw ValidationException::single('ic_no', 'An employee with this IC / passport already exists in this company.');
        }
    }

    public static function findByIc(int $companyId, string $icNorm): ?array
    {
        return self::where('`company_id` = ? AND `ic_norm` = ?', [$companyId, $icNorm], null, 1)[0] ?? null;
    }

    /**
     * Employee search (Eligibility Checker, Employee Master).
     * IC / passport and employee ID match exactly or by prefix, name and
     * phone by substring; company is an optional filter.
     *
     * @return list<array<string, mixed>> rows with company_name
     */
    public static function search(string $q, ?int $companyId, int $limit = 25): array
    {
        $q = trim($q);
        $where = ['e.`deleted_at` IS NULL', 'c.`deleted_at` IS NULL'];
        $params = [];
        if ($companyId !== null && $companyId > 0) {
            $where[] = 'e.`company_id` = ?';
            $params[] = $companyId;
        }
        if ($q !== '') {
            $or = [];
            $ic = self::normalizeIc($q);
            if ($ic !== '') {
                $or[] = 'e.`ic_norm` LIKE ?';
                $params[] = addcslashes($ic, '\\%_') . '%';
                $or[] = 'e.`employee_no` = ?';
                $params[] = $q;
            }
            $or[] = 'e.`name` LIKE ?';
            $params[] = '%' . addcslashes($q, '\\%_') . '%';
            $phone = self::normalizePhone($q);
            if (strlen($phone) >= 4) {
                $or[] = "REPLACE(REPLACE(REPLACE(e.`phone`, '-', ''), ' ', ''), '+', '') LIKE ?";
                $params[] = '%' . $phone . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }

        $rows = self::db()->select(
            'SELECT e.*, c.`name` AS company_name
               FROM `cora_employees` e
               JOIN `cora_companies` c ON c.`id` = e.`company_id`
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY e.`name` ASC
              LIMIT ' . max(1, $limit),
            $params
        );

        return array_map([self::class, 'present'], $rows);
    }
}
