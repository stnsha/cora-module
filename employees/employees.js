/**
 * Employee Master (employees/index.php).
 * employees/api.php: employees_list (?company_id, q, status) with the
 * eligibility status, employees_get, employees_create / employees_update /
 * employees_delete. ?company_id= in the URL preselects the company filter.
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
    var modal = new bootstrap.Modal(el('cora-emp-modal'));
    var all = [];
    var timer = null;

    var F = {
        id: el('cora-emp-id'), company: el('cora-emp-company-f'), status: el('cora-emp-status-f'), name: el('cora-emp-name'),
        ic: el('cora-emp-ic'), no: el('cora-emp-no'), phone: el('cora-emp-phone'), dept: el('cora-emp-dept'),
        address: el('cora-emp-address'), limit: el('cora-emp-limit'), start: el('cora-emp-start'), end: el('cora-emp-end'),
        info: el('cora-emp-info'), restriction: el('cora-emp-restriction'), override: el('cora-emp-override'),
        until: el('cora-emp-override-until'), rRemark: el('cora-emp-restriction-remark'), error: el('cora-emp-error'), title: el('cora-emp-title')
    };

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
        F.company.innerHTML = '<option value="">Choose a company</option>' + opts;
        if (preset) { companyEl.value = preset; }
        load();
    });

    qEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 300); });
    companyEl.addEventListener('change', load);
    statusEl.addEventListener('change', load);
    el('cora-emp-reset').addEventListener('click', function () {
        qEl.value = ''; companyEl.value = ''; statusEl.value = '';
        load();
    });

    function syncRestriction() {
        var t = parseInt(F.restriction.value, 10);
        [].forEach.call(document.querySelectorAll('[data-override]'), function (d) { d.hidden = t !== 1; });
        [].forEach.call(document.querySelectorAll('[data-restriction]'), function (d) { d.hidden = t === 0; });
    }
    F.restriction.addEventListener('change', syncRestriction);

    function open(e) {
        F.error.hidden = true;
        F.title.textContent = e ? 'Edit employee' : 'Add employee';
        F.id.value = e ? e.id : '';
        F.company.value = e ? String(e.company_id) : (companyEl.value || '');
        F.status.value = String(e ? e.status : 1);
        F.name.value = e ? e.name : '';
        F.ic.value = e ? e.ic_no : '';
        F.no.value = e && e.employee_no ? e.employee_no : '';
        F.phone.value = e && e.phone ? e.phone : '';
        F.dept.value = e && e.department ? e.department : '';
        F.address.value = e && e.address ? e.address : '';
        F.limit.value = e ? Number(e.entitlement_limit).toFixed(2) : '';
        F.start.value = e ? Cora.toInputDate(e.start_date) : '';
        F.end.value = e ? Cora.toInputDate(e.end_date) : '';
        F.info.value = e && e.info_remark ? e.info_remark : '';
        F.restriction.value = String(e ? e.restriction_type : 0);
        F.override.value = e && e.override_limit !== null ? Number(e.override_limit).toFixed(2) : '';
        F.until.value = e ? Cora.toInputDate(e.override_until) : '';
        F.rRemark.value = e && e.restriction_remark ? e.restriction_remark : '';
        syncRestriction();
        modal.show();
    }

    el('cora-emp-add').addEventListener('click', function () { open(null); });

    el('cora-emp-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var id = F.id.value;
        var body = {
            id: id || undefined,
            company_id: F.company.value, status: parseInt(F.status.value, 10), name: F.name.value, ic_no: F.ic.value,
            employee_no: F.no.value, phone: F.phone.value, department: F.dept.value, address: F.address.value,
            entitlement_limit: F.limit.value, start_date: F.start.value, end_date: F.end.value, info_remark: F.info.value,
            restriction_type: parseInt(F.restriction.value, 10), override_limit: F.override.value, override_until: F.until.value,
            restriction_remark: F.rRemark.value
        };
        Cora.call(API, id ? 'employees_update' : 'employees_create', { method: 'POST', body: body }).then(function (res) {
            if (!res.success) { F.error.textContent = Cora.firstError(res); F.error.hidden = false; return; }
            modal.hide();
            load();
        }).catch(function () { F.error.textContent = 'Could not reach the server.'; F.error.hidden = false; });
    });

    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var row = all.filter(function (r) { return r.id === id; })[0];
        switch (btn.getAttribute('data-act')) {
            case 'edit':
                Cora.call(API, 'employees_get', { query: { id: id } }).then(function (res) {
                    if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
                    open(res.data);
                });
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
