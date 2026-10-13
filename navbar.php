<?php
/**
 * CORA top navigation. Styling follows the atem / edm modules (cora- prefix,
 * see css/style.css). Menu items are gated by the staff.cora tier resolved in
 * header.php: 'outlet' = outlet roles and admins, 'admin' = Corporate Admin +
 * Super Admin, 'super' = Super Admin, 'outlet_only' = outlet roles only.
 */

// Dev role switcher toolbar (localhost + real Super Admin only).
$_navbar_serverName = $_SERVER['SERVER_NAME'] ?? '';
$_navbar_httpHost   = $_SERVER['HTTP_HOST'] ?? '';
$_navbar_isLocal    = in_array($_navbar_serverName, ['localhost', '127.0.0.1'], true)
    || str_contains($_navbar_serverName, 'localhost')
    || str_contains($_navbar_httpHost, 'localhost')
    || str_contains($_navbar_httpHost, '127.0.0.1');

if ($_navbar_isLocal && (int) ($cora_flag ?? 0) === 1) {
    $_navbar_roleLabels = [
        1 => 'super admin',
        2 => 'corporate admin',
        3 => 'outlet pic',
        4 => 'outlet user',
    ];
    $_navbar_activeRole      = isset($_SESSION['cora_dev_role_override']) ? (int) $_SESSION['cora_dev_role_override'] : null;
    $_navbar_activeRoleLabel = $_navbar_activeRole !== null && isset($_navbar_roleLabels[$_navbar_activeRole])
        ? $_navbar_roleLabels[$_navbar_activeRole]
        : 'DB Default';
    $_navbar_currentUri      = $_SERVER['REQUEST_URI'] ?? CORA_BASE . 'index.php';
?>
<div
    style="background:#12122a;color:#d0d0f0;padding:5px 14px;font-size:11px;font-family:monospace;display:flex;align-items:center;gap:10px;flex-wrap:wrap;border-bottom:1px solid #333;">
    <span style="color:#888;letter-spacing:.05em;">DEV ROLE</span>
    <strong style="color:#f0c040;">[<?php echo htmlspecialchars($_navbar_activeRoleLabel); ?>]</strong>
    <?php foreach ($_navbar_roleLabels as $_r => $_label): ?>
    <form method="POST" action="<?php echo CORA_BASE; ?>dev-switch-role.php" style="display:inline;margin:0;">
        <input type="hidden" name="role" value="<?php echo $_r; ?>">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_navbar_currentUri); ?>">
        <button type="submit"
            style="background:<?php echo ($_navbar_activeRole === $_r ? '#2e2e6e' : '#1e1e3e'); ?>;color:<?php echo ($_navbar_activeRole === $_r ? '#f0c040' : '#aaa'); ?>;border:1px solid <?php echo ($_navbar_activeRole === $_r ? '#555' : '#333'); ?>;padding:2px 7px;font-size:11px;cursor:pointer;border-radius:3px;font-family:monospace;"><?php echo $_r; ?>:
            <?php echo $_label; ?></button>
    </form>
    <?php endforeach; ?>
    <?php if ($_navbar_activeRole !== null): ?>
    <form method="POST" action="<?php echo CORA_BASE; ?>dev-switch-role.php" style="display:inline;margin:0;">
        <input type="hidden" name="role" value="clear">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_navbar_currentUri); ?>">
        <button type="submit"
            style="background:#3a1010;color:#ff8888;border:1px solid #a44;padding:2px 7px;font-size:11px;cursor:pointer;border-radius:3px;font-family:monospace;">Clear
            Override</button>
    </form>
    <?php endif; ?>
</div>
<?php } ?>

<?php
// One page = one folder: the folder name drives the active state, the script
// basename separates sibling screens in one folder (e.g. employees/import.php).
$_self          = $_SERVER['PHP_SELF'] ?? '';
$current_dir    = basename(dirname($_self));
$current_script = basename($_self);

/**
 * Each entry: label, gate, href (relative to CORA_BASE) or items (dropdown
 * children: folder, file, label, optional files / folders that also mark it
 * active, optional gate).
 */
$cora_nav = [
    ['label' => 'Dashboard', 'gate' => 'admin', 'href' => 'dashboard/index.php'],
    ['label' => 'Eligibility Checker', 'gate' => 'outlet', 'href' => 'eligibility/index.php'],
    [
        'label' => 'Claims',
        'gate'  => 'outlet',
        'items' => [
            ['folder' => 'review', 'file' => 'index.php', 'label' => 'Claim Review', 'gate' => 'admin'],
            ['folder' => 'claims', 'file' => 'index.php', 'label' => 'Claim History', 'files' => ['view.php', 'create.php']],
        ],
    ],
    [
        'label' => 'Employees',
        'gate'  => 'admin',
        'items' => [
            ['folder' => 'employees', 'file' => 'index.php', 'label' => 'Employee Master'],
            ['folder' => 'employees', 'file' => 'import.php', 'label' => 'Upload Employees'],
        ],
    ],
    ['label' => 'Companies', 'gate' => 'admin', 'href' => 'companies/index.php'],
    ['label' => 'Reports', 'gate' => 'admin', 'href' => 'reports/index.php'],
    ['label' => 'Audit Log', 'gate' => 'admin', 'href' => 'audit/index.php'],
    [
        'label' => 'Settings',
        'gate'  => 'super',
        'items' => [
            ['folder' => 'settings', 'file' => 'index.php', 'label' => 'Default Rules'],
            ['folder' => 'settings', 'file' => 'users.php', 'label' => 'Users & permissions', 'gate' => 'realsuper'],
        ],
    ],
];

$cora_gate_ok = [
    'outlet'    => !empty($cora_is_outlet) || !empty($cora_is_admin),
    'admin'     => !empty($cora_is_admin),
    'super'     => !empty($cora_is_super),
    'realsuper' => !empty($_is_superadmin),
];
?>
<nav class="cora-nav navbar navbar-expand-lg navbar-light mb-3">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>
        <div class="collapse navbar-collapse w-100" id="navbarNav">
            <ul class="navbar-nav align-items-lg-center w-100">
                <?php foreach ($cora_nav as $_item): ?>
                <?php if (empty($cora_gate_ok[$_item['gate']])) { continue; } ?>
                <?php if (isset($_item['items'])): ?>
                <?php
                    $_children = array_values(array_filter($_item['items'], static fn (array $c): bool => !isset($c['gate']) || !empty($cora_gate_ok[$c['gate']])));
                    $_group_active = in_array($current_dir, array_column($_children, 'folder'), true) ? 'active' : '';
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo $_group_active; ?>" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false"><?php echo htmlspecialchars($_item['label']); ?></a>
                    <ul class="dropdown-menu">
                        <?php foreach ($_children as $_c): ?>
                        <?php
                            $_child_files  = array_merge([$_c['file']], $_c['files'] ?? []);
                            $_child_active = ($current_dir === $_c['folder'] && in_array($current_script, $_child_files, true)) ? 'active' : '';
                        ?>
                        <li>
                            <a class="dropdown-item <?php echo $_child_active; ?>"
                                href="<?php echo CORA_BASE . $_c['folder'] . '/' . $_c['file']; ?>"><?php echo htmlspecialchars($_c['label']); ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php else: ?>
                <?php
                    $_href_folder   = strstr($_item['href'], '/', true) ?: $_item['href'];
                    $_single_active = $current_dir === $_href_folder ? 'active' : '';
                ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_single_active; ?>"
                        href="<?php echo CORA_BASE . $_item['href']; ?>"><?php echo htmlspecialchars($_item['label']); ?></a>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>

                <li class="nav-item dropdown cora-notif-dropdown ms-lg-auto">
                    <a class="nav-link position-relative" href="#" id="coraNotifBell" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <span id="cora-notif-badge" class="cora-notif-badge cora-hidden">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end cora-notif-menu" aria-labelledby="coraNotifBell">
                        <div class="cora-notif-menu-header">
                            <span>Notifications</span>
                            <span class="text-muted small"><?php
                                echo htmlspecialchars([1 => 'Super Admin', 2 => 'Corporate Admin', 3 => 'Outlet PIC', 4 => 'Outlet User'][$cora_permission] ?? '');
                            ?></span>
                        </div>
                        <div id="cora-notif-list" class="cora-notif-list">
                            <div class="cora-notif-empty">Loading...</div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>
