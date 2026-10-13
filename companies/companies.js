/**
 * Company Master (companies/index.php).
 * companies/api.php: companies_list, companies_get (with resolved rules),
 * companies_create / companies_update / companies_delete (Super Admin),
 * clients_search / clients_get (Corporate Client List picker). Add / Edit
 * open companies/edit.php; Corporate Admins get View (read-only there).
 */
(function () {
    'use strict';

    var API = 'companies/api.php';
    var CAN_EDIT = !!window.CORA_CAN_EDIT;
    var el = function (id) { return document.getElementById(id); };
    var rowsEl = el('cora-co-rows');
    var alertEl = el('cora-co-alert');
    var all = [];

    var pager = window.coraPager(el('cora-co-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (c, i) {
            var items = [{ act: 'edit', label: CAN_EDIT ? 'Edit' : 'View' },
                { act: 'employees', label: 'View employees' }];
            if (CAN_EDIT) {
                items.push({ act: 'toggle', label: c.status === 1 ? 'Set inactive' : 'Set active' }, { divider: true }, { act: 'delete', label: 'Delete', danger: true });
            }
            return '<tr data-id="' + c.id + '">' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td>' + Cora.esc(c.name) + '</td>' +
                '<td>' + Cora.esc(c.code || '-') + '</td>' +
                '<td>' + (c.client_name ? Cora.esc(c.client_name) : '<span class="text-muted">Not linked</span>') + '</td>' +
                '<td>' + (c.default_limit !== null ? Cora.money(c.default_limit) : '<span class="text-muted">Default</span>') + '</td>' +
                '<td>' + (c.limit_mode === 2 ? Cora.pill('cora-pill-warning', 'Warn') : Cora.pill('cora-pill-secondary', 'Block')) + '</td>' +
                '<td>' + c.employee_count.toLocaleString() + '</td>' +
                '<td>' + (c.status === 1 ? Cora.pill('cora-pill-success', 'Active') : Cora.pill('cora-pill-secondary', 'Inactive')) + '</td>' +
                '<td class="text-end">' + Cora.kebab(items) + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No companies yet.</td></tr>';
    });

    function load() {
        Cora.call(API, 'companies_list').then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            all = res.data;
            filter();
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    function filter() {
        var q = el('cora-co-q').value.trim().toLowerCase();
        var st = el('cora-co-status').value;
        pager.set(all.filter(function (c) {
            return (!q || (c.name + ' ' + (c.code || '')).toLowerCase().indexOf(q) !== -1) && (!st || String(c.status) === st);
        }));
    }
    el('cora-co-q').addEventListener('input', filter);
    el('cora-co-status').addEventListener('change', filter);

    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var row = all.filter(function (c) { return c.id === id; })[0];
        switch (btn.getAttribute('data-act')) {
            case 'edit':
                location.href = Cora.BASE + 'companies/edit.php?id=' + id;
                break;
            case 'employees':
                location.href = Cora.BASE + 'employees/index.php?company_id=' + id;
                break;
            case 'toggle':
                Cora.call(API, 'companies_get', { query: { id: id } }).then(function (res) {
                    if (!res.success) { return; }
                    var c = res.data;
                    var body = {
                        id: id, client_id: c.client_id, name: c.name, code: c.code, category: c.category, status: c.status === 1 ? 2 : 1,
                        default_limit: c.default_limit, limit_mode: c.limit_mode, period_start: c.period_start, period_end: c.period_end,
                        benefit_criteria: c.benefit_criteria, general_remark: c.general_remark, hr_contact_name: c.hr_contact_name, hr_contact_email: c.hr_contact_email
                    };
                    Cora.call(API, 'companies_update', { method: 'POST', body: body }).then(function (r2) {
                        if (!r2.success) { window.coraAlert(Cora.firstError(r2), { variant: 'danger' }); return; }
                        load();
                    });
                });
                break;
            case 'delete':
                window.coraConfirm('Delete ' + row.name + '? Only a company with no employees and no claims can be deleted.', function () {
                    Cora.call(API, 'companies_delete', { method: 'POST', body: { id: id } }).then(function (res) {
                        if (!res.success) { window.coraAlert(Cora.firstError(res), { variant: 'danger', title: 'Not deleted' }); return; }
                        load();
                    });
                });
                break;
        }
    });

    load();
})();
