/**
 * Claim History (claims/index.php).
 * Talks to claims/api.php: claims_list / claims_counts (filters),
 * claims_cancel. Create claim / Edit open claims/create.php. Row menu: View, Edit, Cancel. The Status
 * dropdown shows the per-status count in each option; ?status= in the URL
 * preselects it (notification links).
 */
(function () {
    'use strict';

    var API = 'claims/api.php';
    var el = function (id) { return document.getElementById(id); };
    var rowsEl = el('cora-claims-rows');
    var alertEl = el('cora-claims-alert');
    var qEl = el('cora-claims-q');
    var monthEl = el('cora-claims-month');
    var companyEl = el('cora-claims-company');
    var statusEl = el('cora-claims-status');
    var status = new URLSearchParams(location.search).get('status') || '';
    var all = [];
    var timer = null;

    var pager = window.coraPager(el('cora-claims-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (c, i) {
            return '<tr data-id="' + c.id + '">' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td><a href="' + Cora.BASE + 'claims/view.php?id=' + c.id + '">' + Cora.esc(c.claim_no) + '</a>' +
                    (c.flag_labels && c.flag_labels.length ? ' <i class="bi bi-flag-fill text-warning" title="' + Cora.esc(c.flag_labels.join('; ')) + '"></i>' : '') + '</td>' +
                '<td>' + Cora.esc(c.visit_date) + '</td>' +
                '<td>' + Cora.esc(c.employee_name) + '<div class="text-muted small">' + Cora.esc(c.employee_ic) + '</div></td>' +
                '<td>' + Cora.esc(c.company_name) + '</td>' +
                '<td>' + Cora.esc(c.outlet_name) + '</td>' +
                '<td>' + Cora.esc(c.so_no) + '</td>' +
                '<td>' + Cora.money(c.total_amount) + '</td>' +
                '<td>' + Cora.money(c.claim_amount) + '</td>' +
                '<td>' + Cora.esc(c.payment_method_label) + '</td>' +
                '<td>' + Cora.claimPill(c.status) + '</td>' +
                '<td class="text-end">' + menu(c) + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="12" class="text-center text-muted py-4">No claims found.</td></tr>';
    });

    function menu(c) {
        var items = [{ act: 'view', label: 'View' }];
        if ([1, 2, 7].indexOf(c.status) !== -1) { items.push({ act: 'edit', label: c.status === 7 ? 'Amend & resubmit' : (c.status === 1 ? 'Edit / submit' : 'Amend') }); }
        if ([1, 2, 3, 7].indexOf(c.status) !== -1) { items.push({ divider: true }, { act: 'cancel', label: 'Cancel claim', danger: true }); }
        return Cora.kebab(items);
    }

    function filters() {
        return { q: qEl.value.trim(), month: monthEl.value, company_id: companyEl ? companyEl.value : '' };
    }

    function load() {
        Cora.alertBox(alertEl, null);
        var f = filters();
        Cora.call(API, 'claims_list', { query: Object.assign({ status: status }, f) }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); all = []; } else { all = res.data; }
            pager.set(all);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
        Cora.call(API, 'claims_counts', { query: f }).then(function (res) {
            if (!res.success) { return; }
            var c = res.data, total = 0;
            Object.keys(c).forEach(function (k) { total += c[k]; });
            [].forEach.call(statusEl.options, function (opt) {
                var key = opt.value;
                var n = key === '' ? total : key.split(',').reduce(function (s, k) { return s + (c[k] || 0); }, 0);
                opt.textContent = opt.getAttribute('data-label') + ' (' + n + ')';
            });
        });
    }

    // Notification links pass a single status; map it onto its option.
    if (status === '2' || status === '3') { status = '2,3'; }
    statusEl.value = [].some.call(statusEl.options, function (o) { return o.value === status; }) ? status : '';
    status = statusEl.value;
    statusEl.addEventListener('change', function () { status = statusEl.value; load(); });

    qEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 300); });
    monthEl.addEventListener('change', load);
    if (companyEl) {
        companyEl.addEventListener('change', load);
        Cora.call('companies/api.php', 'companies_options').then(function (res) {
            if (res.success) {
                companyEl.innerHTML += res.data.map(function (c) { return '<option value="' + c.id + '">' + Cora.esc(c.name) + '</option>'; }).join('');
            }
        });
    }
    el('cora-claims-reset').addEventListener('click', function () {
        qEl.value = '';
        monthEl.value = '';
        statusEl.value = '';
        status = '';
        if (companyEl) { companyEl.value = ''; }
        load();
    });

    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var act = btn.getAttribute('data-act');
        if (act === 'view') { location.href = Cora.BASE + 'claims/view.php?id=' + id; return; }
        if (act === 'edit') { edit(id); return; }
        if (act === 'cancel') { cancel(id); }
    });

    function edit(id) {
        location.href = Cora.BASE + 'claims/create.php?id=' + id;
    }

    function cancel(id) {
        window.coraPrompt('Why is this claim being cancelled? The reserved amount is released.', function (reason) {
            Cora.call(API, 'claims_cancel', { method: 'POST', body: { id: id, reason: reason } }).then(function (res) {
                if (!res.success) { window.coraAlert(Cora.firstError(res), { variant: 'danger' }); return; }
                load();
            });
        }, { title: 'Cancel claim', variant: 'danger', label: 'Reason', required: true });
    }

    load();
})();
