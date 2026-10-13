<?php
/**
 * Create / edit claim page (no modal).
 *   claims/create.php                  find the employee, then fill the claim
 *   claims/create.php?employee_id=N    employee preselected (from the Eligibility Checker)
 *   claims/create.php?id=N             edit a draft / amend a submitted or returned claim
 */
$cora_edit_id = (int) ($_GET['id'] ?? 0);
$cora_employee_id = (int) ($_GET['employee_id'] ?? 0);
$page_title = $cora_edit_id > 0 ? 'Edit Claim' : 'Create Claim';
$page_subtitle = $cora_edit_id > 0
    ? 'Correct the claim and submit it again. The corporate claim amount stays reserved from the balance until an admin decides.'
    : 'Find the employee, check the eligibility, then record the visit and Sales Order. Submitting reserves the corporate claim amount from the available balance.';
$page_access = 'outlet';
require __DIR__ . '/../partials.php';
$page_title_actions = static fn (): string => '<a class="btn btn-outline-secondary d-inline-flex align-items-center" href="'
    . CORA_BASE . 'claims/index.php"><i class="bi bi-arrow-left me-1"></i>Claim History</a>';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'claims/create.js';
?>
<div id="cora-cc-alert" class="alert alert-danger py-2 px-3 small" hidden></div>

<!-- Step 1: employee -->
<div id="cora-cc-find" class="cora-card cora-filter mb-3" hidden>
    <h6 class="cora-card-title"><i class="bi bi-1-circle"></i> Find Employee</h6>
    <form id="cora-cc-search" class="row g-2 mt-1 align-items-end" autocomplete="off">
        <div class="col-md-6">
            <label class="form-label" for="cora-cc-q">IC / Passport, Name, Phone or Employee ID</label>
            <input type="search" id="cora-cc-q" class="form-control form-control-sm" minlength="3" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cora-cc-company">Company</label>
            <select id="cora-cc-company" class="form-select form-select-sm"><option value="">All companies</option></select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Search</button>
        </div>
    </form>
    <div id="cora-cc-results" class="mt-3" hidden>
        <div class="table-responsive">
            <table class="table table-hover align-middle cora-view-tbl mb-0">
                <thead>
                    <tr>
                        <th style="width:3rem;">#</th>
                        <th>Employee</th>
                        <th>IC / Passport</th>
                        <th>Employee ID</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Available</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="cora-cc-rows"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Selected employee: eligibility summary -->
<div id="cora-cc-employee" class="mb-4" hidden>
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1" id="cora-cc-emp-name"></h2>
            <div class="text-muted small" id="cora-cc-emp-meta"></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="cora-cc-emp-status"></span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="cora-cc-change" hidden>Change employee</button>
        </div>
    </div>
    <div id="cora-cc-restriction" class="alert alert-danger py-2 px-3 mb-3" hidden></div>
    <div id="cora-cc-info" class="alert alert-info py-2 px-3 small mb-3" hidden></div>
    <div id="cora-cc-return-note" class="alert alert-warning py-2 px-3 small mb-3" hidden></div>
    <div class="row g-3">
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Annual Limit</div>
                <div class="cora-stat-value cora-stat-value--blue" id="cora-cc-limit">-</div>
                <div class="cora-stat-label" id="cora-cc-period">-</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Approved Utilisation</div>
                <div class="cora-stat-value cora-stat-value--green" id="cora-cc-approved">-</div>
                <div class="cora-stat-label">approved by Admin</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Pending Claims</div>
                <div class="cora-stat-value cora-stat-value--orange" id="cora-cc-pending">-</div>
                <div class="cora-stat-label" id="cora-cc-pending-label">submitted, not yet approved</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Available Balance</div>
                <div class="cora-stat-value cora-stat-value--blue" id="cora-cc-available">-</div>
                <div class="cora-stat-label">for this claim</div>
            </div>
        </div>
        <div class="col-12">
            <div class="cora-card">
                <h6 class="cora-card-title"><i class="bi bi-card-checklist"></i> Benefit / Claim Criteria</h6>
                <div class="small" id="cora-cc-criteria"></div>
                <div class="small mt-2" id="cora-cc-remark"></div>
            </div>
        </div>
    </div>
</div>

<!-- Step 2: claim details -->
<form id="cora-cc-form" novalidate hidden>
    <h6 class="mb-3"><i class="bi bi-2-circle"></i> Claim Details</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="cora-cc-outlet">Outlet <span class="text-danger" aria-hidden="true">*</span></label>
            <select class="form-select" id="cora-cc-outlet" required></select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cora-cc-visit">Visitation Date <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="date" class="form-control" id="cora-cc-visit" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cora-cc-so">Sales Order Number <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="text" class="form-control" id="cora-cc-so" maxlength="50" required autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="cora-cc-total">Purchase Amount (RM) <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="number" class="form-control" id="cora-cc-total" min="0.01" step="0.01" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="cora-cc-method">Payment Method <span class="text-danger" aria-hidden="true">*</span></label>
            <select class="form-select" id="cora-cc-method" required></select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="cora-cc-corporate">Corporate Claim (RM)</label>
            <input type="number" class="form-control" id="cora-cc-corporate" min="0" step="0.01">
            <div class="form-text" id="cora-cc-selfpay">Self-pay: RM0.00</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="cora-cc-type">Claim Type</label>
            <select class="form-select" id="cora-cc-type"></select>
        </div>
        <div class="col-12" id="cora-cc-suggest" hidden>
            <div class="alert alert-warning py-2 px-3 small mb-0 d-flex align-items-center justify-content-between gap-2">
                <span id="cora-cc-suggest-text"></span>
                <button type="button" class="btn btn-sm btn-outline-dark" id="cora-cc-suggest-btn">Use Mixed Payment</button>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="cora-cc-remark-f">Remark</label>
            <textarea class="form-control" id="cora-cc-remark-f" rows="2" maxlength="2000"></textarea>
        </div>
        <div class="col-12" id="cora-cc-dup-wrap" hidden>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cora-cc-dup">
                <label class="form-check-label" for="cora-cc-dup">Authorise a duplicate Sales Order number (admin)</label>
            </div>
        </div>
    </div>
    <div id="cora-cc-error" class="alert alert-danger py-2 px-3 small mt-3" hidden></div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-light btn-sm" href="<?php echo CORA_BASE; ?>claims/index.php">Cancel</a>
        <button type="button" class="btn btn-outline-primary btn-sm" id="cora-cc-draft">Save Draft</button>
        <button type="submit" class="btn btn-primary btn-sm" id="cora-cc-submit">Submit Claim</button>
    </div>
</form>
<script>
window.CORA_CC = <?php echo json_encode(['id' => $cora_edit_id, 'employee_id' => $cora_employee_id]); ?>;
</script>
<?php
include __DIR__ . '/../footer.php';
