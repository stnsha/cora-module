<?php
$page_title = 'Company Master';
$page_subtitle = 'Corporate clients administered directly by Alpro. Link each company to the Corporate Client List: category, benefit criteria and general remark left empty here are inherited from it, so the two never carry conflicting instructions.';
$page_access = 'admin';
require __DIR__ . '/../partials.php';
// Only a Super Admin configures companies; Corporate Admins view them.
$page_title_actions = static fn (int $role): string => $role === 1 ? cora_title_button('Add company', 'cora-co-add') : '';
include __DIR__ . '/../header.php';
$page_js = CORA_BASE . 'companies/companies.js';
?>
<div class="cora-card cora-filter mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4 col-sm-6">
            <label class="form-label" for="cora-co-q">Search</label>
            <input type="search" id="cora-co-q" class="form-control form-control-sm" placeholder="Company name or code">
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label" for="cora-co-status">Status</label>
            <select id="cora-co-status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="1">Active</option>
                <option value="2">Inactive</option>
            </select>
        </div>
    </div>
</div>

<div id="cora-co-alert" class="alert alert-danger py-2 px-3 small" hidden></div>
<?php cora_table('cora-co', ['Company', 'Code', 'Corporate Client List', 'Default Limit', 'Over-limit', 'Employees', 'Status']); ?>

<div class="modal fade" id="cora-co-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="cora-co-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="cora-co-title">Add company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <input type="hidden" id="cora-co-id">
                            <input type="hidden" id="cora-co-client-id">
                            <div class="mb-3 cora-users-search">
                                <label class="form-label" for="cora-co-client">Corporate Client List</label>
                                <input type="search" class="form-control" id="cora-co-client" placeholder="Type the client name to link..." autocomplete="off">
                                <ul class="cora-users-results" id="cora-co-client-results" role="listbox" hidden></ul>
                                <div class="form-text" id="cora-co-client-note">Not linked - rules come only from this form and the Default Alpro rule.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label" for="cora-co-name">Company Name <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input type="text" class="form-control" id="cora-co-name" maxlength="255" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="cora-co-code">Code</label>
                                    <input type="text" class="form-control" id="cora-co-code" maxlength="30">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-category">Category</label>
                                    <input type="text" class="form-control" id="cora-co-category" maxlength="100" placeholder="Empty = inherit">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-status-f">Status <span class="text-danger" aria-hidden="true">*</span></label>
                                    <select class="form-select" id="cora-co-status-f">
                                        <option value="1">Active</option>
                                        <option value="2">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-limit">Default Annual Limit (RM)</label>
                                    <input type="number" class="form-control" id="cora-co-limit" min="0" step="0.01" placeholder="Empty = Default Alpro rule">
                                    <div class="form-text">Used when an employee has no limit of their own.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-mode">Over-limit Handling <span class="text-danger" aria-hidden="true">*</span></label>
                                    <select class="form-select" id="cora-co-mode">
                                        <option value="1">Block claims above the available balance</option>
                                        <option value="2">Warn only (allow, flag for review)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-start">Contract Period Start</label>
                                    <input type="date" class="form-control" id="cora-co-start">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-end">Contract Period End</label>
                                    <input type="date" class="form-control" id="cora-co-end">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="cora-co-criteria">Benefit / Claim Criteria</label>
                                    <textarea class="form-control" id="cora-co-criteria" rows="3" maxlength="5000" placeholder="Empty = follow the Corporate Client List"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="cora-co-remark">Company General Remark</label>
                                    <textarea class="form-control" id="cora-co-remark" rows="3" maxlength="5000" placeholder="Empty = follow the Corporate Client List"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-hr-name">HR Contact Name</label>
                                    <input type="text" class="form-control" id="cora-co-hr-name" maxlength="150">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cora-co-hr-email">HR Contact Email</label>
                                    <input type="email" class="form-control" id="cora-co-hr-email" maxlength="255">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="cora-card h-100">
                                <h6 class="cora-card-title"><i class="bi bi-diagram-3"></i> Inherited from Corporate Client List</h6>
                                <div id="cora-co-inherited" class="small text-muted">Link a client to see its rules.</div>
                            </div>
                        </div>
                    </div>
                    <div id="cora-co-error" class="text-danger small mt-3" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <?php if ($cora_is_super): ?>
                    <button type="submit" class="btn btn-primary btn-sm" id="cora-co-save">Save</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<script>window.CORA_CAN_EDIT = <?php echo json_encode($cora_is_super); ?>;</script>
<?php
include __DIR__ . '/../footer.php';
