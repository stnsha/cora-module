/**
 * Eligibility Checker (eligibility/index.php).
 * Talks to eligibility/api.php: eligibility_search (?q, company_id) and
 * eligibility_card (?id); companies/api.php companies_options for the filter.
 * Create Claim opens claims/create.php?employee_id=.
 */
(function () {
    'use strict';

    var API = 'eligibility/api.php';
    var el = function (id) { return document.getElementById(id); };

    var alertEl   = el('cora-elig-alert');
    var resultsEl = el('cora-elig-results');
    var rowsEl    = el('cora-elig-rows');
    var cardEl    = el('cora-elig-card');
    var qEl       = el('cora-elig-q');
    var companyEl = el('cora-elig-company');
    var current   = null;   // eligibility_card data

    var SOURCE = {
        employee_override: 'individual override',
        employee: 'individual entitlement',
        company: 'company default',
        'default': 'Default Alpro rule'
    };
    var RULE_SOURCE = {
        company: 'Set on the Company Master',
        client_list: 'As per Corporate Client List',
        'default': 'Default Alpro rule'
    };

    var pager = window.coraPager(el('cora-elig-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (r, i) {
            return '<tr data-id="' + r.id + '">' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td>' + Cora.esc(r.name) + (r.restricted ? ' <i class="bi bi-exclamation-triangle-fill text-danger" title="Restriction / override on this employee"></i>' : '') + '</td>' +
                '<td>' + Cora.esc(r.ic_no) + '</td>' +
                '<td>' + Cora.esc(r.employee_no || '-') + '</td>' +
                '<td>' + Cora.esc(r.company_name) + '</td>' +
                '<td class="text-muted">' + Cora.esc(r.department || '-') + '</td>' +
                '<td>' + Cora.pill(r.status_pill, r.status) + '</td>' +
                '<td>' + Cora.money(r.available) + '</td>' +
                '<td class="text-end">' + Cora.kebab([{ act: 'view', label: 'View eligibility card' }]) + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No employee found. Check the IC / passport, or ask HR to confirm enrolment.</td></tr>';
    });

    Cora.call('companies/api.php', 'companies_options').then(function (res) {
        if (!res.success) { return; }
        companyEl.innerHTML += res.data.map(function (c) {
            return '<option value="' + c.id + '">' + Cora.esc(c.name) + '</option>';
        }).join('');
    });

    function search() {
        Cora.alertBox(alertEl, null);
        cardEl.hidden = true;
        Cora.call(API, 'eligibility_search', { query: { q: qEl.value.trim(), company_id: companyEl.value } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); resultsEl.hidden = true; return; }
            resultsEl.hidden = false;
            pager.set(res.data);
            if (res.data.length === 1) { openCard(res.data[0].id); }
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    el('cora-elig-search').addEventListener('submit', function (e) {
        e.preventDefault();
        if (qEl.value.trim().length < 3) { Cora.alertBox(alertEl, 'Type at least 3 characters.', 'warning'); return; }
        search();
    });
    el('cora-elig-reset').addEventListener('click', function () {
        qEl.value = '';
        companyEl.value = '';
        resultsEl.hidden = true;
        cardEl.hidden = true;
        Cora.alertBox(alertEl, null);
        qEl.focus();
    });
    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act="view"]');
        if (btn) { openCard(parseInt(btn.closest('tr').getAttribute('data-id'), 10)); }
    });

    var presetQ = new URLSearchParams(location.search).get('q');
    if (presetQ && presetQ.trim().length >= 3) {
        qEl.value = presetQ;
        setTimeout(search, 0);
    }

    function openCard(id) {
        Cora.call(API, 'eligibility_card', { query: { id: id } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            current = res.data;
            renderCard(res.data);
            cardEl.hidden = false;
            cardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    function renderCard(d) {
        var e = d.employee, ent = d.entitlement, rules = d.rules || {};
        el('cora-card-name').textContent = e.name;
        el('cora-card-meta').innerHTML = Cora.esc(e.company_name || '') +
            ' &middot; IC / Passport ' + Cora.esc(e.ic_no) +
            (e.employee_no ? ' &middot; Employee ID ' + Cora.esc(e.employee_no) : '') +
            (e.department ? ' &middot; ' + Cora.esc(e.department) : '');
        el('cora-card-status').innerHTML = '<span class="cora-pill ' + ent.status_pill + '" style="font-size:13px;padding:4px 12px;">' + Cora.esc(ent.status) + '</span>';

        el('cora-card-limit').textContent = Cora.money(ent.limit);
        el('cora-card-limit-source').textContent = SOURCE[ent.limit_source] || ent.limit_source;
        el('cora-card-approved').textContent = Cora.money(ent.approved);
        el('cora-card-pending').textContent = Cora.money(ent.pending);
        el('cora-card-available').textContent = Cora.money(ent.available);
        el('cora-card-available').className = 'cora-stat-value ' + (ent.available > 0 ? 'cora-stat-value--green' : 'cora-stat-value--red');
        el('cora-card-period').textContent = (ent.period_start || '-') + ' to ' + (ent.period_end || '-');
        el('cora-card-category').textContent = rules.category || '-';
        el('cora-card-limit-mode').textContent = ent.limit_mode === 2
            ? 'Claims above the balance are allowed and flagged for review'
            : 'Claims above the balance are blocked - use Mixed Payment';

        // Restriction / override first and prominent; information remark separate.
        var restr = el('cora-card-restriction');
        if (e.restriction_type && e.restriction_type !== 0) {
            restr.innerHTML = '<strong><i class="bi bi-exclamation-octagon-fill"></i> ' + Cora.esc(e.restriction_label) + '</strong>' +
                (e.restriction_type === 1 && e.override_limit !== null ? ' - limit ' + Cora.money(e.override_limit) + (e.override_until ? ' until ' + Cora.esc(e.override_until) : '') : '') +
                (e.restriction_remark ? '<div class="mt-1">' + Cora.lines(e.restriction_remark) + '</div>' : '');
            restr.hidden = false;
        } else {
            restr.hidden = true;
        }
        var info = el('cora-card-info');
        info.innerHTML = e.info_remark ? '<strong>Special Employee Remark:</strong> ' + Cora.lines(e.info_remark) : '';
        info.hidden = !e.info_remark;

        el('cora-card-criteria').innerHTML = rules.benefit_criteria ? Cora.lines(rules.benefit_criteria) : '<span class="text-muted">None recorded.</span>';
        el('cora-card-criteria-source').textContent = rules.benefit_criteria ? (RULE_SOURCE[rules.benefit_criteria_source] || '') : '';
        el('cora-card-remark').innerHTML = rules.general_remark ? Cora.lines(rules.general_remark) : '<span class="text-muted">None recorded.</span>';
        el('cora-card-remark-source').textContent = rules.general_remark ? (RULE_SOURCE[rules.general_remark_source] || '') : '';

        el('cora-card-history').innerHTML = d.history.length ? d.history.map(function (c, i) {
            var no = c.id ? '<a href="' + Cora.BASE + 'claims/view.php?id=' + c.id + '">' + Cora.esc(c.claim_no) + '</a>' : Cora.esc(c.claim_no);
            return '<tr><td class="text-muted">' + (i + 1) + '</td><td>' + no + '</td><td>' + Cora.esc(c.visit_date) + '</td>' +
                '<td>' + Cora.esc(c.outlet_name) + '</td><td>' + Cora.esc(c.so_no || '-') + '</td>' +
                '<td>' + Cora.esc(c.payment_method_label) + '</td><td class="text-end">' + Cora.money(c.claim_amount) + '</td>' +
                '<td>' + Cora.claimPill(c.status) + '</td></tr>';
        }).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">No claims in this period.</td></tr>';

        var blocked = ent.status === 'SUSPENDED' || ent.status === 'INACTIVE';
        var claimBtn = el('cora-card-claim');
        claimBtn.disabled = blocked;
        claimBtn.title = blocked ? 'Employee is ' + ent.status + ' - no corporate claim can be submitted.' : '';
    }

    el('cora-card-claim').addEventListener('click', function () {
        if (current) { location.href = Cora.BASE + 'claims/create.php?employee_id=' + current.employee.id; }
    });
})();
