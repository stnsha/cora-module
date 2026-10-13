<?php

declare(strict_types=1);

namespace Cora\Controllers;

use Cora\Core\Controller;
use Cora\Core\ValidationException;
use Cora\Models\AuditLog;
use Cora\Services\Entitlement;

/**
 * Audit Log (audit/) - Corporate Admin and Super Admin. Actions:
 *   audit_list   ?entity= &entity_id= &q= (staff / action / remark) &date_from= &date_to=
 *                newest first, at most LIMIT rows
 *   audit_get    ?id=   one entry with its changes
 */
final class AuditController extends Controller
{
    private const LIMIT = 2000;

    protected function handle(string $action): mixed
    {
        $this->requireAdmin();

        switch ($action) {
            case 'audit_list':
                return $this->listing();
            case 'audit_get':
                return AuditLog::findOrFail($this->requireId('Audit entry'));
        }
        $this->unknown();
    }

    private function listing(): array
    {
        $where = ['1 = 1'];
        $params = [];
        $entity = (string) $this->request->query('entity', '');
        if ($entity !== '') {
            if (!in_array($entity, AuditLog::ENTITIES, true)) {
                throw ValidationException::single('entity', 'Unknown record type.');
            }
            $where[] = '`entity` = ?';
            $params[] = $entity;
        }
        $entityId = (int) $this->request->query('entity_id', 0);
        if ($entityId > 0) {
            $where[] = '`entity_id` = ?';
            $params[] = $entityId;
        }
        $q = trim((string) $this->request->query('q', ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $where[] = '(`staff_name` LIKE ? OR `action` LIKE ? OR `remark` LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $from = Entitlement::ymd($this->request->query('date_from'));
        if ($from !== null) {
            $where[] = '`created_at` >= ?';
            $params[] = $from . ' 00:00:00';
        }
        $to = Entitlement::ymd($this->request->query('date_to'));
        if ($to !== null) {
            $where[] = '`created_at` <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return AuditLog::where(implode(' AND ', $where), $params, null, self::LIMIT);
    }
}
