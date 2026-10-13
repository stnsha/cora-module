<?php
$page_title = 'Claim Review';
$page_subtitle = 'One central queue of submitted claims. Open a claim to see the employee, company rules, remarks, history and remaining limit, then Approve, Reject (reason compulsory) or Return for Amendment.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'review/review.js';
?>
<div class="cora-card cora-filter mb-3">
    <h6 class="cora-card-title"><i class="bi bi-funnel"></i> Pending Claims - <?php echo date('j M Y'); ?></h6>
    <div class="row g-2 mt-1 align-items-end">
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rv-q">Search</label>
            <input type="search" id="cora-rv-q" class="form-control form-control-sm" placeholder="Employee, IC, SO or claim no.">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rv-company">Company</label>
            <select id="cora-rv-company" class="form-select form-select-sm"><option value="">All companies</option></select>
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rv-show">Show</label>
            <select id="cora-rv-show" class="form-select form-select-sm">
                <option value="">All pending</option>
                <option value="today">New today</option>
                <option value="resubmitted">Returned claims resubmitted</option>
                <option value="flagged">Flagged only</option>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-rv-reset">Reset</button>
        </div>
    </div>
</div>

<div id="cora-rv-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php cora_table('cora-rv', ['Employee', 'Company', 'Outlet', 'Visit', 'SO No.', 'Amount', 'Remaining', 'Submitted', 'Status']); ?>
<?php
include __DIR__ . '/../footer.php';
