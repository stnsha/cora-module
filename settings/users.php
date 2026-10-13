<?php
$page_title = 'Users & permissions';
$page_subtitle = 'CORA roles on odb staff accounts. Outlet roles act for the outlet(s) on the staff account (staff.outlet).';
$page_access = 'super';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';

// Same rule as settings/api.php users_*: the real Super Admin only, never a
// dev-simulated role.
if (empty($_is_superadmin)) {
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Location: ' . CORA_BASE . 'settings/index.php');
    exit;
}
$page_js = CORA_BASE . 'settings/users.js';
require __DIR__ . '/../app/bootstrap.php';
$cora_roles = \Cora\Core\Role::map();
$cora_role_help = [
    1 => 'Company configuration, limits, rules, user access, audit',
    2 => 'Employee upload, claim review, reports',
    3 => 'Outlet User + amend / cancel outlet submissions',
    4 => 'Search eligibility, submit and view own outlet claims',
];
?>
<!-- Markup, classes and font sizes follow atem/access_control/index.php (as edm/settings/users.php). -->
<div class="row g-4 cora-users-page">

    <!-- Left: staff with CORA access -->
    <div class="col-md-7">
        <div class="cora-card cora-filter mb-3">
            <h6 class="cora-card-title"><i class="bi bi-funnel"></i> Filter</h6>
            <div class="row g-2 mt-1 align-items-end">
                <div class="col-md-4 col-sm-6">
                    <label class="form-label" for="cora-users-filter-name">Staff Name</label>
                    <input type="text" id="cora-users-filter-name" class="form-control form-control-sm" placeholder="Search name...">
                </div>
                <div class="col-md-4 col-sm-6">
                    <label class="form-label" for="cora-users-filter-role">Role</label>
                    <select id="cora-users-filter-role" class="form-select form-select-sm">
                        <option value="0">All Role</option>
                        <?php foreach ($cora_roles as $v => $r): ?>
                        <option value="<?php echo $v; ?>"><?php echo cora_h($r['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-users-filter-reset">Reset</button>
                </div>
            </div>
        </div>

        <div id="cora-users-alert" class="alert alert-danger py-2 px-3 small" hidden></div>

        <div>
            <div class="table-responsive">
                <table class="table table-hover align-middle cora-view-tbl mb-0">
                    <thead>
                        <tr>
                            <th style="width:3rem;">#</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="cora-users-rows">
                        <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="cora-pager" id="cora-users-pager" style="margin-top:12px;padding-top:12px;border-top:1px solid #e9ecef;"></div>
        </div>
    </div>

    <!-- Right: add / update access -->
    <div class="col-md-5">
        <div>
            <p class="mb-3 text-muted cora-users-panel-label" id="cora-users-panel-title">Add / Update CORA Access</p>

            <div id="cora-users-form-alert" class="alert alert-dismissible fade show mb-3" role="alert" hidden>
                <span id="cora-users-form-alert-msg"></span>
                <button type="button" class="btn-close" id="cora-users-form-alert-close" aria-label="Close"></button>
            </div>

            <div class="mb-3 cora-users-search">
                <label class="form-label" for="cora-users-search">Staff Name</label>
                <input type="search" class="form-control" id="cora-users-search" placeholder="Type staff name or username to search..." autocomplete="off"
                       role="combobox" aria-expanded="false" aria-controls="cora-users-results" aria-autocomplete="list">
                <ul class="cora-users-results" id="cora-users-results" role="listbox" hidden></ul>
            </div>

            <div class="cora-users-info mb-3" id="cora-users-info" hidden>
                <p><strong>Name:</strong> <span id="cora-users-info-name"></span></p>
                <p><strong>Department:</strong> <span id="cora-users-info-dept"></span></p>
                <p><strong>Status:</strong> <span id="cora-users-info-status"></span></p>
                <p><strong>Current Role:</strong> <span id="cora-users-info-role"></span></p>
            </div>

            <div class="mb-3 cora-users-roles" id="cora-users-roles" hidden>
                <label class="form-label">Role</label>
                <?php foreach ($cora_roles as $v => $r): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="cora-users-role" id="cora-users-role-<?php echo $v; ?>" value="<?php echo $v; ?>">
                    <label class="form-check-label" for="cora-users-role-<?php echo $v; ?>"><?php echo cora_h($r['label']); ?>
                        <span class="text-muted small d-block"><?php echo cora_h($cora_role_help[$v]); ?></span></label>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="cora-users-actions" hidden>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cora-users-cancel">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="cora-users-save">Add Access</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.CORA_USER_ROLES = <?php echo json_encode($cora_roles); ?>;
window.CORA_USER_SELF = <?php echo json_encode(isset($id_user) ? (int) $id_user : null); ?>;
</script>
<?php
include __DIR__ . '/../footer.php';
