<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Model;

/**
 * Company Master: a corporate client whose employee benefits Alpro
 * administers directly. Linked to the Corporate Client List by client_id;
 * benefit_criteria / general_remark / category left empty inherit from it
 * (see rules()).
 * Table: cora_companies.
 */
final class Company extends Model
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;

    public const STATUSES = [
        self::STATUS_ACTIVE   => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    public const LIMIT_BLOCK = 1;
    public const LIMIT_WARN = 2;

    public const LIMIT_MODES = [
        self::LIMIT_BLOCK => 'Block claims above the available balance',
        self::LIMIT_WARN  => 'Warn only (allow, flag for review)',
    ];

    protected const TABLE = 'cora_companies';

    protected const FILLABLE = [
        'client_id', 'code', 'name', 'category', 'default_limit', 'limit_mode', 'period_start', 'period_end',
        'benefit_criteria', 'general_remark', 'hr_contact_name', 'hr_contact_email', 'status',
        'created_by', 'created_by_name',
    ];

    protected const CASTS = [
        'client_id'     => 'int',
        'default_limit' => 'decimal',
        'limit_mode'    => 'int',
        'status'        => 'int',
        'period_start'  => 'date',
        'period_end'    => 'date',
    ];

    protected const ORDER = '`name` ASC';

    /**
     * Company rule, resolved: own value first, then the Corporate Client
     * List, then the Default Alpro rule. Each value says where it came from.
     *
     * @param array<string, mixed> $company presented row
     * @return array<string, mixed>
     */
    public static function rules(array $company): array
    {
        $client = !empty($company['client_id']) ? ClientList::find((int) $company['client_id']) : null;
        $pick = static function (?string $own, ?string $inherited, ?string $default): array {
            if ($own !== null && trim($own) !== '') {
                return [trim($own), 'company'];
            }
            if ($inherited !== null && trim($inherited) !== '') {
                return [trim($inherited), 'client_list'];
            }

            return [$default !== null && trim($default) !== '' ? trim($default) : null, 'default'];
        };

        [$criteria, $criteriaSource] = $pick($company['benefit_criteria'] ?? null, $client['benefit_criteria'] ?? null, Setting::value('default_benefit_criteria'));
        [$remark, $remarkSource] = $pick($company['general_remark'] ?? null, $client['general_remark'] ?? null, null);
        [$category, $categorySource] = $pick($company['category'] ?? null, $client['category'] ?? null, null);

        return [
            'benefit_criteria'        => $criteria,
            'benefit_criteria_source' => $criteriaSource,
            'general_remark'          => $remark,
            'general_remark_source'   => $remarkSource,
            'category'                => $category,
            'category_source'         => $categorySource,
            'limit_mode'              => (int) ($company['limit_mode'] ?? Setting::defaultLimitMode()),
            'default_limit'           => $company['default_limit'] ?? Setting::defaultLimit(),
            'client'                  => $client,
        ];
    }

    /** Active company whose name or code matches (case-insensitive) - upload matching. */
    public static function findByNameOrCode(string $value): ?array
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return null;
        }

        return self::where('LOWER(TRIM(`name`)) = ? OR LOWER(TRIM(`code`)) = ?', [$v, $v], null, 1)[0] ?? null;
    }

    /** @return list<array<string, mixed>> listing with employee counts */
    public static function listing(): array
    {
        $rows = self::db()->select(
            'SELECT c.*, (SELECT COUNT(*) FROM `cora_employees` e WHERE e.`company_id` = c.`id` AND e.`deleted_at` IS NULL) AS employee_count,
                    l.`client_name`
               FROM `cora_companies` c
               LEFT JOIN `micare_client_list` l ON l.`id` = c.`client_id`
              WHERE c.`deleted_at` IS NULL
              ORDER BY c.`name` ASC'
        );

        return array_map(static function (array $r): array {
            $r = self::present($r);
            $r['employee_count'] = (int) $r['employee_count'];
            return $r;
        }, $rows);
    }
}
