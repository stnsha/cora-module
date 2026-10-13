<?php
$page_title = 'Company Master';
$page_subtitle = 'Corporate clients administered directly by Alpro. Link each company to the Corporate Client List: category, benefit criteria and general remark left empty here are inherited from it, so the two never carry conflicting instructions.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
// Only a Super Admin configures companies; Corporate Admins view them.
$page_title_actions = static fn (int $role): string => $role === 1
    ? '<a class="btn btn-primary d-inline-flex align-items-center" href="' . CORA_BASE . 'companies/edit.php"><i class="bi bi-plus-lg me-1"></i>Add company</a>'
    : '';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'companies/companies.js';
?>
<div class="cora-card cora-filter mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4 col-sm-6">
            <label class="form-label" for="cora-co-q">Search</label>
            <input type="search" id="cora-co-q" class="form-control form-control-sm" placeholder="Company name or code">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-co-status">Status</label>
            <select id="cora-co-status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="1">Active</option>
                <option value="2">Inactive</option>
            </select>
        </div>
    </div>
</div>

<div id="cora-co-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php cora_table('cora-co', ['Company', 'Code', 'Corporate Client List', 'Default Limit', 'Over-limit', 'Employees', 'Status']); ?>

<script>window.CORA_CAN_EDIT = <?php echo json_encode($cora_is_super); ?>;</script>
<?php
include __DIR__ . '/../footer.php';
