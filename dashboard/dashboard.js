/**
 * Dashboard (dashboard/index.php).
 * dashboard/api.php: notifications (queue cards) and company_dashboard
 * (?month=YYYY-MM &company_id=) - KPI cards for the selection, an HR summary
 * sentence and the per-company table.
 */
(function () {
    'use strict';

    var API = 'dashboard/api.php';
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-dash-alert');
    var monthEl = el('cora-dash-month');
    var companyEl = el('cora-dash-company');
    var rowsEl = el('cora-dash-rows');

    var pager = window.coraPager(el('cora-dash-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (r, i) {
            return '<tr>' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td>' + Cora.esc(r.name) + '</td>' +
                '<td>' + r.enrolled.toLocaleString() + '</td>' +
                '<td>' + r.utilised.toLocaleString() + '</td>' +
                '<td>' + r.utilisation_rate + '%</td>' +
                '<td>' + r.claims.toLocaleString() + '</td>' +
                '<td>' + Cora.money(r.approved) + '</td>' +
                '<td>' + Cora.money(r.pending) + '</td>' +
                '<td>' + Cora.money(r.rejected) + '</td>' +
                '<td>' + Cora.money(r.average) + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">No companies yet.</td></tr>';
    });

    function loadQueue() {
        Cora.call(API, 'notifications').then(function (res) {
            if (!res.success) { return; }
            res.data.items.forEach(function (n) {
                var v = el('cora-dash-q-' + n.key);
                if (v) { v.textContent = n.count.toLocaleString(); }
            });
        });
    }

    function loadCompanies() {
        Cora.alertBox(alertEl, null);
        Cora.call(API, 'company_dashboard', { query: { month: monthEl.value, company_id: companyEl.value } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            var d = res.data;
            var t = companyEl.value && d.companies.length ? d.companies[0] : d.totals;
            var name = companyEl.value && d.companies.length ? d.companies[0].name : 'All companies';
            el('cora-dash-heading').textContent = name + ' - ' + d.month_label;
            el('cora-dash-k-enrolled').textContent = t.enrolled.toLocaleString();
            el('cora-dash-k-utilised').textContent = t.utilised.toLocaleString();
            el('cora-dash-k-claims').textContent = t.claims.toLocaleString();
            el('cora-dash-k-approved').textContent = Cora.money(t.approved);
            el('cora-dash-k-pending').textContent = Cora.money(t.pending);
            el('cora-dash-k-rejected').textContent = Cora.money(t.rejected);
            el('cora-dash-k-average').textContent = Cora.money(t.average);
            el('cora-dash-k-utilisation_rate').textContent = t.utilisation_rate + '%';

            var hr = el('cora-dash-hr');
            hr.hidden = t.enrolled === 0;
            hr.innerHTML = '<strong>HR summary:</strong> In ' + Cora.esc(d.month_label) + ', ' + t.utilisation_rate + '% of eligible employees' +
                (companyEl.value ? ' of ' + Cora.esc(name) : '') + ' utilised their pharmacy benefits, generating ' +
                Cora.money(t.approved) + ' approved utilisation.';
            pager.set(d.companies);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    Cora.call('companies/api.php', 'companies_options').then(function (res) {
        if (res.success) {
            companyEl.innerHTML += res.data.map(function (c) { return '<option value="' + c.id + '">' + Cora.esc(c.name) + '</option>'; }).join('');
        }
    });
    monthEl.addEventListener('change', loadCompanies);
    companyEl.addEventListener('change', loadCompanies);

    loadQueue();
    loadCompanies();
})();
