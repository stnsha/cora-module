<?php
$page_title = 'Dashboard';
$page_subtitle = 'Claims waiting for action and monthly utilisation per company (by visitation date).';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'dashboard/dashboard.js';

// Notification centre cards: key => [title, value colour, label, link].
$cora_queue_cards = [
    'pending'         => ['Pending Review', 'cora-stat-value--orange', 'claims in the queue', 'review/index.php'],
    'new_today'       => ['New Today', 'cora-stat-value--blue', 'submitted today', 'review/index.php?today=1'],
    'resubmitted'     => ['Resubmitted', 'cora-stat-value--blue', 'returned claims resubmitted', 'review/index.php?resubmitted=1'],
    'awaiting_outlet' => ['Awaiting Outlet', 'cora-stat-value--red', 'returned for amendment', 'claims/index.php?status=7'],
    'uploads'         => ['Uploads to Confirm', 'cora-stat-value--red', 'employee uploads', 'employees/import.php'],
];

// Company dashboard cards: key => [title, value colour, label].
$cora_kpis = [
    'enrolled'         => ['Employees Enrolled', 'cora-stat-value--blue', 'entitlement covers the month'],
    'utilised'         => ['Employees Utilised', 'cora-stat-value--blue', 'with an approved claim'],
    'claims'           => ['Claims', 'cora-stat-value--blue', 'submitted (not draft / cancelled)'],
    'approved'         => ['Approved Claims', 'cora-stat-value--green', 'approved utilisation'],
    'pending'          => ['Pending Claims', 'cora-stat-value--orange', 'reserved'],
    'rejected'         => ['Rejected Claims', 'cora-stat-value--red', 'released'],
    'average'          => ['Average Claim', 'cora-stat-value--blue', 'per approved claim'],
    'utilisation_rate' => ['Utilisation Rate', 'cora-stat-value--green', 'of enrolled employees'],
];
?>
<div id="cora-dash-alert" class="alert alert-danger py-2 px-3 small" hidden></div>

<div class="row g-3 mb-4">
    <?php foreach ($cora_queue_cards as $key => [$title, $cls, $label, $href]): ?>
    <div class="col-12 col-sm-6 col-xl">
        <a class="text-decoration-none text-reset" href="<?php echo CORA_BASE . $href; ?>">
            <div class="cora-card cora-dash-stat h-100">
                <div class="cora-card-title mb-1"><?php echo cora_h($title); ?></div>
                <div class="cora-stat-value <?php echo $cls; ?>" id="cora-dash-q-<?php echo $key; ?>">---</div>
                <div class="cora-stat-label"><?php echo cora_h($label); ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
    <h2 class="h5 mb-0" id="cora-dash-heading">Company Dashboard</h2>
    <div class="d-flex align-items-end gap-2 cora-filter">
        <div>
            <label class="form-label small mb-1" for="cora-dash-company">Company</label>
            <select id="cora-dash-company" class="form-select form-select-sm"><option value="">All companies</option></select>
        </div>
        <div>
            <label class="form-label small mb-1" for="cora-dash-month">Month</label>
            <input type="month" id="cora-dash-month" class="form-control form-control-sm" value="<?php echo date('Y-m'); ?>">
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($cora_kpis as $key => [$title, $cls, $label]): ?>
    <div class="col-6 col-md-3">
        <div class="cora-card cora-dash-stat h-100">
            <div class="cora-card-title mb-1"><?php echo cora_h($title); ?></div>
            <div class="cora-stat-value <?php echo $cls; ?>" id="cora-dash-k-<?php echo $key; ?>">---</div>
            <div class="cora-stat-label"><?php echo cora_h($label); ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="alert alert-info py-2 px-3 small mb-4" id="cora-dash-hr" hidden></div>

<h6 class="mb-2">By Company</h6>
<?php cora_table('cora-dash', ['Company', 'Enrolled', 'Utilised', 'Utilisation', 'Claims', 'Approved', 'Pending', 'Rejected', 'Average'], false); ?>
<?php
include __DIR__ . '/../footer.php';
