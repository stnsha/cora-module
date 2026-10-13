<?php
$page_title = 'Eligibility Checker';
$page_subtitle = 'Search the employee before the transaction. Check the status, the available balance, the company rules and the individual remark, then create the claim.';
$page_access = 'outlet';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'eligibility/eligibility.js';
?>
<div class="cora-card cora-filter mb-3">
    <h6 class="cora-card-title"><i class="bi bi-search"></i> Search Employee</h6>
    <form id="cora-elig-search" class="row g-2 mt-1 align-items-end" autocomplete="off">
        <div class="col-md-6">
            <label class="form-label" for="cora-elig-q">IC / Passport, Name, Phone or Employee ID</label>
            <input type="search" id="cora-elig-q" class="form-control form-control-sm" placeholder="e.g. 900101145678" minlength="3" required autofocus>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cora-elig-company">Company</label>
            <select id="cora-elig-company" class="form-select form-select-sm">
                <option value="">All companies</option>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Search</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-elig-reset">Reset</button>
        </div>
    </form>
</div>

<div id="cora-elig-alert" class="alert alert-danger py-2 px-3 small" hidden></div>

<div id="cora-elig-results" hidden>
    <?php cora_table('cora-elig', ['Employee', 'IC / Passport', 'Employee ID', 'Company', 'Department', 'Status', 'Available']); ?>
</div>

<!-- Employee Eligibility Card -->
<div id="cora-elig-card" class="mt-4" hidden>
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1" id="cora-card-name"></h2>
            <div class="text-muted small" id="cora-card-meta"></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="cora-card-status"></span>
            <button type="button" class="btn btn-primary btn-sm" id="cora-card-claim"><i class="bi bi-receipt"></i> Create Claim</button>
        </div>
    </div>

    <div id="cora-card-restriction" class="alert alert-danger py-2 px-3 mb-3" hidden></div>
    <div id="cora-card-info" class="alert alert-info py-2 px-3 small mb-3" hidden></div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Annual Limit</div>
                <div class="cora-stat-value cora-stat-value--blue" id="cora-card-limit">-</div>
                <div class="cora-stat-label" id="cora-card-limit-source">entitlement</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Approved Utilisation</div>
                <div class="cora-stat-value cora-stat-value--green" id="cora-card-approved">-</div>
                <div class="cora-stat-label">approved by Admin</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Pending Claims</div>
                <div class="cora-stat-value cora-stat-value--orange" id="cora-card-pending">-</div>
                <div class="cora-stat-label">submitted, not yet approved</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Available Balance</div>
                <div class="cora-stat-value cora-stat-value--blue" id="cora-card-available">-</div>
                <div class="cora-stat-label">limit - approved - pending</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="cora-card h-100">
                <h6 class="cora-card-title"><i class="bi bi-calendar-range"></i> Entitlement</h6>
                <p class="mb-1 small"><strong>Period:</strong> <span id="cora-card-period"></span></p>
                <p class="mb-1 small"><strong>Company category:</strong> <span id="cora-card-category"></span></p>
                <p class="mb-0 small"><strong>Over-limit handling:</strong> <span id="cora-card-limit-mode"></span></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="cora-card h-100">
                <h6 class="cora-card-title"><i class="bi bi-card-checklist"></i> Benefit / Claim Criteria</h6>
                <div class="small" id="cora-card-criteria" style="white-space:normal;"></div>
                <div class="text-muted small mt-1" id="cora-card-criteria-source"></div>
            </div>
        </div>
        <div class="col-12">
            <div class="cora-card">
                <h6 class="cora-card-title"><i class="bi bi-building"></i> Company General Remark</h6>
                <div class="small" id="cora-card-remark"></div>
                <div class="text-muted small mt-1" id="cora-card-remark-source"></div>
            </div>
        </div>
    </div>

    <h6 class="mb-2">Claim History (current entitlement period)</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle cora-view-tbl">
            <thead>
                <tr>
                    <th style="width:3rem;">#</th>
                    <th>Claim No.</th>
                    <th>Visit Date</th>
                    <th>Outlet</th>
                    <th>SO No.</th>
                    <th>Payment</th>
                    <th class="text-end">Corporate Claim</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="cora-card-history"></tbody>
        </table>
    </div>
</div>
<?php
include __DIR__ . '/../footer.php';
