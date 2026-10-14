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
            <div class="cora-card-title-row flex-wrap gap-2">
                <div>
                    <h6 class="cora-card-title"><i class="bi bi-2-circle"></i> Validation result</h6>
                    <div class="cora-imp-file" id="cora-imp-file-name"></div>
                </div>
                <div class="d-flex gap-2" id="cora-imp-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cora-imp-discard">Discard</button>
                    <button type="button" class="btn btn-success btn-sm" id="cora-imp-confirm"><i class="bi bi-cloud-check"></i> Confirm upload</button>
                </div>
            </div>

            <div class="cora-imp-stats">
                <div class="cora-imp-stat"><div class="cora-imp-stat-label">Records uploaded</div><div class="cora-imp-stat-value" id="cora-imp-total">0</div></div>
                <div class="cora-imp-stat"><div class="cora-imp-stat-label">Valid</div><div class="cora-imp-stat-value cora-stat-value--green" id="cora-imp-valid">0</div></div>
                <div class="cora-imp-stat"><div class="cora-imp-stat-label">Need fixing</div><div class="cora-imp-stat-value cora-stat-value--red" id="cora-imp-invalid">0</div></div>
            </div>
            <div class="cora-imp-status" id="cora-imp-headline" hidden></div>
            <div class="cora-imp-chips" id="cora-imp-summary"></div>

            <div class="cora-imp-filter-row">
                <label class="form-label mb-0" for="cora-imp-filter">Show rows</label>
                <select id="cora-imp-filter" class="form-select form-select-sm"></select>
            </div>
            <div class="cora-imp-rows-tbl">
                <?php cora_table('cora-imp', ['Row', 'Company', 'Employee', 'IC / Passport', 'Limit', 'Start', 'End', 'Result'], false); ?>
            </div>
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

        <div class="cora-card" id="cora-imp-pending-card" hidden>
            <h6 class="cora-card-title"><i class="bi bi-hourglass-split"></i> Awaiting confirmation</h6>
            <p class="cora-card-hint mb-2">Validated but not yet confirmed or discarded. Open one to finish it.</p>
            <div class="table-responsive cora-imp-recent-wrap">
                <table class="table table-sm align-middle cora-view-tbl mb-0">
                    <thead><tr><th>File</th><th>Rows</th><th>By</th></tr></thead>
                    <tbody id="cora-imp-recent"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../footer.php';
