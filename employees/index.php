<?php
$page_title = 'Employee Master';
$page_subtitle = 'Eligible employees per company. Edits and entitlement adjustments are audited; approved claims keep the values they were approved with.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
$page_title_actions = static fn (): string => '<a class="btn btn-outline-primary d-inline-flex align-items-center me-2" href="'
    . CORA_BASE . 'employees/import.php"><i class="bi bi-upload me-1"></i>Upload employees</a>'
    . '<a class="btn btn-primary d-inline-flex align-items-center" id="cora-emp-add" href="' . CORA_BASE . 'employees/edit.php"><i class="bi bi-plus-lg me-1"></i>Add employee</a>';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'employees/employees.js';
?>
<div class="cora-card cora-filter mb-3">
    <h6 class="cora-card-title"><i class="bi bi-funnel"></i> Filter</h6>
    <div class="row g-2 mt-1 align-items-end">
        <div class="col-md-4 col-sm-6">
            <label class="form-label" for="cora-emp-q">Search</label>
            <input type="search" id="cora-emp-q" class="form-control form-control-sm" placeholder="IC / passport, name, phone or employee ID">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-emp-company">Company</label>
            <select id="cora-emp-company" class="form-select form-select-sm"><option value="">All companies</option></select>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-emp-status">Status</label>
            <select id="cora-emp-status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="1">Active</option>
                <option value="2">Inactive</option>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-emp-reset">Reset</button>
        </div>
    </div>
</div>

<div id="cora-emp-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php cora_table('cora-emp', ['Employee', 'IC / Passport', 'Employee ID', 'Company', 'Period', 'Limit', 'Available', 'Eligibility']); ?>

<?php
include __DIR__ . '/../footer.php';
