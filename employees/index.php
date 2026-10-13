<?php
$page_title = 'Employee Master';
$page_subtitle = 'Eligible employees per company. Edits and entitlement adjustments are audited; approved claims keep the values they were approved with.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
$page_title_actions = static fn (): string => '<a class="btn btn-outline-primary d-inline-flex align-items-center me-2" href="'
    . CORA_BASE . 'employees/import.php"><i class="bi bi-upload me-1"></i>Upload employees</a>'
    . cora_title_button('Add employee', 'cora-emp-add');
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

<div class="modal fade" id="cora-emp-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="cora-emp-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="cora-emp-title">Add employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cora-emp-id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cora-emp-company-f">Company <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select" id="cora-emp-company-f" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cora-emp-status-f">Status <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select" id="cora-emp-status-f">
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cora-emp-name">Employee Name <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="cora-emp-name" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cora-emp-ic">IC / Passport <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="cora-emp-ic" maxlength="30" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-no">Employee ID</label>
                            <input type="text" class="form-control" id="cora-emp-no" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-phone">Phone Number</label>
                            <input type="text" class="form-control" id="cora-emp-phone" maxlength="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-dept">Department</label>
                            <input type="text" class="form-control" id="cora-emp-dept" maxlength="150">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="cora-emp-address">Address</label>
                            <textarea class="form-control" id="cora-emp-address" rows="2" maxlength="1000"></textarea>
                            <div class="form-text">Never shown to outlet users.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-limit">Entitlement Limit (RM) <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="cora-emp-limit" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-start">Entitlement Start <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="date" class="form-control" id="cora-emp-start" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-end">Entitlement End <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="date" class="form-control" id="cora-emp-end" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="cora-emp-info">Information Remark</label>
                            <textarea class="form-control" id="cora-emp-info" rows="2" maxlength="2000" placeholder="Shown to outlets as the Special Employee Remark"></textarea>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-2">Restriction / Override</h6>
                    <p class="text-muted small">Operational instructions are set here, not in the free-text remark. Hierarchy: individual employee rule, then company rule, then Default Alpro rule.</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="cora-emp-restriction">Type</label>
                            <select class="form-select" id="cora-emp-restriction">
                                <option value="0">None</option>
                                <option value="1">Entitlement limit override</option>
                                <option value="2">Entitlement suspended</option>
                            </select>
                        </div>
                        <div class="col-md-4" data-override>
                            <label class="form-label" for="cora-emp-override">Override Limit (RM)</label>
                            <input type="number" class="form-control" id="cora-emp-override" min="0" step="0.01">
                        </div>
                        <div class="col-md-4" data-override>
                            <label class="form-label" for="cora-emp-override-until">Override Until</label>
                            <input type="date" class="form-control" id="cora-emp-override-until">
                        </div>
                        <div class="col-12" data-restriction>
                            <label class="form-label" for="cora-emp-restriction-remark">Reason (shown to outlets) <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea class="form-control" id="cora-emp-restriction-remark" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div id="cora-emp-error" class="text-danger small mt-3" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../footer.php';
