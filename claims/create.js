/**
 * Create / edit claim page (claims/create.php).
 *   new claim:  search the employee (eligibility_search) -> pick -> eligibility
 *               summary (eligibility_card) + claim details form
 *   ?employee_id=N  employee preselected;  ?id=N  edit (claims_get)
 * Saves through claims/api.php claims_save (draft) / claims_submit, then opens
 * the claim page (claims/view.php?id=&saved=draft|submitted).
 *
 * Payment method rules (enforced again by Services\ClaimWorkflow):
 *   Corporate Claim: corporate = purchase
 *   Mixed Payment:   0 < corporate < purchase, the rest is self-pay
 *   Self-Pay / TPA:  nothing charged to the corporate entitlement
 */
(function () {
    'use strict';

    var PAY = { CORPORATE: 1, TPA: 2, SELF: 3, MIXED: 4 };
    var CFG = window.CORA_CC || {};
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-cc-alert');
    var f = {
        outlet: el('cora-cc-outlet'), visit: el('cora-cc-visit'), so: el('cora-cc-so'), total: el('cora-cc-total'),
        method: el('cora-cc-method'), corporate: el('cora-cc-corporate'), selfpay: el('cora-cc-selfpay'), type: el('cora-cc-type'),
        suggest: el('cora-cc-suggest'), suggestText: el('cora-cc-suggest-text'), suggestBtn: el('cora-cc-suggest-btn'),
        remark: el('cora-cc-remark-f'), dupWrap: el('cora-cc-dup-wrap'), dup: el('cora-cc-dup'), error: el('cora-cc-error'),
        draft: el('cora-cc-draft'), submit: el('cora-cc-submit'), form: el('cora-cc-form')
    };

    var ctx = null;       // claim_context: outlets, claim types, payment methods
    var employee = null;  // selected employee (masked for outlet roles)
    var ent = null;       // entitlement, adjusted for an edited claim's own reservation
    var claim = null;     // claim being edited

    // ---- context (outlets, claim types, payment methods) ----

    function loadContext() {
        return Cora.call('eligibility/api.php', 'claim_context').then(function (res) {
            if (!res.success) { throw new Error(Cora.firstError(res)); }
            ctx = res.data;
            f.outlet.innerHTML = ctx.outlets.length
                ? ctx.outlets.map(function (o) { return '<option value="' + o.id + '">' + Cora.esc(o.name) + ' (' + Cora.esc(o.code) + ')</option>'; }).join('')
                : '<option value="">No outlet on your account</option>';
            f.outlet.disabled = ctx.outlets.length <= 1;
            f.type.innerHTML = '<option value="">-</option>' + ctx.claim_types.map(function (t) {
                return '<option value="' + Cora.esc(t) + '">' + Cora.esc(t) + '</option>';
            }).join('');
            f.method.innerHTML = Object.keys(ctx.payment_methods).map(function (k) {
                return '<option value="' + k + '">' + Cora.esc(ctx.payment_methods[k]) + '</option>';
            }).join('');
            f.dupWrap.hidden = !ctx.can_authorise_duplicate;
        });
    }

    // ---- step 1: find employee ----

    var found = [];
    function showFinder() {
        el('cora-cc-find').hidden = false;
        el('cora-cc-employee').hidden = true;
        f.form.hidden = true;
        el('cora-cc-q').focus();
    }

    Cora.call('companies/api.php', 'companies_options').then(function (res) {
        if (res.success) {
            el('cora-cc-company').innerHTML += res.data.map(function (c) { return '<option value="' + c.id + '">' + Cora.esc(c.name) + '</option>'; }).join('');
        }
    });

    el('cora-cc-search').addEventListener('submit', function (e) {
        e.preventDefault();
        Cora.alertBox(alertEl, null);
        var q = el('cora-cc-q').value.trim();
        if (q.length < 3) { Cora.alertBox(alertEl, 'Type at least 3 characters.', 'warning'); return; }
        Cora.call('eligibility/api.php', 'eligibility_search', { query: { q: q, company_id: el('cora-cc-company').value } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            found = res.data;
            el('cora-cc-results').hidden = false;
            el('cora-cc-rows').innerHTML = found.length ? found.map(function (r, i) {
                var blocked = r.status === 'SUSPENDED' || r.status === 'INACTIVE';
                return '<tr data-id="' + r.id + '">' +
                    '<td class="text-muted">' + (i + 1) + '</td>' +
                    '<td>' + Cora.esc(r.name) + (r.restricted ? ' <i class="bi bi-exclamation-triangle-fill text-danger" title="Restriction / override on this employee"></i>' : '') + '</td>' +
                    '<td>' + Cora.esc(r.ic_no) + '</td>' +
                    '<td>' + Cora.esc(r.employee_no || '-') + '</td>' +
                    '<td>' + Cora.esc(r.company_name) + '</td>' +
                    '<td>' + Cora.pill(r.status_pill, r.status) + '</td>' +
                    '<td>' + Cora.money(r.available) + '</td>' +
                    '<td class="text-end">' + (blocked ? '<span class="text-muted small">Not claimable</span>' : Cora.kebab([{ act: 'pick', label: 'Select employee' }])) + '</td>' +
                '</tr>';
            }).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">No employee found. Check the IC / passport, or ask HR to confirm enrolment.</td></tr>';
            if (found.length === 1 && found[0].status !== 'SUSPENDED' && found[0].status !== 'INACTIVE') { selectEmployee(found[0].id); }
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    });

    el('cora-cc-rows').addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act="pick"]');
        if (btn) { selectEmployee(parseInt(btn.closest('tr').getAttribute('data-id'), 10)); }
    });
    el('cora-cc-change').addEventListener('click', showFinder);

    function selectEmployee(id) {
        Cora.alertBox(alertEl, null);
        Cora.call('eligibility/api.php', 'eligibility_card', { query: { id: id } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            employee = res.data.employee;
            ent = res.data.entitlement;
            renderEmployee(res.data.rules || {});
            el('cora-cc-find').hidden = true;
            el('cora-cc-change').hidden = !!claim;
            if (ent.status === 'SUSPENDED' || ent.status === 'INACTIVE') {
                Cora.alertBox(alertEl, 'Employee is ' + ent.status + ' - a corporate claim cannot be submitted.');
                f.form.hidden = true;
                return;
            }
            f.form.hidden = false;
            if (!claim) { resetForm(); }
            recalc();
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    function renderEmployee(rules) {
        var e = employee;
        el('cora-cc-emp-name').textContent = e.name;
        el('cora-cc-emp-meta').innerHTML = Cora.esc(e.company_name || '') + ' &middot; IC / Passport ' + Cora.esc(e.ic_no) +
            (e.employee_no ? ' &middot; Employee ID ' + Cora.esc(e.employee_no) : '') + (e.department ? ' &middot; ' + Cora.esc(e.department) : '');
        el('cora-cc-emp-status').innerHTML = Cora.pill(ent.status_pill, ent.status);

        var restr = el('cora-cc-restriction');
        restr.hidden = !(e.restriction_type && e.restriction_type !== 0);
        if (!restr.hidden) {
            restr.innerHTML = '<strong><i class="bi bi-exclamation-octagon-fill"></i> ' + Cora.esc(e.restriction_label) + '</strong>' +
                (e.restriction_type === 1 && e.override_limit !== null ? ' - limit ' + Cora.money(e.override_limit) + (e.override_until ? ' until ' + Cora.esc(e.override_until) : '') : '') +
                (e.restriction_remark ? '<div class="mt-1">' + Cora.lines(e.restriction_remark) + '</div>' : '');
        }
        var info = el('cora-cc-info');
        info.hidden = !e.info_remark;
        info.innerHTML = e.info_remark ? '<strong>Special Employee Remark:</strong> ' + Cora.lines(e.info_remark) : '';

        // Editing a claim that already reserves its amount: that amount is
        // available to the claim itself (the server excludes it the same way).
        if (claim && [2, 3, 7].indexOf(claim.status) !== -1) {
            ent = Object.assign({}, ent);
            ent.pending = Math.max(0, Number(ent.pending) - Number(claim.claim_amount));
            ent.available = Math.max(0, Number(ent.limit) - Number(ent.approved) - ent.pending);
            el('cora-cc-pending-label').textContent = 'other pending claims';
        }
        el('cora-cc-limit').textContent = Cora.money(ent.limit);
        el('cora-cc-period').textContent = (ent.period_start || '-') + ' to ' + (ent.period_end || '-');
        el('cora-cc-approved').textContent = Cora.money(ent.approved);
        el('cora-cc-pending').textContent = Cora.money(ent.pending);
        el('cora-cc-available').textContent = Cora.money(ent.available);
        el('cora-cc-available').className = 'cora-stat-value ' + (ent.available > 0 ? 'cora-stat-value--green' : 'cora-stat-value--red');
        el('cora-cc-criteria').innerHTML = rules.benefit_criteria ? Cora.lines(rules.benefit_criteria) : '<span class="text-muted">None recorded.</span>';
        el('cora-cc-remark').innerHTML = rules.general_remark ? '<strong>Company general remark:</strong><br>' + Cora.lines(rules.general_remark) : '';
        el('cora-cc-employee').hidden = false;
    }

    // ---- step 2: claim details ----

    function resetForm() {
        f.visit.value = Cora.today();
        f.so.value = '';
        f.type.value = '';
        f.total.value = '';
        f.method.value = String(PAY.CORPORATE);
        f.corporate.value = '';
        f.remark.value = '';
        f.dup.checked = false;
        f.error.hidden = true;
    }

    function fillFromClaim(c) {
        f.visit.value = Cora.toInputDate(c.visit_date);
        f.so.value = c.so_no;
        f.type.value = c.claim_type || '';
        f.total.value = Number(c.total_amount).toFixed(2);
        f.method.value = String(c.payment_method);
        f.corporate.value = Number(c.claim_amount).toFixed(2);
        f.remark.value = c.remark || '';
        if (c.outlet_id) { f.outlet.value = String(c.outlet_id); }
        var note = el('cora-cc-return-note');
        note.hidden = !(c.status === 7 && c.review_remark);
        note.textContent = c.status === 7 ? 'Returned for amendment by ' + (c.reviewed_by_name || 'Admin') + ': ' + (c.review_remark || '') : '';
        // A submitted / returned claim is changed by submitting it again.
        f.draft.hidden = c.status !== 1;
        f.submit.textContent = c.status === 7 ? 'Resubmit Claim' : (c.status === 2 ? 'Save Amendment' : 'Submit Claim');
        el('cora-cc-find').hidden = true;
    }

    function recalc() {
        var total = Number(f.total.value || 0);
        var method = parseInt(f.method.value, 10);
        var corporate;
        if (method === PAY.CORPORATE) {
            corporate = total;
            f.corporate.readOnly = true;
            f.corporate.value = total ? total.toFixed(2) : '';
        } else if (method === PAY.MIXED) {
            f.corporate.readOnly = false;
            corporate = Number(f.corporate.value || 0);
        } else {
            corporate = 0;
            f.corporate.readOnly = true;
            f.corporate.value = '0.00';
        }
        f.selfpay.textContent = method === PAY.TPA ? 'Paid via TPA: ' + Cora.money(total) : 'Self-pay: ' + Cora.money(Math.max(0, total - corporate));

        var avail = ent ? Number(ent.available) : 0;
        var over = corporate > avail + 0.001 && total > 0;
        f.suggest.hidden = !over;
        if (over) {
            f.suggestText.textContent = 'Corporate claim ' + Cora.money(corporate) + ' exceeds the available balance of ' + Cora.money(avail) + '.' +
                (avail > 0 ? ' Split it: corporate ' + Cora.money(avail) + ' + self-pay ' + Cora.money(total - avail) + '.' : ' Record it as Self-Pay.');
            f.suggestBtn.textContent = avail > 0 ? 'Use Mixed Payment' : 'Use Self-Pay';
        }
    }

    f.total.addEventListener('input', recalc);
    f.corporate.addEventListener('input', recalc);
    f.method.addEventListener('change', function () {
        if (parseInt(f.method.value, 10) === PAY.MIXED && !Number(f.corporate.value)) {
            f.corporate.value = Math.min(ent ? Number(ent.available) : 0, Number(f.total.value || 0)).toFixed(2);
        }
        recalc();
    });
    f.suggestBtn.addEventListener('click', function () {
        var avail = ent ? Number(ent.available) : 0;
        if (avail > 0) {
            f.method.value = String(PAY.MIXED);
            f.corporate.value = avail.toFixed(2);
        } else {
            f.method.value = String(PAY.SELF);
        }
        recalc();
    });

    function save(submit) {
        f.error.hidden = true;
        if (submit && !f.form.reportValidity()) { return; }
        var body = {
            employee_id: employee.id,
            outlet_id: parseInt(f.outlet.value, 10) || null,
            visit_date: f.visit.value,
            so_no: f.so.value,
            claim_type: f.type.value,
            total_amount: f.total.value,
            payment_method: parseInt(f.method.value, 10),
            claim_amount: f.corporate.value,
            remark: f.remark.value,
            authorise_duplicate: f.dup.checked
        };
        if (claim) { body.id = claim.id; }
        f.draft.disabled = f.submit.disabled = true;
        Cora.call('claims/api.php', submit ? 'claims_submit' : 'claims_save', { method: 'POST', body: body }).then(function (res) {
            f.draft.disabled = f.submit.disabled = false;
            if (!res.success) {
                f.error.textContent = Cora.firstError(res);
                f.error.hidden = false;
                f.error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            location.href = Cora.BASE + 'claims/view.php?id=' + res.data.id + '&saved=' + (submit ? 'submitted' : 'draft');
        }).catch(function () {
            f.draft.disabled = f.submit.disabled = false;
            f.error.textContent = 'Could not reach the server.';
            f.error.hidden = false;
        });
    }

    f.form.addEventListener('submit', function (e) { e.preventDefault(); save(true); });
    f.draft.addEventListener('click', function () { save(false); });

    // ---- start ----

    loadContext().then(function () {
        if (CFG.id) {
            return Cora.call('claims/api.php', 'claims_get', { query: { id: CFG.id } }).then(function (res) {
                if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
                if (!res.data.can.edit) {
                    Cora.alertBox(alertEl, 'Claim ' + res.data.claim.claim_no + ' cannot be edited by you in its current status (' + res.data.claim.status_label + ').');
                    return;
                }
                claim = res.data.claim;
                fillFromClaim(claim);
                selectEmployee(claim.employee_id);
            });
        }
        if (CFG.employee_id) { selectEmployee(CFG.employee_id); return; }
        showFinder();
    }).catch(function (err) { Cora.alertBox(alertEl, err.message || 'Could not load the claim form.'); });
})();
