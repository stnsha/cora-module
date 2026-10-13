<?php
$page_title = 'Audit Log';
$page_subtitle = 'Every edit, approval, rejection, entitlement adjustment, upload and access change, with the staff member and date/time.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'audit/audit.js';
?>
<div class="cora-card cora-filter mb-3">
    <h6 class="cora-card-title"><i class="bi bi-funnel"></i> Filter</h6>
    <div class="row g-2 mt-1 align-items-end">
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-au-entity">Record</label>
            <select id="cora-au-entity" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="claim">Claim</option>
                <option value="employee">Employee</option>
                <option value="company">Company</option>
                <option value="import">Employee upload</option>
                <option value="setting">Setting</option>
                <option value="user">User access</option>
            </select>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-au-id">Record ID</label>
            <input type="number" id="cora-au-id" class="form-control form-control-sm" min="1">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-au-q">Search</label>
            <input type="search" id="cora-au-q" class="form-control form-control-sm" placeholder="Staff, action or remark">
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-au-from">From</label>
            <input type="date" id="cora-au-from" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-au-to">To</label>
            <input type="date" id="cora-au-to" class="form-control form-control-sm">
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-au-reset">Reset</button>
        </div>
    </div>
</div>

<div id="cora-au-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php cora_table('cora-au', ['Date / Time', 'Staff', 'Record', 'Action', 'Remark', 'Changes']); ?>

<div class="modal fade" id="cora-au-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cora-au-modal-title">Changes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="cora-au-modal-meta"></p>
                <div class="table-responsive">
                    <table class="table table-sm cora-view-tbl small">
                        <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                        <tbody id="cora-au-changes"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../footer.php';
