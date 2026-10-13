<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Database;

/**
 * odb outlets (`outlet`), read-only. An outlet account's staff.outlet holds
 * the outlet id(s) it acts for; claims record the outlet and a name snapshot.
 */
final class Outlet
{
    /**
     * @param list<int> $ids
     * @return list<array{id: int, code: string, name: string}>
     */
    public static function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Database::get()->select(
            'SELECT `id`, `code`, `comp_name` FROM `outlet` WHERE `id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
              AND `recycle` != 1 ORDER BY `comp_name` ASC',
            $ids
        );

        return array_map([self::class, 'present'], $rows);
    }

    /** @return array{id: int, code: string, name: string}|null */
    public static function find(int $id): ?array
    {
        return self::byIds([$id])[0] ?? null;
    }

    /** @return list<array{id: int, code: string, name: string}> every active outlet (report filter) */
    public static function all(): array
    {
        $rows = Database::get()->select('SELECT `id`, `code`, `comp_name` FROM `outlet` WHERE `recycle` != 1 ORDER BY `comp_name` ASC');

        return array_map([self::class, 'present'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, code: string, name: string}
     */
    private static function present(array $row): array
    {
        return ['id' => (int) $row['id'], 'code' => (string) $row['code'], 'name' => trim((string) $row['comp_name'])];
    }
}
