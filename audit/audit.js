/**
 * Audit Log (audit/index.php).
 * audit/api.php: audit_list (?entity, entity_id, q, date_from, date_to) and
 * audit_get (?id) for the changes modal ({ field: [before, after] }).
 */
(function () {
    'use strict';

    var API = 'audit/api.php';
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-au-alert');
    var rowsEl = el('cora-au-rows');
    var modal = new bootstrap.Modal(el('cora-au-modal'));
    var F = { entity: el('cora-au-entity'), id: el('cora-au-id'), q: el('cora-au-q'), from: el('cora-au-from'), to: el('cora-au-to') };
    var all = [];
    var timer = null;

    var ENTITY = { claim: 'Claim', employee: 'Employee', company: 'Company', import: 'Upload', setting: 'Setting', user: 'User' };

    function label(action) {
        return String(action || '').replace(/_/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); });
    }

    function recordLink(a) {
        var text = (ENTITY[a.entity] || a.entity) + (a.entity_id ? ' #' + a.entity_id : '');
        if (a.entity === 'claim' && a.entity_id) {
            return '<a href="' + Cora.BASE + 'claims/view.php?id=' + a.entity_id + '">' + Cora.esc(text) + '</a>';
        }
        return Cora.esc(text);
    }

    var pager = window.coraPager(el('cora-au-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (a, i) {
            var n = a.changes ? Object.keys(a.changes).length : 0;
            return '<tr data-id="' + a.id + '">' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td class="small">' + Cora.esc(a.created_at) + '</td>' +
                '<td>' + Cora.esc(a.staff_name || '-') + '</td>' +
                '<td>' + recordLink(a) + '</td>' +
                '<td>' + Cora.esc(label(a.action)) + '</td>' +
                '<td class="small">' + Cora.esc(a.remark || '') + '</td>' +
                '<td>' + (n ? n + ' field' + (n === 1 ? '' : 's') : '-') + '</td>' +
                '<td class="text-end">' + (n ? Cora.kebab([{ act: 'changes', label: 'View changes' }]) : '') + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No audit entries.</td></tr>';
    });

    function load() {
        Cora.alertBox(alertEl, null);
        Cora.call(API, 'audit_list', { query: {
            entity: F.entity.value, entity_id: F.id.value, q: F.q.value.trim(), date_from: F.from.value, date_to: F.to.value
        } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); all = []; } else { all = res.data; }
            pager.set(all);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    function fmt(v) {
        if (v === null || v === undefined || v === '') { return '<span class="text-muted">-</span>'; }
        if (typeof v === 'object') { return '<code>' + Cora.esc(JSON.stringify(v)) + '</code>'; }
        return Cora.lines(String(v));
    }

    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act="changes"]');
        if (!btn) { return; }
        var id = parseInt(btn.closest('tr').getAttribute('data-id'), 10);
        var a = all.filter(function (r) { return r.id === id; })[0];
        if (!a) { return; }
        el('cora-au-modal-title').textContent = label(a.action) + ' - ' + (ENTITY[a.entity] || a.entity) + (a.entity_id ? ' #' + a.entity_id : '');
        el('cora-au-modal-meta').textContent = (a.staff_name || '-') + ' - ' + a.created_at + (a.ip_address ? ' - ' + a.ip_address : '');
        el('cora-au-changes').innerHTML = Object.keys(a.changes || {}).map(function (k) {
            return '<tr><td>' + Cora.esc(k.replace(/_/g, ' ')) + '</td><td>' + fmt(a.changes[k][0]) + '</td><td>' + fmt(a.changes[k][1]) + '</td></tr>';
        }).join('');
        modal.show();
    });

    [F.entity, F.from, F.to].forEach(function (i) { i.addEventListener('change', load); });
    [F.q, F.id].forEach(function (i) { i.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 300); }); });
    el('cora-au-reset').addEventListener('click', function () {
        F.entity.value = ''; F.id.value = ''; F.q.value = ''; F.from.value = ''; F.to.value = '';
        load();
    });

    load();
})();
