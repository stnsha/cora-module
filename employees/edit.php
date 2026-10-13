<?php
/**
 * Add / edit employee page (no modal).
 *   employees/edit.php                 add an employee
 *   employees/edit.php?company_id=N    add, company preselected (from a filtered list)
 *   employees/edit.php?id=N            edit an employee
 */
$cora_employee_id = (int) ($_GET['id'] ?? 0);
$cora_company_id = (int) ($_GET['company_id'] ?? 0);
$page_title = $cora_employee_id > 0 ? 'Edit Employee' : 'Add Employee';
$page_subtitle = 'Edits and entitlement adjustments are audited; approved claims keep the values they were approved with.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
$page_title_actions = static fn (): string => '<a class="btn btn-outline-secondary d-inline-flex align-items-center" href="'
    . CORA_BASE . 'employees/index.php"><i class="bi bi-arrow-left me-1"></i>Employee Master</a>';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'employees/edit.js';
?>
<div id="cora-emp-alert" class="alert alert-danger py-2 px-3 small" hidden></div>

<form id="cora-emp-form" novalidate>
    <input type="hidden" id="cora-emp-id" value="<?php echo $cora_employee_id ?: ''; ?>">
    <input type="hidden" id="cora-emp-preset-company" value="<?php echo $cora_company_id ?: ''; ?>">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="cora-card">
                <h6 class="cora-card-title"><i class="bi bi-person-vcard"></i> Employee details</h6>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label" for="cora-emp-company-f">Company <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select" id="cora-emp-company-f" required><option value="">Choose a company</option></select>
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
            </div>
        </div>
        <div class="col-lg-5">
            <div class="cora-card">
                <h6 class="cora-card-title"><i class="bi bi-shield-exclamation"></i> Restriction / Override</h6>
                <p class="text-muted small mt-2">Operational instructions are set here, not in the free-text remark. Hierarchy: individual employee rule, then company rule, then Default Alpro rule.</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="cora-emp-restriction">Type</label>
                        <select class="form-select" id="cora-emp-restriction">
                            <option value="0">None</option>
                            <option value="1">Entitlement limit override</option>
                            <option value="2">Entitlement suspended</option>
                        </select>
                    </div>
                    <div class="col-md-6" data-override>
                        <label class="form-label" for="cora-emp-override">Override Limit (RM)</label>
                        <input type="number" class="form-control" id="cora-emp-override" min="0" step="0.01">
                    </div>
                    <div class="col-md-6" data-override>
                        <label class="form-label" for="cora-emp-override-until">Override Until</label>
                        <input type="date" class="form-control" id="cora-emp-override-until">
                    </div>
                    <div class="col-12" data-restriction>
                        <label class="form-label" for="cora-emp-restriction-remark">Reason (shown to outlets) <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea class="form-control" id="cora-emp-restriction-remark" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="cora-emp-error" class="text-danger small mt-3" hidden></div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-light btn-sm" href="<?php echo CORA_BASE; ?>employees/index.php">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </div>
</form>
<?php
include __DIR__ . '/../footer.php';
