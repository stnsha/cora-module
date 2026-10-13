/**
 * Add / edit employee (employees/edit.php, ?id=N to edit, ?company_id=N to
 * preselect the company when adding).
 * employees/api.php: employees_get, employees_create / employees_update;
 * companies/api.php companies_list for the Company select. Save returns to
 * the Employee Master.
 */
(function () {
    'use strict';

    var API = 'employees/api.php';
    var LIST = Cora.BASE + 'employees/index.php';
    var el = function (id) { return document.getElementById(id); };
    var form = el('cora-emp-form');
    var alertEl = el('cora-emp-alert');

    var F = {
        id: el('cora-emp-id'), company: el('cora-emp-company-f'), status: el('cora-emp-status-f'), name: el('cora-emp-name'),
        ic: el('cora-emp-ic'), no: el('cora-emp-no'), phone: el('cora-emp-phone'), dept: el('cora-emp-dept'),
        address: el('cora-emp-address'), limit: el('cora-emp-limit'), start: el('cora-emp-start'), end: el('cora-emp-end'),
        info: el('cora-emp-info'), restriction: el('cora-emp-restriction'), override: el('cora-emp-override'),
        until: el('cora-emp-override-until'), rRemark: el('cora-emp-restriction-remark'), error: el('cora-emp-error')
    };

    function syncRestriction() {
        var t = parseInt(F.restriction.value, 10);
        [].forEach.call(document.querySelectorAll('[data-override]'), function (d) { d.hidden = t !== 1; });
        [].forEach.call(document.querySelectorAll('[data-restriction]'), function (d) { d.hidden = t === 0; });
    }
    F.restriction.addEventListener('change', syncRestriction);

    function fill(e) {
        F.company.value = String(e.company_id);
        F.status.value = String(e.status);
        F.name.value = e.name;
        F.ic.value = e.ic_no;
        F.no.value = e.employee_no || '';
        F.phone.value = e.phone || '';
        F.dept.value = e.department || '';
        F.address.value = e.address || '';
        F.limit.value = Number(e.entitlement_limit).toFixed(2);
        F.start.value = Cora.toInputDate(e.start_date);
        F.end.value = Cora.toInputDate(e.end_date);
        F.info.value = e.info_remark || '';
        F.restriction.value = String(e.restriction_type);
        F.override.value = e.override_limit !== null ? Number(e.override_limit).toFixed(2) : '';
        F.until.value = Cora.toInputDate(e.override_until);
        F.rRemark.value = e.restriction_remark || '';
        syncRestriction();
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        F.error.hidden = true;
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
            location.href = LIST;
        }).catch(function () { F.error.textContent = 'Could not reach the server.'; F.error.hidden = false; });
    });

    syncRestriction();
    Cora.call('companies/api.php', 'companies_list').then(function (res) {
        if (res.success) {
            F.company.innerHTML += res.data.map(function (c) {
                return '<option value="' + c.id + '">' + Cora.esc(c.name) + (c.status === 2 ? ' (inactive)' : '') + '</option>';
            }).join('');
        }
        if (!F.id.value) {
            F.company.value = el('cora-emp-preset-company').value;
            return;
        }
        Cora.call(API, 'employees_get', { query: { id: F.id.value } }).then(function (r2) {
            if (!r2.success) { Cora.alertBox(alertEl, Cora.firstError(r2)); form.hidden = true; return; }
            fill(r2.data);
        });
    }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
})();
