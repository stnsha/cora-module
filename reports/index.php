<?php
$page_title = 'Monthly Claim Report';
$page_subtitle = 'Claims by visitation month. Filter by company, outlet, employee, claim status or payment method, then export to Excel or CSV.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
$page_title_actions = '<div class="cora-rp-export">'
    . '<div class="d-flex gap-2">'
    . '<button type="button" class="btn btn-outline-primary d-inline-flex align-items-center" id="cora-rp-csv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>'
    . '<button type="button" class="btn btn-primary d-inline-flex align-items-center" id="cora-rp-xlsx"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>'
    . '</div>'
    . '<div class="cora-loading-bar" id="cora-rp-loading" hidden><span></span></div>'
    . '</div>';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'reports/reports.js';
require __DIR__ . '/../app/bootstrap.php';
$cora_outlet_list = \Cora\Models\Outlet::all();
?>
<div class="cora-card cora-filter mb-3">
    <h6 class="cora-card-title"><i class="bi bi-funnel"></i> Filter</h6>
    <div class="row g-2 mt-1 align-items-end">
        <div class="col-md-2 col-sm-6">
            <label class="form-label" for="cora-rp-month">Month</label>
            <input type="month" id="cora-rp-month" class="form-control form-control-sm" value="<?php echo date('Y-m'); ?>">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rp-company">Company</label>
            <select id="cora-rp-company" class="form-select form-select-sm"><option value="">All companies</option></select>
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rp-outlet">Outlet</label>
            <select id="cora-rp-outlet" class="form-select form-select-sm">
                <option value="">All outlets</option>
                <?php foreach ($cora_outlet_list as $o): ?>
                <option value="<?php echo (int) $o['id']; ?>"><?php echo cora_h($o['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-sm-6">
            <label class="form-label" for="cora-rp-q">Employee</label>
            <input type="search" id="cora-rp-q" class="form-control form-control-sm" placeholder="Name, IC / passport, employee ID, SO">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rp-status">Claim Status</label>
            <select id="cora-rp-status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach (\Cora\Models\Claim::STATUSES as $v => $label): ?>
                <option value="<?php echo $v; ?>"><?php echo cora_h($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-rp-method">Payment Method</label>
            <select id="cora-rp-method" class="form-select form-select-sm">
                <option value="">All methods</option>
                <?php foreach (\Cora\Models\Claim::PAYMENT_METHODS as $v => $label): ?>
                <option value="<?php echo $v; ?>"><?php echo cora_h($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="cora-rp-run"><i class="bi bi-search"></i> Apply</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cora-rp-reset">Reset</button>
        </div>
    </div>
</div>

<div id="cora-rp-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<p class="small text-muted mb-2" id="cora-rp-totals"></p>
<?php cora_table('cora-rp', ['Employee Name', 'IC / Employee ID', 'Company', 'Outlet', 'Visit Date', 'SO No.', 'Claim Amount', 'Payment', 'Status', 'Approval Date', 'Approved By', 'Rejection Remark'], false); ?>
<?php
include __DIR__ . '/../footer.php';
