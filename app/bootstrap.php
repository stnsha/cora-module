<?php

declare(strict_types=1);

/**
 * CORA module bootstrap - loaded by every <folder>/api.php and by any page that
 * reads CORA data server-side.
 *
 * MVC layout (namespace Cora\, PSR-4 under app/):
 *   Core/         Database (mysqli over odb's $conn), Request, Response, Auth,
 *                 Validator, base Model + Controller, exceptions
 *   Models/       one class per cora_* table (plus read-only odb tables)
 *   Services/     logic spanning several tables (entitlement, claim workflow,
 *                 employee import, reports, audit trail)
 *   Controllers/  one class per module folder - that page's actions
 *   Views         the module folders themselves (index.php + JS)
 *
 * All data lives in the odb database (cora_* tables, see sql/cora_master.sql).
 */

// Timezone, Composer and the Cora\ autoloader.
require_once __DIR__ . '/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('CORA_BASE')) {
    define('CORA_BASE', '/odb/' . basename(dirname(__DIR__)) . '/');
}

// Reuse odb's connection when a page (header.php) already opened it.
if (!isset($conn) || !($conn instanceof mysqli)) {
    $connect = 1;
    include __DIR__ . '/../../common/index_adv.php';
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

\Cora\Core\Database::boot($conn);
