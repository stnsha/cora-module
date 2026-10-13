/**
 * Company Master (companies/index.php).
 * companies/api.php: companies_list, companies_get (with resolved rules),
 * companies_create / companies_update / companies_delete (Super Admin),
 * clients_search / clients_get (Corporate Client List picker). Corporate
 * Admins open the same modal read-only (window.CORA_CAN_EDIT = false).
 */
(function () {
    'use strict';

    var API = 'companies/api.php';
    var CAN_EDIT = !!window.CORA_CAN_EDIT;
    var el = function (id) { return document.getElementById(id); };
    var rowsEl = el('cora-co-rows');
    var alertEl = el('cora-co-alert');
    var modal = new bootstrap.Modal(el('cora-co-modal'));
    var all = [];
    var clients = [];
    var searchTimer = null;

    var F = {
        id: el('cora-co-id'), clientId: el('cora-co-client-id'), client: el('cora-co-client'), clientResults: el('cora-co-client-results'),
        clientNote: el('cora-co-client-note'), name: el('cora-co-name'), code: el('cora-co-code'), category: el('cora-co-category'),
        status: el('cora-co-status-f'), limit: el('cora-co-limit'), mode: el('cora-co-mode'), start: el('cora-co-start'),
        end: el('cora-co-end'), criteria: el('cora-co-criteria'), remark: el('cora-co-remark'), hrName: el('cora-co-hr-name'),
        hrEmail: el('cora-co-hr-email'), inherited: el('cora-co-inherited'), error: el('cora-co-error'), title: el('cora-co-title')
    };

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

    // ---- inherited rules preview ----

    function showInherited(client) {
        if (!client) {
            F.inherited.innerHTML = 'Link a client to see its rules.';
            F.clientNote.textContent = 'Not linked - rules come only from this form and the Default Alpro rule.';
            return;
        }
        F.clientNote.textContent = 'Linked to client list #' + client.id + (client.client_status ? ' (' + client.client_status + ')' : '') + '.';
        F.inherited.innerHTML =
            '<p class="mb-2"><strong>Category (TPA):</strong> ' + Cora.esc(client.category || '-') + '</p>' +
            '<p class="mb-1"><strong>Benefit / claim criteria:</strong></p><p class="mb-2">' + Cora.lines(client.benefit_criteria || '-') + '</p>' +
            '<p class="mb-1"><strong>General remark:</strong></p><p class="mb-0">' + (client.general_remark ? Cora.lines(client.general_remark) : '-') + '</p>';
    }

    function closeResults() { F.clientResults.hidden = true; }
    F.client.addEventListener('input', function () {
        clearTimeout(searchTimer);
        var q = F.client.value.trim();
        if (!q) { F.clientId.value = ''; showInherited(null); closeResults(); return; }
        if (q.length < 2) { closeResults(); return; }
        searchTimer = setTimeout(function () {
            Cora.call(API, 'clients_search', { query: { q: q } }).then(function (res) {
                clients = (res.success && res.data) || [];
                F.clientResults.innerHTML = clients.length ? clients.map(function (c, i) {
                    return '<li role="option" data-i="' + i + '"><span class="cora-users-results-name">' + Cora.esc(c.client_name) + '</span>' +
                        '<span class="cora-users-results-meta">' + Cora.esc(c.category || '-') + ' &middot; ' + Cora.esc(c.client_status || '') + '</span></li>';
                }).join('') : '<li class="cora-users-results-empty">No client matches "' + Cora.esc(q) + '".</li>';
                F.clientResults.hidden = false;
            });
        }, 250);
    });
    F.clientResults.addEventListener('mousedown', function (e) {
        var li = e.target.closest('li[data-i]');
        if (!li) { return; }
        e.preventDefault();
        var c = clients[parseInt(li.getAttribute('data-i'), 10)];
        F.clientId.value = c.id;
        F.client.value = c.client_name;
        if (!F.name.value.trim()) { F.name.value = c.client_name; }
        showInherited(c);
        closeResults();
    });
    F.client.addEventListener('blur', function () { setTimeout(closeResults, 150); });

    // ---- modal ----

    function open(c) {
        F.error.hidden = true;
        F.title.textContent = c ? (CAN_EDIT ? 'Edit company' : 'Company') : 'Add company';
        F.id.value = c ? c.id : '';
        F.clientId.value = c && c.client_id ? c.client_id : '';
        F.client.value = c && c.rules && c.rules.client ? c.rules.client.client_name : '';
        F.name.value = c ? c.name : '';
        F.code.value = c && c.code ? c.code : '';
        F.category.value = c && c.category ? c.category : '';
        F.status.value = String(c ? c.status : 1);
        F.limit.value = c && c.default_limit !== null ? Number(c.default_limit).toFixed(2) : '';
        F.mode.value = String(c ? c.limit_mode : 1);
        F.start.value = c ? Cora.toInputDate(c.period_start) : '';
        F.end.value = c ? Cora.toInputDate(c.period_end) : '';
        F.criteria.value = c && c.benefit_criteria ? c.benefit_criteria : '';
        F.remark.value = c && c.general_remark ? c.general_remark : '';
        F.hrName.value = c && c.hr_contact_name ? c.hr_contact_name : '';
        F.hrEmail.value = c && c.hr_contact_email ? c.hr_contact_email : '';
        showInherited(c && c.rules ? c.rules.client : null);
        [].forEach.call(el('cora-co-form').querySelectorAll('input, select, textarea'), function (i) { i.disabled = !CAN_EDIT; });
        modal.show();
    }

    var addBtn = el('cora-co-add');
    if (addBtn) { addBtn.addEventListener('click', function () { open(null); }); }

    el('cora-co-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!CAN_EDIT) { return; }
        var id = F.id.value;
        var body = {
            id: id || undefined,
            client_id: F.clientId.value || null,
            name: F.name.value, code: F.code.value, category: F.category.value, status: parseInt(F.status.value, 10),
            default_limit: F.limit.value, limit_mode: parseInt(F.mode.value, 10), period_start: F.start.value, period_end: F.end.value,
            benefit_criteria: F.criteria.value, general_remark: F.remark.value, hr_contact_name: F.hrName.value, hr_contact_email: F.hrEmail.value
        };
        Cora.call(API, id ? 'companies_update' : 'companies_create', { method: 'POST', body: body }).then(function (res) {
            if (!res.success) { F.error.textContent = Cora.firstError(res); F.error.hidden = false; return; }
            modal.hide();
            load();
        }).catch(function () { F.error.textContent = 'Could not reach the server.'; F.error.hidden = false; });
    });

    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var row = all.filter(function (c) { return c.id === id; })[0];
        switch (btn.getAttribute('data-act')) {
            case 'edit':
                Cora.call(API, 'companies_get', { query: { id: id } }).then(function (res) {
                    if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
                    open(res.data);
                });
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
