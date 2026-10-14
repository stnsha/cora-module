/**
 * Monthly Claim Report (reports/index.php).
 * reports/api.php: report_list (filters) and report_export (&format=csv|xlsx,
 * a file download - opened as a plain navigation). ?employee_q= in the URL
 * prefills the employee search (Employee Master "View claims") and clears
 * the month so every period is shown.
 */
(function () {
    'use strict';

    var API = 'reports/api.php';
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-rp-alert');
    var rowsEl = el('cora-rp-rows');
    var F = {
        month: el('cora-rp-month'), company: el('cora-rp-company'), outlet: el('cora-rp-outlet'),
        q: el('cora-rp-q'), status: el('cora-rp-status'), method: el('cora-rp-method')
    };

    var preset = new URLSearchParams(location.search).get('employee_q');
    if (preset) { F.q.value = preset; F.month.value = ''; }

    var pager = window.coraPager(el('cora-rp-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (r, i) {
            return '<tr>' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td><a href="' + Cora.BASE + 'claims/view.php?id=' + r.id + '">' + Cora.esc(r.employee_name) + '</a><div class="text-muted small">' + Cora.esc(r.claim_no) + '</div></td>' +
                '<td>' + Cora.esc(r.employee_ic) + (r.employee_no ? '<div class="text-muted small">' + Cora.esc(r.employee_no) + '</div>' : '') + '</td>' +
                '<td>' + Cora.esc(r.company_name) + '</td>' +
                '<td>' + Cora.esc(r.outlet_name) + '</td>' +
                '<td>' + Cora.esc(r.visit_date) + '</td>' +
                '<td>' + Cora.esc(r.so_no) + '</td>' +
                '<td>' + Cora.money(r.claim_amount) + '</td>' +
                '<td>' + Cora.esc(r.payment_method_label) + '</td>' +
                '<td>' + Cora.claimPill(r.status) + '</td>' +
                '<td class="small">' + Cora.esc(r.approval_date || '-') + '</td>' +
                '<td class="small">' + Cora.esc(r.approved_by || '-') + '</td>' +
                '<td class="small">' + Cora.esc(r.rejection_remark || '-') + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="13" class="text-center text-muted py-4">No claims for these filters.</td></tr>';
    });

    function filters() {
        return {
            month: F.month.value, company_id: F.company.value, outlet_id: F.outlet.value,
            q: F.q.value.trim(), status: F.status.value, payment_method: F.method.value
        };
    }

    function load() {
        Cora.alertBox(alertEl, null);
        Cora.call(API, 'report_list', { query: filters() }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); pager.set([]); return; }
            var rows = res.data.rows;
            var sum = function (key, statuses) {
                return rows.filter(function (r) { return !statuses || statuses.indexOf(r.status) !== -1; })
                    .reduce(function (s, r) { return s + Number(r[key] || 0); }, 0);
            };
            el('cora-rp-totals').innerHTML = rows.length.toLocaleString() + ' claim(s)' +
                ' &middot; Approved ' + Cora.money(sum('claim_amount', [4])) +
                ' &middot; Pending ' + Cora.money(sum('claim_amount', [2, 3, 7])) +
                ' &middot; Rejected ' + Cora.money(sum('claim_amount', [5])) +
                (res.data.limited ? ' &middot; <strong>showing the newest rows only - export for the full list</strong>' : '');
            el('cora-rp-xlsx').disabled = !res.data.xlsx;
            el('cora-rp-xlsx').title = res.data.xlsx ? '' : 'Excel export needs PhpSpreadsheet (composer install in cora/). Use CSV.';
            pager.set(rows);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    Cora.call('companies/api.php', 'companies_options').then(function (res) {
        if (res.success) {
            F.company.innerHTML += res.data.map(function (c) { return '<option value="' + c.id + '">' + Cora.esc(c.name) + '</option>'; }).join('');
        }
    });

    el('cora-rp-run').addEventListener('click', load);
    F.q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { load(); } });
    el('cora-rp-reset').addEventListener('click', function () {
        var d = new Date();
        F.month.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        F.company.value = ''; F.outlet.value = ''; F.q.value = ''; F.status.value = ''; F.method.value = '';
        load();
    });
    /*
     * The file is fetched (not opened with location.href) so the page knows
     * when it is ready: a small loading bar shows and both buttons are
     * disabled until the download starts. A JSON reply means an error.
     */
    function exportReport(format) {
        var buttons = [el('cora-rp-csv'), el('cora-rp-xlsx')];
        var bar = el('cora-rp-loading');
        var busy = function (on) {
            bar.hidden = !on;
            buttons.forEach(function (b) { b.disabled = on; });
        };
        busy(true);
        fetch(Cora.url(API, 'report_export', Object.assign({ format: format }, filters())), { credentials: 'same-origin' })
            .then(function (res) {
                var type = res.headers.get('Content-Type') || '';
                if (!res.ok || type.indexOf('application/json') === 0) {
                    return res.json().catch(function () { return {}; }).then(function (body) {
                        throw new Error(body.message || 'The export could not be created.');
                    });
                }
                var match = /filename="([^"]+)"/.exec(res.headers.get('Content-Disposition') || '');
                return res.blob().then(function (blob) {
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = match ? match[1] : 'cora_claim_report.' + format;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
                });
            })
            .catch(function (err) {
                window.coraAlert(err.message || 'Could not reach the server.', { variant: 'danger' });
            })
            .then(function () { busy(false); });
    }
    el('cora-rp-csv').addEventListener('click', function () { exportReport('csv'); });
    el('cora-rp-xlsx').addEventListener('click', function () { exportReport('xlsx'); });

    load();
})();
