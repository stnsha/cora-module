<?php

declare(strict_types=1);

namespace Cora\Services;

use Cora\Core\Auth;
use Cora\Models\AuditLog;

/**
 * Writes the audit trail. Every edit, approval, rejection and entitlement
 * adjustment calls record() with the acting staff member; the time is the
 * row's created_at.
 */
final class AuditTrail
{
    /** Fields never copied into the log. */
    private const IGNORED = ['created_at', 'updated_at', 'deleted_at', 'ic_norm', 'so_norm'];

    /**
     * @param array<string, mixed>|null $before presented row before the change
     * @param array<string, mixed>|null $after  presented row after the change
     */
    public static function record(
        Auth $auth,
        string $entity,
        ?int $entityId,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?string $remark = null,
    ): void {
        $changes = self::diff($before, $after);
        AuditLog::create([
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'action'     => $action,
            'changes'    => $changes === [] ? null : $changes,
            'remark'     => $remark !== null ? mb_substr($remark, 0, 2000) : null,
            'staff_id'   => $auth->staffId,
            'staff_name' => $auth->staffName,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? mb_substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
        ]);
    }

    /**
     * { field: [before, after] } for every field whose value changed.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(?array $before, ?array $after): array
    {
        $before ??= [];
        $after ??= [];
        $out = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (in_array($key, self::IGNORED, true) || $key === 'id') {
                continue;
            }
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            if (self::same($b, $a)) {
                continue;
            }
            $out[$key] = [$b, $a];
        }

        return $out;
    }

    private static function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return json_encode($a) === json_encode($b);
    }
}
