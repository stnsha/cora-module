<?php

declare(strict_types=1);

namespace Cora\Models;

use Cora\Core\Model;

/**
 * Audit trail row: who did what to which record, when, and the changed
 * fields as { field: [before, after] }. Written only through
 * Services\AuditTrail. Table: cora_audit_logs.
 */
final class AuditLog extends Model
{
    public const ENTITIES = ['company', 'employee', 'claim', 'import', 'setting', 'user'];

    protected const TABLE = 'cora_audit_logs';

    protected const FILLABLE = ['entity', 'entity_id', 'action', 'changes', 'remark', 'staff_id', 'staff_name', 'ip_address'];

    protected const CASTS = [
        'entity_id' => 'int',
        'staff_id'  => 'int',
        'changes'   => 'json',
    ];

    protected const ORDER = '`id` DESC';

    /** @return list<array<string, mixed>> oldest first (claim timeline) */
    public static function forRecord(string $entity, int $id): array
    {
        return self::where('`entity` = ? AND `entity_id` = ?', [$entity, $id], '`id` ASC');
    }
}
