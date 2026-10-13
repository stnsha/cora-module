<?php
/**
 * Development-only role switcher for CORA testing (follows atem / edm).
 * Sets a session override for the staff.cora role tier:
 *   1 = Super Admin, 2 = Corporate Admin, 3 = Outlet PIC, 4 = Outlet User
 * A simulated role never grants the real Super Admin flag.
 *
 * This file must NOT be deployed to production.
 */
date_default_timezone_set('Asia/Kuala_Lumpur');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$httpHost   = $_SERVER['HTTP_HOST'] ?? '';
$isLocal    = in_array($serverName, ['localhost', '127.0.0.1'], true)
    || str_contains($serverName, 'localhost')
    || str_contains($httpHost, 'localhost')
    || str_contains($httpHost, '127.0.0.1');

if (!$isLocal) {
    http_response_code(403);
    echo 'This endpoint is not available in production.';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$roleInput = $_POST['role'] ?? null;
if ($roleInput === 'clear') {
    unset($_SESSION['cora_dev_role_override']);
} elseif ($roleInput !== null && in_array((int) $roleInput, [1, 2, 3, 4], true)) {
    $_SESSION['cora_dev_role_override'] = (int) $roleInput;
}

$base = '/odb/' . basename(__DIR__) . '/';
$redirect = (string) ($_POST['redirect'] ?? '');
// Only relative redirects inside this module (no open redirect).
if ($redirect === '' || str_contains($redirect, '://') || !str_starts_with($redirect, $base)) {
    $redirect = $base . 'index.php';
}

header('Location: ' . $redirect);
exit;
