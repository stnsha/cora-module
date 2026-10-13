<?php
$page_title = 'Default Rules';
$page_subtitle = 'Default Alpro rule - the last level of the hierarchy: Individual employee rule, then Company rule, then this. Used only where neither the employee nor the company sets a value.';
$page_access = 'super';
require __DIR__ . '/../partials.php';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'settings/settings.js';
?>
<div id="cora-set-alert" class="alert py-2 px-3 small" hidden></div>
<form id="cora-set-form" class="row g-4" novalidate>
    <div class="col-lg-6">
        <div class="mb-3">
            <label class="form-label" for="cora-set-limit">Default Annual Limit (RM) <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="number" class="form-control" id="cora-set-limit" min="0" step="0.01" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="cora-set-mode">Default Over-limit Handling <span class="text-danger" aria-hidden="true">*</span></label>
            <select class="form-select" id="cora-set-mode">
                <option value="1">Block claims above the available balance</option>
                <option value="2">Warn only (allow, flag for review)</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="cora-set-criteria">Default Benefit / Claim Criteria</label>
            <textarea class="form-control" id="cora-set-criteria" rows="4" maxlength="5000"></textarea>
        </div>
    </div>
    <div class="col-lg-6">
        <label class="form-label" for="cora-set-types">Claim Types <span class="text-danger" aria-hidden="true">*</span></label>
        <textarea class="form-control" id="cora-set-types" rows="6"></textarea>
        <div class="form-text">One per line. Offered in the Claim Type list when an outlet creates a claim.</div>
    </div>
    <div class="col-12 d-flex justify-content-end">
        <button type="submit" class="btn btn-primary btn-sm" id="cora-set-save">Save</button>
    </div>
</form>
<?php
include __DIR__ . '/../footer.php';
