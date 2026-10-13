<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Database;

/**
 * The existing Corporate Client List (odb `micare_client_list`, TPA name in
 * `micare_administrator`). Read-only here: CORA inherits each company's
 * category and general rules from it instead of keeping a second rule set,
 * so the two can never carry conflicting instructions. Not a Core\Model -
 * the table soft-deletes with `recycle = 1`, not deleted_at.
 */
final class ClientList
{
    private const SELECT = 'SELECT c.`id`, c.`client_name`, c.`status`, c.`duration_ltm`, c.`duration_minor_ailment`,
                c.`micare_exclusion_list`, c.`panel_list`, c.`ltm`, c.`minor_ailment`, c.`supplement`,
                c.`supplement_remark`, c.`outlet_involved`, c.`remark`, c.`privilege`, a.`administrator`
           FROM `micare_client_list` c
           LEFT JOIN `micare_administrator` a ON a.`id` = c.`administrator_id`';

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Database::get()->first(self::SELECT . ' WHERE c.`id` = ? AND c.`recycle` != 1 LIMIT 1', [$id]);

        return $row === null ? null : self::present($row);
    }

    /**
     * Client list search for the Company Master picker.
     *
     * @return list<array<string, mixed>>
     */
    public static function search(string $q, int $limit = 20): array
    {
        $like = '%' . addcslashes($q, '\\%_') . '%';
        $rows = Database::get()->select(
            self::SELECT . ' WHERE c.`recycle` != 1 AND c.`client_name` LIKE ? ORDER BY c.`client_name` ASC LIMIT ' . max(1, $limit),
            [$like]
        );

        return array_map([self::class, 'present'], $rows);
    }

    /**
     * Row -> what the portal shows: the TPA / administrator is the company
     * category, the coverage flags become the benefit criteria text, and the
     * remark (stored as HTML) becomes plain text.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function present(array $row): array
    {
        $yes = static fn (mixed $v): bool => (int) $v === 1;
        $lines = [];
        $lines[] = 'Long-term medication (LTM): ' . ($yes($row['ltm'])
            ? 'Covered' . (trim((string) $row['duration_ltm']) !== '' ? ' (' . trim((string) $row['duration_ltm']) . ' supply)' : '')
            : 'Not covered');
        $lines[] = 'Minor ailment: ' . ($yes($row['minor_ailment'])
            ? 'Covered' . (trim((string) $row['duration_minor_ailment']) !== '' ? ' (' . trim((string) $row['duration_minor_ailment']) . ' supply)' : '')
            : 'Not covered');
        $lines[] = 'Supplement: ' . ($yes($row['supplement']) ? 'Covered' : 'Not covered')
            . (trim((string) $row['supplement_remark']) !== '' ? ' - ' . trim((string) $row['supplement_remark']) : '');
        if ($yes($row['micare_exclusion_list'])) {
            $lines[] = 'Exclusion list applies.';
        }
        if ($yes($row['panel_list'])) {
            $lines[] = 'Panel list applies.';
        }
        if (trim((string) $row['outlet_involved']) !== '') {
            $lines[] = 'Outlets: ' . trim((string) $row['outlet_involved']);
        }
        $privilege = self::plainText((string) ($row['privilege'] ?? ''));
        if ($privilege !== '') {
            $lines[] = 'Privilege: ' . $privilege;
        }

        return [
            'id'               => (int) $row['id'],
            'client_name'      => trim((string) $row['client_name']),
            'client_status'    => trim((string) $row['status']),
            'category'         => $row['administrator'] !== null ? trim((string) $row['administrator']) : null,
            'benefit_criteria' => implode("\n", $lines),
            'general_remark'   => self::plainText((string) ($row['remark'] ?? '')),
        ];
    }

    /** HTML remark -> plain text with line breaks (never rendered as HTML). */
    public static function plainText(string $html): string
    {
        $html = preg_replace('~<\s*(br|/p|/tr|/li|/div|/h[1-6])\b[^>]*>~i', "\n", $html) ?? $html;
        $html = preg_replace('~<\s*/t[dh]\s*>~i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $lines = array_filter(array_map(static fn (string $l): string => trim(preg_replace('/[ \t]+/', ' ', $l) ?? $l), explode("\n", $text)), 'strlen');

        return implode("\n", $lines);
    }
}
