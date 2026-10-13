<?php
$page_title = 'Claim History';
$page_subtitle = 'Historical and pending claims. Outlet users see their own outlet; admins see every outlet. Drafts and claims returned for amendment are completed from here.';
$page_access = 'outlet';
require __DIR__ . '/../partials.php';
$page_title_actions = static fn (): string => '<a class="btn btn-primary d-inline-flex align-items-center" href="'
    . CORA_BASE . 'claims/create.php"><i class="bi bi-plus-lg me-1"></i>Create claim</a>';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'claims/claims.js';

// Status filter: '' = all, then Models\Claim::STATUSES in workflow order.
$cora_status_filter = [
    ''    => 'All statuses',
    '1'   => 'Draft',
    '2,3' => 'Submitted / Pending Review',
    '7'   => 'Amendment Required',
    '4'   => 'Approved',
    '5'   => 'Rejected',
    '6'   => 'Cancelled / Void',
];
?>
<div class="cora-card cora-filter mb-3">
    <h6 class="cora-card-title"><i class="bi bi-funnel"></i> Filter</h6>
    <div class="row g-2 mt-1 align-items-end">
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-claims-q">Search</label>
            <input type="search" id="cora-claims-q" class="form-control form-control-sm" placeholder="Employee, IC, SO or claim no.">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-claims-status">Status</label>
            <select id="cora-claims-status" class="form-select form-select-sm">
                <?php foreach ($cora_status_filter as $key => $label): ?>
                <option value="<?php echo cora_h($key); ?>" data-label="<?php echo cora_h($label); ?>"><?php echo cora_h($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-claims-month">Visit Month</label>
            <input type="month" id="cora-claims-month" class="form-control form-control-sm">
        </div>
        <?php if ($cora_is_admin): ?>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-claims-company">Company</label>
            <select id="cora-claims-company" class="form-select form-select-sm"><option value="">All companies</option></select>
        </div>
        <?php endif; ?>
        <div class="col-auto d-flex align-items-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-claims-reset">Reset</button>
        </div>
    </div>
</div>

<div id="cora-claims-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php cora_table('cora-claims', ['Claim No.', 'Visit Date', 'Employee', 'Company', 'Outlet', 'SO No.', 'Purchase', 'Corporate', 'Payment', 'Status']); ?>
<?php
include __DIR__ . '/../footer.php';
