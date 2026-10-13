/**
 * Add / edit company (companies/edit.php, ?id=N to edit).
 * companies/api.php: companies_get (with resolved rules), companies_create /
 * companies_update (Super Admin), clients_search (Corporate Client List
 * picker). Corporate Admins see the page read-only (window.CORA_CAN_EDIT =
 * false); without an id they are sent back to the list. Save returns to the
 * Company Master.
 */
(function () {
    'use strict';

    var API = 'companies/api.php';
    var LIST = Cora.BASE + 'companies/index.php';
    var CAN_EDIT = !!window.CORA_CAN_EDIT;
    var el = function (id) { return document.getElementById(id); };
    var form = el('cora-co-form');
    var alertEl = el('cora-co-alert');
    var clients = [];
    var searchTimer = null;

    var F = {
        id: el('cora-co-id'), clientId: el('cora-co-client-id'), client: el('cora-co-client'), clientResults: el('cora-co-client-results'),
        clientNote: el('cora-co-client-note'), name: el('cora-co-name'), code: el('cora-co-code'), category: el('cora-co-category'),
        status: el('cora-co-status-f'), limit: el('cora-co-limit'), mode: el('cora-co-mode'), start: el('cora-co-start'),
        end: el('cora-co-end'), criteria: el('cora-co-criteria'), remark: el('cora-co-remark'), hrName: el('cora-co-hr-name'),
        hrEmail: el('cora-co-hr-email'), inherited: el('cora-co-inherited'), error: el('cora-co-error')
    };

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

    // ---- form ----

    function fill(c) {
        F.clientId.value = c.client_id || '';
        F.client.value = c.rules && c.rules.client ? c.rules.client.client_name : '';
        F.name.value = c.name;
        F.code.value = c.code || '';
        F.category.value = c.category || '';
        F.status.value = String(c.status);
        F.limit.value = c.default_limit !== null ? Number(c.default_limit).toFixed(2) : '';
        F.mode.value = String(c.limit_mode);
        F.start.value = Cora.toInputDate(c.period_start);
        F.end.value = Cora.toInputDate(c.period_end);
        F.criteria.value = c.benefit_criteria || '';
        F.remark.value = c.general_remark || '';
        F.hrName.value = c.hr_contact_name || '';
        F.hrEmail.value = c.hr_contact_email || '';
        showInherited(c.rules ? c.rules.client : null);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!CAN_EDIT) { return; }
        F.error.hidden = true;
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
            location.href = LIST;
        }).catch(function () { F.error.textContent = 'Could not reach the server.'; F.error.hidden = false; });
    });

    if (!CAN_EDIT) {
        if (!F.id.value) { location.replace(LIST); return; }
        var title = document.querySelector('.cora-page-title');
        if (title) { title.textContent = 'Company'; }
        [].forEach.call(form.querySelectorAll('input, select, textarea'), function (i) { i.disabled = true; });
    }

    if (F.id.value) {
        Cora.call(API, 'companies_get', { query: { id: F.id.value } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); form.hidden = true; return; }
            fill(res.data);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }
})();
