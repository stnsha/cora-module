<?php

declare(strict_types=1);

namespace Cora\Services;

use Cora\Core\Auth;
use Cora\Core\Database;
use Cora\Models\Claim;
use Cora\Models\EmployeeImport;

/**
 * Notification centre counters.
 *   Admin:  claims pending review, new today, returned claims resubmitted,
 *           uploads awaiting confirmation.
 *   Outlet: claims returned for amendment, drafts not yet submitted.
 */
final class Notifications
{
    /** @return array<string, mixed> */
    public static function forUser(Auth $auth): array
    {
        $db = Database::get();
        if ($auth->isAdmin()) {
            $queue = implode(',', Claim::IN_QUEUE);
            $row = $db->first(
                'SELECT
                    SUM(`status` IN (' . $queue . ')) AS pending,
                    SUM(`status` IN (' . $queue . ') AND DATE(`submitted_at`) = CURDATE()) AS new_today,
                    SUM(`status` IN (' . $queue . ') AND `resubmit_count` > 0) AS resubmitted,
                    SUM(`status` = ?) AS awaiting_outlet
                   FROM `cora_claims` WHERE `deleted_at` IS NULL',
                [Claim::AMENDMENT_REQUIRED]
            ) ?? [];
            $uploads = (int) $db->scalar(
                'SELECT COUNT(*) FROM `cora_employee_imports` WHERE `status` = ? AND `deleted_at` IS NULL',
                [EmployeeImport::VALIDATED]
            );

            $items = [
                ['key' => 'pending', 'count' => (int) ($row['pending'] ?? 0), 'label' => 'Claims pending review', 'href' => 'review/index.php'],
                ['key' => 'new_today', 'count' => (int) ($row['new_today'] ?? 0), 'label' => 'New today', 'href' => 'review/index.php?today=1'],
                ['key' => 'resubmitted', 'count' => (int) ($row['resubmitted'] ?? 0), 'label' => 'Returned claims resubmitted', 'href' => 'review/index.php?resubmitted=1'],
                ['key' => 'awaiting_outlet', 'count' => (int) ($row['awaiting_outlet'] ?? 0), 'label' => 'Waiting for outlet amendment', 'href' => 'claims/index.php?status=' . Claim::AMENDMENT_REQUIRED],
                ['key' => 'uploads', 'count' => $uploads, 'label' => 'Employee uploads awaiting confirmation', 'href' => 'employees/import.php'],
            ];

            return ['total' => (int) ($row['pending'] ?? 0) + $uploads, 'items' => $items];
        }

        if ($auth->outletIds === []) {
            return ['total' => 0, 'items' => []];
        }
        $in = implode(',', array_fill(0, count($auth->outletIds), '?'));
        $row = $db->first(
            'SELECT SUM(`status` = ?) AS amendment, SUM(`status` = ?) AS drafts
               FROM `cora_claims` WHERE `deleted_at` IS NULL AND `outlet_id` IN (' . $in . ')',
            [Claim::AMENDMENT_REQUIRED, Claim::DRAFT, ...$auth->outletIds]
        ) ?? [];
        $items = [
            ['key' => 'amendment', 'count' => (int) ($row['amendment'] ?? 0), 'label' => 'Claims returned for amendment', 'href' => 'claims/index.php?status=' . Claim::AMENDMENT_REQUIRED],
            ['key' => 'drafts', 'count' => (int) ($row['drafts'] ?? 0), 'label' => 'Draft claims not yet submitted', 'href' => 'claims/index.php?status=' . Claim::DRAFT],
        ];

        return ['total' => (int) ($row['amendment'] ?? 0), 'items' => $items];
    }
}
