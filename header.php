<?php
/**
 * CORA base layout - top partial. Styling follows the atem / edm modules
 * strictly (same css/style.css with the cora- prefix, same header markup).
 *
 * A page includes this after optionally setting:
 *   $page_title          - shown in <title> and as the page heading (default "CORA")
 *   $page_subtitle       - one-line description under the heading
 *   $page_title_actions  - optional HTML rendered on the right of the page heading,
 *                          or a closure returning it (called after the role is
 *                          known, so it can read $cora_is_super / $cora_is_admin)
 *   $page_hide_title     - true to skip the heading row
 *   $page_access         - who may open the page: 'any' (default), 'outlet'
 *                          (outlet roles + admins), 'admin' (Corporate Admin,
 *                          Super Admin) or 'super' (Super Admin)
 *   $extra_css           - optional extra <link>/<style> markup for <head>
 *   $page_js             - optional page-specific script path, emitted by footer.php
 */
ob_start();
$page_title = $page_title ?? 'CORA';
$page_access = $page_access ?? 'any';

if (!defined('CORA_BASE')) {
    define('CORA_BASE', '/odb/' . basename(__DIR__) . '/');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo CORA_BASE; ?>css/logo.svg?v=<?php echo filemtime(__DIR__ . '/css/logo.svg'); ?>">
    <base href="/odb/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo CORA_BASE; ?>css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>" rel="stylesheet">
    <?php if (isset($extra_css)) echo $extra_css; ?>
    <!-- Set in <head> so inline page scripts resolve this module's real folder. -->
    <script>window.CORA_MODULE_BASE = <?php echo json_encode(CORA_BASE); ?>;</script>
</head>
<?php
require_once __DIR__ . '/../lock_adv.php';
$connect = 1;
include __DIR__ . '/../common/index_adv.php';

// staff.cora role tier and outlet(s) for the logged-in user. lock_adv.php does
// not expose these columns, so they are queried here (as edm does for staff.edm).
$cora_flag = 0;
$cora_outlets = [];
if (isset($id_user)) {
    $_cora_stmt = $conn->prepare('SELECT `cora`, `outlet` FROM `staff` WHERE `id` = ?');
    $_cora_uid = (int) $id_user;
    $_cora_stmt->bind_param('i', $_cora_uid);
    $_cora_stmt->execute();
    $_cora_row = $_cora_stmt->get_result()->fetch_assoc();
    $_cora_stmt->close();
    if ($_cora_row) {
        $cora_flag = (int) $_cora_row['cora'];
        $cora_outlets = array_values(array_filter(array_map('intval', explode(',', (string) $_cora_row['outlet'])), static fn (int $v): bool => $v > 0));
    }
}

// Dev role override (localhost only, toggled via dev-switch-role.php).
$cora_permission = isset($_SESSION['cora_dev_role_override']) ? (int) $_SESSION['cora_dev_role_override'] : $cora_flag;

// Real Super Admin only - a dev override never grants it.
$_is_superadmin = (!isset($_SESSION['cora_dev_role_override']) && $cora_flag === 1);
if ($_is_superadmin) {
    $cora_permission = 1;
}

$cora_is_super  = ($cora_permission === 1);
$cora_is_admin  = in_array($cora_permission, [1, 2], true);
$cora_is_outlet = in_array($cora_permission, [3, 4], true);
$cora_is_pic    = ($cora_permission === 3) || $cora_is_admin;

$_cora_allowed = match ($page_access) {
    'super'  => $cora_is_super,
    'admin'  => $cora_is_admin,
    'outlet' => $cora_is_outlet || $cora_is_admin,
    default  => $cora_permission >= 1 && $cora_permission <= 4,
};

if (!in_array($cora_permission, [1, 2, 3, 4], true)) {
    ob_end_clean();
    if (isset($_SESSION['cora_dev_role_override'])) {
        unset($_SESSION['cora_dev_role_override']);
        header('Location: ' . CORA_BASE . 'index.php');
    } else {
        header('Location: /odb/index.php');
    }
    exit;
}
if (!$_cora_allowed) {
    ob_end_clean();
    header('Location: ' . CORA_BASE . 'index.php');
    exit;
}
if (isset($page_title_actions) && $page_title_actions instanceof Closure) {
    $page_title_actions = $page_title_actions($cora_permission);
}
?>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="header" style="position: relative;">
        <b class="rtop"><b class="r1"></b><b class="r2"></b><b class="r3"></b><b class="r4"></b></b>
        <h1 class="headerH1"><img src='<?php echo CORA_BASE; ?>css/logo.svg?v=<?php echo filemtime(__DIR__ . '/css/logo.svg'); ?>' width='20px'>CORA</h1>
        <b class="rbottom"><b class="r4"></b><b class="r3"></b><b class="r2"></b><b class="r1"></b></b>
    </div>
    <div class="cora-container mb-3">

        <?php if (empty($page_hide_title)): ?>
        <div class="row mb-4">
            <div class="col-12 d-flex align-items-start justify-content-between gap-3">
                <div class="flex-grow-1" style="min-width:0;">
                    <h1 class="cora-page-title mb-0"><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if (!empty($page_subtitle)): ?>
                    <p class="text-muted small mb-0 mt-1"><?php echo htmlspecialchars($page_subtitle); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($page_title_actions)): ?>
                <div class="flex-shrink-0"><?php echo $page_title_actions; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
