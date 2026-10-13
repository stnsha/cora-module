<?php
$page_title = 'Claim';
$page_access = 'outlet';
$page_hide_title = true;
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'claims/view.js';
$cora_claim_id = (int) ($_GET['id'] ?? 0);
$cora_review = ($_GET['review'] ?? '') === '1' && $cora_is_admin;
?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <a href="<?php echo CORA_BASE . ($cora_review ? 'review/index.php' : 'claims/index.php'); ?>" class="small text-decoration-none">
            <i class="bi bi-arrow-left"></i> <?php echo $cora_review ? 'Claim Review queue' : 'Claim History'; ?>
        </a>
        <h1 class="cora-page-title mb-0 mt-1">Claim <span id="cora-cv-no"></span> <span id="cora-cv-status"></span></h1>
        <p class="text-muted small mb-0 mt-1" id="cora-cv-sub"></p>
    </div>
    <div class="d-flex flex-wrap gap-2" id="cora-cv-actions">
        <button type="button" class="btn btn-success btn-sm" data-act="approve" hidden><i class="bi bi-check2"></i> Approve</button>
        <button type="button" class="btn btn-outline-danger btn-sm" data-act="reject" hidden><i class="bi bi-x-lg"></i> Reject</button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-act="return" hidden><i class="bi bi-arrow-return-left"></i> Return for Amendment</button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-act="edit" hidden><i class="bi bi-pencil"></i> <span>Edit</span></button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-act="cancel" hidden><i class="bi bi-slash-circle"></i> <span>Cancel Claim</span></button>
    </div>
</div>

<div id="cora-cv-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php if (($_GET['saved'] ?? '') === 'submitted'): ?>
<div class="alert alert-success py-2 px-3 small">Claim submitted for Admin review. The corporate claim amount is reserved from the employee's available balance.</div>
<?php elseif (($_GET['saved'] ?? '') === 'draft'): ?>
<div class="alert alert-success py-2 px-3 small">Draft saved. Use Edit / Submit when ready - a draft does not reserve any balance.</div>
<?php endif; ?>
<div id="cora-cv-flags" class="alert alert-warning py-2 px-3 small" hidden></div>
<div id="cora-cv-decision" class="alert py-2 px-3 small" hidden></div>

<div id="cora-cv-body" hidden>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="cora-card h-100">
                <h6 class="cora-card-title"><i class="bi bi-receipt"></i> Transaction</h6>
                <table class="table table-sm mb-0 cora-kv-tbl"><tbody id="cora-cv-txn"></tbody></table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="cora-card h-100">
                <h6 class="cora-card-title"><i class="bi bi-person-badge"></i> Employee</h6>
                <div id="cora-cv-restriction" class="alert alert-danger py-2 px-3 small" hidden></div>
                <div id="cora-cv-info" class="alert alert-info py-2 px-3 small" hidden></div>
                <table class="table table-sm mb-0 cora-kv-tbl"><tbody id="cora-cv-emp"></tbody></table>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Limit</div>
                <div class="cora-stat-value cora-stat-value--blue" id="cora-cv-limit">-</div>
                <div class="cora-stat-label">current entitlement</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Approved</div>
                <div class="cora-stat-value cora-stat-value--green" id="cora-cv-approved">-</div>
                <div class="cora-stat-label">this period</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Pending</div>
                <div class="cora-stat-value cora-stat-value--orange" id="cora-cv-pending">-</div>
                <div class="cora-stat-label">reserved, incl. this claim</div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1">Current Remaining</div>
                <div class="cora-stat-value cora-stat-value--blue" id="cora-cv-available">-</div>
                <div class="cora-stat-label" id="cora-cv-snapshot">-</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="cora-card h-100 cora-rules">
                <h6 class="cora-card-title"><i class="bi bi-card-checklist"></i> Company Entitlement Rules</h6>
                <div class="small mb-2" id="cora-cv-category"></div>
                <div class="small" id="cora-cv-criteria"></div>
                <hr class="my-2">
                <div class="small" id="cora-cv-remark"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="cora-card h-100">
                <h6 class="cora-card-title"><i class="bi bi-clock-history"></i> Timeline</h6>
                <ul class="list-unstyled small mb-0" id="cora-cv-timeline"></ul>
            </div>
        </div>
    </div>

    <h6 class="mb-2">Previous Claim History</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle cora-view-tbl">
            <thead>
                <tr>
                    <th style="width:3rem;">#</th>
                    <th>Claim No.</th>
                    <th>Visit Date</th>
                    <th>Outlet</th>
                    <th>SO No.</th>
                    <th class="text-end">Corporate Claim</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="cora-cv-history"></tbody>
        </table>
    </div>
</div>
<script>
window.CORA_CLAIM_ID = <?php echo json_encode($cora_claim_id); ?>;
window.CORA_CLAIM_REVIEW = <?php echo json_encode($cora_review); ?>;
</script>
<?php
include __DIR__ . '/../footer.php';
