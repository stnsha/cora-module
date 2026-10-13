/**
 * Employee Master (employees/index.php).
 * employees/api.php: employees_list (?company_id, q, status) with the
 * eligibility status, employees_get, employees_create / employees_update /
 * employees_delete. ?company_id= in the URL preselects the company filter.
 * Add / Edit open employees/edit.php; Add carries the filtered company.
 */
(function () {
    'use strict';

    var API = 'employees/api.php';
    var el = function (id) { return document.getElementById(id); };
    var rowsEl = el('cora-emp-rows');
    var alertEl = el('cora-emp-alert');
    var qEl = el('cora-emp-q');
    var companyEl = el('cora-emp-company');
    var statusEl = el('cora-emp-status');
    var all = [];
    var timer = null;

    var pager = window.coraPager(el('cora-emp-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (r, i) {
            return '<tr data-id="' + r.id + '">' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td>' + Cora.esc(r.name) +
                    (r.restriction_type ? ' <i class="bi bi-exclamation-triangle-fill text-danger" title="' + (r.restriction_type === 2 ? 'Suspended' : 'Limit override') + '"></i>' : '') +
                    (r.department ? '<div class="text-muted small">' + Cora.esc(r.department) + '</div>' : '') + '</td>' +
                '<td>' + Cora.esc(r.ic_no) + '</td>' +
                '<td>' + Cora.esc(r.employee_no || '-') + '</td>' +
                '<td>' + Cora.esc(r.company_name) + '</td>' +
                '<td class="small">' + Cora.esc(r.start_date) + '<br>' + Cora.esc(r.end_date) + '</td>' +
                '<td>' + Cora.money(r.limit) + '</td>' +
                '<td>' + Cora.money(r.available) + '</td>' +
                '<td>' + Cora.pill(r.eligibility_pill, r.eligibility) + '</td>' +
                '<td class="text-end">' + Cora.kebab([
                    { act: 'edit', label: 'Edit' },
                    { act: 'claims', label: 'View claims' },
                    { divider: true },
                    { act: 'delete', label: 'Delete', danger: true }
                ]) + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">No employees found.</td></tr>';
    });

    function load() {
        Cora.alertBox(alertEl, null);
        Cora.call(API, 'employees_list', { query: { q: qEl.value.trim(), company_id: companyEl.value, status: statusEl.value } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); all = []; } else { all = res.data; }
            pager.set(all);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    var preset = new URLSearchParams(location.search).get('company_id') || '';
    Cora.call('companies/api.php', 'companies_list').then(function (res) {
        if (!res.success) { load(); return; }
        var opts = res.data.map(function (c) {
            return '<option value="' + c.id + '">' + Cora.esc(c.name) + (c.status === 2 ? ' (inactive)' : '') + '</option>';
        }).join('');
        companyEl.innerHTML += opts;
        if (preset) { companyEl.value = preset; }
        syncAddLink();
        load();
    });

    qEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 300); });
    companyEl.addEventListener('change', function () { syncAddLink(); load(); });
    statusEl.addEventListener('change', load);
    el('cora-emp-reset').addEventListener('click', function () {
        qEl.value = ''; companyEl.value = ''; statusEl.value = '';
        syncAddLink();
        load();
    });

    // Add employee preselects the company the list is filtered on.
    var addLink = el('cora-emp-add');
    function syncAddLink() {
        addLink.href = Cora.BASE + 'employees/edit.php' + (companyEl.value ? '?company_id=' + companyEl.value : '');
    }

    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var row = all.filter(function (r) { return r.id === id; })[0];
        switch (btn.getAttribute('data-act')) {
            case 'edit':
                location.href = Cora.BASE + 'employees/edit.php?id=' + id;
                break;
            case 'claims':
                location.href = Cora.BASE + 'reports/index.php?employee_q=' + encodeURIComponent(row.ic_no);
                break;
            case 'delete':
                window.coraConfirm('Delete ' + row.name + ' from the Employee Master? Past claims are kept. Prefer setting the employee Inactive.', function () {
                    Cora.call(API, 'employees_delete', { method: 'POST', body: { id: id } }).then(function (res) {
                        if (!res.success) { window.coraAlert(Cora.firstError(res), { variant: 'danger', title: 'Not deleted' }); return; }
                        load();
                    });
                });
                break;
        }
    });
})();
