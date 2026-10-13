<?php
/**
 * Default CORA entry point. Admins land on the Dashboard, outlet roles on
 * the Eligibility Checker. Access control is enforced by the target page
 * (header.php); without a CORA role header.php sends the user back to odb.
 */
if (!defined('CORA_BASE')) {
    define('CORA_BASE', '/odb/' . basename(__DIR__) . '/');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$target = 'eligibility/index.php';
$username = $_SESSION['myusername'] ?? null;
if (is_string($username) && $username !== '') {
    $connect = 1;
    include __DIR__ . '/../common/index_adv.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare('SELECT `cora` FROM `staff` WHERE `username` = ? AND `recycle` != 1 LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $role = isset($_SESSION['cora_dev_role_override']) ? (int) $_SESSION['cora_dev_role_override'] : (int) ($row['cora'] ?? 0);
        if (in_array($role, [1, 2], true)) {
            $target = 'dashboard/index.php';
        }
    }
}

header('Location: ' . CORA_BASE . $target);
exit;
