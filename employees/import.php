<?php
$page_title = 'Upload Employees';
$page_subtitle = 'Bulk CSV / XLSX upload of employee eligibility. The file is validated first; nothing changes in the Employee Master until you confirm. Existing employees (same company and IC / passport) are updated.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'employees/import.js';
require __DIR__ . '/../app/bootstrap.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="cora-card mb-3">
            <h6 class="cora-card-title"><i class="bi bi-1-circle"></i> Choose file</h6>
            <form id="cora-imp-form" class="row g-2 align-items-end mt-1">
                <div class="col-md-8">
                    <label class="form-label" for="cora-imp-file">CSV or XLSX (max 10 MB, first sheet only)</label>
                    <input type="file" class="form-control form-control-sm" id="cora-imp-file" accept=".csv,.xlsx,.xls,.txt" required>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1" id="cora-imp-validate"><i class="bi bi-check2-square"></i> Validate</button>
                </div>
            </form>
            <div id="cora-imp-alert" class="alert alert-danger py-2 px-3 small mt-3 mb-0" hidden></div>
        </div>

        <div class="cora-card mb-3" id="cora-imp-result" hidden>
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <h6 class="cora-card-title mb-0"><i class="bi bi-2-circle"></i> Validation result <span class="text-muted small" id="cora-imp-file-name"></span></h6>
                <div class="d-flex gap-2" id="cora-imp-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cora-imp-discard">Discard</button>
                    <button type="button" class="btn btn-success btn-sm" id="cora-imp-confirm"><i class="bi bi-cloud-check"></i> Confirm upload</button>
                </div>
            </div>
            <p class="small mt-2 mb-2" id="cora-imp-headline"></p>
            <ul class="list-unstyled small mb-3" id="cora-imp-summary"></ul>
            <div class="d-flex align-items-center gap-2 mb-2">
                <label class="form-label small mb-0" for="cora-imp-filter">Show rows</label>
                <select id="cora-imp-filter" class="form-select form-select-sm" style="max-width:280px;"></select>
            </div>
            <?php cora_table('cora-imp', ['Row', 'Company', 'Employee', 'IC / Passport', 'Limit', 'Start', 'End', 'Result'], false); ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="cora-card mb-3">
            <h6 class="cora-card-title"><i class="bi bi-file-earmark-spreadsheet"></i> Upload template</h6>
            <p class="small mb-2">The first row must be the heading row. Compulsory columns are marked <span class="text-danger">*</span>.</p>
            <ul class="small mb-3">
                <?php foreach (\Cora\Services\Import\EmployeeImportService::COLUMNS as [$heading, $required]): ?>
                <li><?php echo cora_h($heading); ?><?php echo $required ? ' <span class="text-danger">*</span>' : ''; ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="small text-muted mb-2">Dates: YYYY-MM-DD or DD/MM/YYYY. Company: the name or code exactly as in the Company Master. A duplicated IC / passport for the same company marks every copy as duplicate.</p>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo CORA_BASE; ?>employees/api.php?action=import_template"><i class="bi bi-download"></i> Download CSV template</a>
        </div>

        <div class="cora-card">
            <h6 class="cora-card-title"><i class="bi bi-clock-history"></i> Recent uploads</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle cora-view-tbl mb-0">
                    <thead><tr><th>File</th><th>Rows</th><th>Status</th><th>By</th></tr></thead>
                    <tbody id="cora-imp-recent"><tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../footer.php';
