<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Model;

/**
 * Default Alpro rules (key / value) - the last level of the rule hierarchy
 * Individual employee rule -> Company rule -> Default Alpro rule.
 * Table: cora_settings. Seeded by sql/cora_seed.sql.
 */
final class Setting extends Model
{
    /** key => [label, type]; type: decimal | int | text | list */
    public const KEYS = [
        'default_limit'            => ['Default annual limit (RM)', 'decimal'],
        'default_limit_mode'       => ['Default over-limit handling', 'int'],
        'default_benefit_criteria' => ['Default benefit / claim criteria', 'text'],
        'claim_types'              => ['Claim types', 'list'],
    ];

    protected const TABLE = 'cora_settings';

    protected const FILLABLE = ['key', 'value', 'updated_by', 'updated_by_name'];

    protected const ORDER = '`key` ASC';

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function value(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (self::all() as $row) {
                self::$cache[(string) $row['key']] = $row['value'] === null ? null : (string) $row['value'];
            }
        }

        return self::$cache[$key] ?? $default;
    }

    public static function defaultLimit(): float
    {
        return round((float) self::value('default_limit', '0'), 2);
    }

    public static function defaultLimitMode(): int
    {
        $mode = (int) self::value('default_limit_mode', (string) Company::LIMIT_BLOCK);

        return array_key_exists($mode, Company::LIMIT_MODES) ? $mode : Company::LIMIT_BLOCK;
    }

    /** @return list<string> */
    public static function claimTypes(): array
    {
        $list = json_decode((string) self::value('claim_types', '[]'), true);

        return is_array($list) ? array_values(array_filter(array_map('strval', $list), 'strlen')) : [];
    }

    /** Insert or update one key; returns the previous value. */
    public static function put(string $key, ?string $value, ?int $staffId, ?string $staffName): ?string
    {
        $row = self::where('`key` = ?', [$key], null, 1)[0] ?? null;
        $data = ['key' => $key, 'value' => $value, 'updated_by' => $staffId, 'updated_by_name' => $staffName];
        if ($row === null) {
            self::create($data);
        } else {
            self::update((int) $row['id'], $data);
        }
        self::$cache = null;

        return $row['value'] ?? null;
    }

    /** @return array<string, mixed> every known key with its typed value */
    public static function asArray(): array
    {
        return [
            'default_limit'            => self::defaultLimit(),
            'default_limit_mode'       => self::defaultLimitMode(),
            'default_benefit_criteria' => self::value('default_benefit_criteria'),
            'claim_types'              => self::claimTypes(),
        ];
    }
}
