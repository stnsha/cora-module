/**
 * Claim view / review (claims/view.php?id=&review=1).
 * claims_get returns the claim, the employee and their entitlement now, the
 * company rules, previous claims, the audit timeline and what the signed-in
 * user may do (can.edit / can.cancel / can.review). Opening with review=1 as
 * an admin moves Submitted -> Pending Review. Actions: claims_approve,
 * claims_reject (reason compulsory), claims_return, claims_cancel.
 */
(function () {
    'use strict';

    var API = 'claims/api.php';
    var ID = window.CORA_CLAIM_ID;
    var REVIEW = window.CORA_CLAIM_REVIEW;
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-cv-alert');
    var data = null;

    var ACTIONS = {
        draft_created: 'Draft created', draft_updated: 'Draft updated', submitted: 'Submitted',
        resubmitted: 'Resubmitted after amendment', amended: 'Amended by outlet', review_started: 'Review started',
        approved: 'Approved', rejected: 'Rejected', returned_for_amendment: 'Returned for amendment',
        cancelled: 'Cancelled', voided: 'Voided (approved claim reversed)'
    };

    function row(label, value) {
        return '<tr><th class="text-muted fw-normal" style="width:40%;">' + Cora.esc(label) + '</th><td>' + value + '</td></tr>';
    }

    function load() {
        Cora.call(API, 'claims_get', { query: { id: ID, review: REVIEW ? 1 : '' } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            data = res.data;
            render(res.data);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    function render(d) {
        var c = d.claim, e = d.employee || {}, ent = d.entitlement || {}, rules = d.rules || {};
        document.title = 'Claim ' + c.claim_no;
        el('cora-cv-no').textContent = c.claim_no;
        el('cora-cv-status').innerHTML = Cora.claimPill(c.status);
        el('cora-cv-sub').textContent = c.employee_name + ' - ' + c.company_name + ' - ' + c.outlet_name;

        var flags = el('cora-cv-flags');
        flags.innerHTML = c.flag_labels.length ? '<strong><i class="bi bi-flag-fill"></i> Flagged:</strong> ' + c.flag_labels.map(Cora.esc).join('; ') : '';
        flags.hidden = !c.flag_labels.length;

        var dec = el('cora-cv-decision');
        if (c.status === 5 || c.status === 7 || (c.status === 4 && c.review_remark)) {
            dec.className = 'alert py-2 px-3 small alert-' + (c.status === 5 ? 'danger' : (c.status === 7 ? 'primary' : 'success'));
            dec.innerHTML = '<strong>' + Cora.esc(c.status_label) + '</strong> by ' + Cora.esc(c.reviewed_by_name || '-') + ' on ' + Cora.esc(c.reviewed_at || '-') +
                (c.review_remark ? ': ' + Cora.lines(c.review_remark) : '');
            dec.hidden = false;
        } else if (c.status === 6) {
            dec.className = 'alert py-2 px-3 small alert-secondary';
            dec.innerHTML = '<strong>Cancelled</strong> by ' + Cora.esc(c.cancelled_by_name || '-') + ' on ' + Cora.esc(c.cancelled_at || '-') + ': ' + Cora.lines(c.cancel_reason || '');
            dec.hidden = false;
        } else {
            dec.hidden = true;
        }

        el('cora-cv-txn').innerHTML =
            row('Visitation Date', Cora.esc(c.visit_date)) +
            row('Sales Order No.', Cora.esc(c.so_no) + (c.duplicate_authorised ? ' ' + Cora.pill('cora-pill-warning', 'authorised duplicate') : '')) +
            row('Outlet', Cora.esc(c.outlet_name)) +
            row('Purchase Amount', Cora.money(c.total_amount)) +
            row('Corporate Claim', '<strong>' + Cora.money(c.claim_amount) + '</strong>') +
            row('Self-Pay', Cora.money(c.self_pay_amount)) +
            row('Payment Method', Cora.esc(c.payment_method_label)) +
            row('Claim Type', Cora.esc(c.claim_type || '-')) +
            row('Remark', c.remark ? Cora.lines(c.remark) : '-') +
            row('Submitted By', Cora.esc(c.submitted_by_name || c.created_by_name || '-')) +
            row('Submission Date/Time', Cora.esc(c.submitted_at || '(not submitted)')) +
            (c.resubmit_count ? row('Resubmissions', Cora.esc(c.resubmit_count)) : '');

        var restr = el('cora-cv-restriction');
        restr.hidden = !(e.restriction_type && e.restriction_type !== 0);
        if (!restr.hidden) {
            restr.innerHTML = '<strong>' + Cora.esc(e.restriction_label) + '</strong>' +
                (e.restriction_type === 1 && e.override_limit !== null ? ' - limit ' + Cora.money(e.override_limit) + (e.override_until ? ' until ' + Cora.esc(e.override_until) : '') : '') +
                (e.restriction_remark ? '<div>' + Cora.lines(e.restriction_remark) + '</div>' : '');
        }
        var info = el('cora-cv-info');
        info.hidden = !e.info_remark;
        info.innerHTML = e.info_remark ? '<strong>Employee remark:</strong> ' + Cora.lines(e.info_remark) : '';
        el('cora-cv-emp').innerHTML =
            row('Name', Cora.esc(e.name || c.employee_name)) +
            row('IC / Passport', Cora.esc(e.ic_no || c.employee_ic)) +
            row('Employee ID', Cora.esc(e.employee_no || c.employee_no || '-')) +
            row('Company', Cora.esc(c.company_name)) +
            row('Department', Cora.esc(e.department || '-')) +
            row('Eligibility now', ent.status ? Cora.pill(ent.status_pill, ent.status) : '-') +
            row('Entitlement Period', Cora.esc((ent.period_start || '-') + ' to ' + (ent.period_end || '-'))) +
            row('Period at submission', Cora.esc((c.period_start || '-') + ' to ' + (c.period_end || '-')));

        el('cora-cv-limit').textContent = Cora.money(ent.limit);
        el('cora-cv-approved').textContent = Cora.money(ent.approved);
        el('cora-cv-pending').textContent = Cora.money(ent.pending);
        el('cora-cv-available').textContent = Cora.money(ent.available);
        el('cora-cv-snapshot').textContent = c.available_snapshot !== null
            ? 'available at submission ' + Cora.money(c.available_snapshot) + ' (limit ' + Cora.money(c.limit_snapshot) + ')'
            : 'not submitted yet';

        el('cora-cv-category').innerHTML = '<strong>Category:</strong><br>' + Cora.esc(rules.category || '-');
        el('cora-cv-criteria').innerHTML = '<strong>Benefit / claim criteria:</strong><br>' + (rules.benefit_criteria ? Cora.lines(rules.benefit_criteria) : '<span class="text-muted">None recorded.</span>');
        el('cora-cv-remark').innerHTML = '<strong>General remark:</strong><br>' + (rules.general_remark ? Cora.lines(rules.general_remark) : '<span class="text-muted">None recorded.</span>');

        el('cora-cv-timeline').innerHTML = d.timeline.length ? d.timeline.map(function (t) {
            return '<li class="mb-2"><div><strong>' + Cora.esc(ACTIONS[t.action] || t.action) + '</strong> - ' + Cora.esc(t.staff_name || '-') + '</div>' +
                '<div class="text-muted">' + Cora.esc(t.created_at) + '</div>' + (t.remark ? '<div>' + Cora.lines(t.remark) + '</div>' : '') + '</li>';
        }).join('') : '<li class="text-muted">No activity recorded.</li>';

        el('cora-cv-history').innerHTML = d.history.length ? d.history.map(function (h, i) {
            return '<tr><td class="text-muted">' + (i + 1) + '</td><td>' + Cora.esc(h.claim_no) + '</td><td>' + Cora.esc(h.visit_date) + '</td>' +
                '<td>' + Cora.esc(h.outlet_name) + '</td><td>' + Cora.esc(h.so_no) + '</td><td class="text-end">' + Cora.money(h.claim_amount) + '</td>' +
                '<td>' + Cora.claimPill(h.status) + '</td></tr>';
        }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">No other claims.</td></tr>';

        var can = d.can;
        var actions = el('cora-cv-actions');
        actions.querySelector('[data-act="approve"]').hidden = !can.review;
        actions.querySelector('[data-act="reject"]').hidden = !can.review;
        actions.querySelector('[data-act="return"]').hidden = !can.review;
        var editBtn = actions.querySelector('[data-act="edit"]');
        editBtn.hidden = !can.edit;
        editBtn.querySelector('span').textContent = c.status === 7 ? 'Amend & Resubmit' : (c.status === 1 ? 'Edit / Submit' : 'Amend');
        var cancelBtn = actions.querySelector('[data-act="cancel"]');
        cancelBtn.hidden = !can.cancel;
        cancelBtn.querySelector('span').textContent = c.status === 4 ? 'Void Claim' : 'Cancel Claim';
        el('cora-cv-body').hidden = false;
    }

    function act(action, body, done) {
        Cora.call(API, action, { method: 'POST', body: Object.assign({ id: ID }, body || {}) }).then(function (res) {
            if (!res.success) { window.coraAlert(Cora.firstError(res), { variant: 'danger', title: 'Not saved' }); return; }
            if (done) { window.coraAlert(done, { variant: 'success', title: 'Done' }); }
            load();
        }).catch(function () { window.coraAlert('Could not reach the server.', { variant: 'danger' }); });
    }

    el('cora-cv-actions').addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn || !data) { return; }
        var c = data.claim;
        switch (btn.getAttribute('data-act')) {
            case 'approve':
                window.coraPrompt('Approve ' + Cora.money(c.claim_amount) + ' for ' + c.employee_name + '? The amount is deducted from the entitlement. Optional remark:', function (text) {
                    act('claims_approve', { remark: text }, 'Claim ' + c.claim_no + ' approved.');
                }, { title: 'Approve claim', variant: 'success', label: 'Remark (optional)', required: false, confirmLabel: 'Approve' });
                break;
            case 'reject':
                window.coraPrompt('Reject claim ' + c.claim_no + '? The reserved ' + Cora.money(c.claim_amount) + ' is released.', function (text) {
                    act('claims_reject', { remark: text }, 'Claim ' + c.claim_no + ' rejected.');
                }, { title: 'Reject claim', variant: 'danger', label: 'Rejection reason', required: true, confirmLabel: 'Reject' });
                break;
            case 'return':
                window.coraPrompt('Return claim ' + c.claim_no + ' to the outlet for correction. The amount stays reserved until it is resubmitted or cancelled.', function (text) {
                    act('claims_return', { remark: text }, 'Claim ' + c.claim_no + ' returned to the outlet.');
                }, { title: 'Return for amendment', variant: 'warning', label: 'What should the outlet correct?', required: true, confirmLabel: 'Return' });
                break;
            case 'cancel':
                window.coraPrompt((c.status === 4 ? 'Void approved claim ' + c.claim_no + '? The approved utilisation is reversed.' : 'Cancel claim ' + c.claim_no + '? Any reserved amount is released.'), function (text) {
                    act('claims_cancel', { reason: text }, 'Claim ' + c.claim_no + (c.status === 4 ? ' voided.' : ' cancelled.'));
                }, { title: c.status === 4 ? 'Void claim' : 'Cancel claim', variant: 'danger', label: 'Reason', required: true });
                break;
            case 'edit':
                location.href = Cora.BASE + 'claims/create.php?id=' + ID;
                break;
        }
    });

    if (!ID) { Cora.alertBox(alertEl, 'No claim selected.'); return; }
    load();
})();
