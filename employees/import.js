/**
 * Upload Employees (employees/import.php).
 * employees/api.php: import_validate (multipart file) -> the staged upload
 * and its summary; import_rows (?id, result) for the table; import_confirm /
 * import_discard; imports_list for Recent uploads. Confirm is only offered
 * once validation has completed and at least one row is valid.
 */
(function () {
    'use strict';

    var API = 'employees/api.php';
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-imp-alert');
    var resultEl = el('cora-imp-result');
    var filterEl = el('cora-imp-filter');
    var rowsEl = el('cora-imp-rows');
    var current = null;   // the import row

    var RESULTS = {
        valid_new:       { label: 'Valid - new employee', cls: 'cora-pill-success' },
        valid_update:    { label: 'Valid - updates existing', cls: 'cora-pill-info' },
        duplicate_ic:    { label: 'Duplicate IC', cls: 'cora-pill-danger' },
        invalid_date:    { label: 'Invalid dates', cls: 'cora-pill-danger' },
        missing_limit:   { label: 'Missing limit', cls: 'cora-pill-danger' },
        invalid_limit:   { label: 'Invalid limit', cls: 'cora-pill-danger' },
        unknown_company: { label: 'Unknown company', cls: 'cora-pill-warning' },
        missing_field:   { label: 'Missing field', cls: 'cora-pill-warning' }
    };
    var STATUS = { 1: ['Awaiting confirmation', 'cora-pill-warning'], 2: ['Confirmed', 'cora-pill-success'], 3: ['Discarded', 'cora-pill-secondary'] };

    filterEl.innerHTML = '<option value="">All rows</option>' + Object.keys(RESULTS).map(function (k) {
        return '<option value="' + k + '">' + Cora.esc(RESULTS[k].label) + '</option>';
    }).join('');

    var pager = window.coraPager(el('cora-imp-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (r, i) {
            var d = r.data || {};
            var res = RESULTS[r.result] || { label: r.result, cls: 'cora-pill-secondary' };
            return '<tr>' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td>' + r.row_no + '</td>' +
                '<td>' + Cora.esc(d.company) + '</td>' +
                '<td>' + Cora.esc(d.name) + '</td>' +
                '<td>' + Cora.esc(d.ic_no) + '</td>' +
                '<td>' + Cora.esc(d.raw_limit) + '</td>' +
                '<td>' + Cora.esc(d.raw_start) + '</td>' +
                '<td>' + Cora.esc(d.raw_end) + '</td>' +
                '<td>' + Cora.pill(res.cls, res.label) + (r.message ? '<div class="text-muted small">' + Cora.esc(r.message) + '</div>' : '') + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="9" class="text-center text-muted py-3">No rows.</td></tr>';
    });

    function showImport(imp) {
        current = imp;
        var s = imp.summary || {};
        var valid = (s.valid_new || 0) + (s.valid_update || 0);
        el('cora-imp-file-name').textContent = '- ' + imp.file_name;
        el('cora-imp-headline').innerHTML = '<strong>' + imp.total_rows.toLocaleString() + '</strong> records uploaded, <strong class="text-success">' +
            valid.toLocaleString() + '</strong> valid' + (imp.status === 1 ? '' : ' - ' + Cora.esc(STATUS[imp.status][0]));
        el('cora-imp-summary').innerHTML = Object.keys(RESULTS).filter(function (k) { return s[k]; }).map(function (k) {
            return '<li>' + Cora.pill(RESULTS[k].cls, s[k].toLocaleString()) + ' ' + Cora.esc(RESULTS[k].label) + '</li>';
        }).join('');
        var open = imp.status === 1;
        el('cora-imp-actions').hidden = !open;
        el('cora-imp-confirm').disabled = valid === 0;
        el('cora-imp-confirm').innerHTML = '<i class="bi bi-cloud-check"></i> Confirm ' + valid.toLocaleString() + ' valid row' + (valid === 1 ? '' : 's');
        resultEl.hidden = false;
        loadRows();
    }

    function loadRows() {
        if (!current) { return; }
        Cora.call(API, 'import_rows', { query: { id: current.id, result: filterEl.value } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            pager.set(res.data.rows);
        });
    }
    filterEl.addEventListener('change', loadRows);

    function loadRecent() {
        Cora.call(API, 'imports_list').then(function (res) {
            var list = (res.success && res.data) || [];
            el('cora-imp-recent').innerHTML = list.length ? list.map(function (i) {
                var st = STATUS[i.status] || ['-', 'cora-pill-secondary'];
                return '<tr data-id="' + i.id + '" style="cursor:pointer;" title="Show this upload">' +
                    '<td class="small">' + Cora.esc(i.file_name) + '<div class="text-muted">' + Cora.esc(i.created_at) + '</div></td>' +
                    '<td>' + i.total_rows + '</td><td>' + Cora.pill(st[1], st[0]) + '</td>' +
                    '<td class="small">' + Cora.esc(i.created_by_name || '-') + '</td></tr>';
            }).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">No uploads yet.</td></tr>';
        });
    }
    el('cora-imp-recent').addEventListener('click', function (e) {
        var tr = e.target.closest('tr[data-id]');
        if (!tr) { return; }
        Cora.call(API, 'import_rows', { query: { id: tr.getAttribute('data-id') } }).then(function (res) {
            if (res.success) { filterEl.value = ''; showImport(res.data.import); }
        });
    });

    el('cora-imp-form').addEventListener('submit', function (e) {
        e.preventDefault();
        Cora.alertBox(alertEl, null);
        var file = el('cora-imp-file').files[0];
        if (!file) { Cora.alertBox(alertEl, 'Choose a file first.'); return; }
        var fd = new FormData();
        fd.append('file', file);
        var btn = el('cora-imp-validate');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validating...';
        Cora.call(API, 'import_validate', { method: 'POST', form: fd }).then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-square"></i> Validate';
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            filterEl.value = '';
            showImport(res.data);
            loadRecent();
        }).catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-square"></i> Validate';
            Cora.alertBox(alertEl, 'Could not reach the server.');
        });
    });

    el('cora-imp-confirm').addEventListener('click', function () {
        if (!current) { return; }
        var s = current.summary || {};
        var valid = (s.valid_new || 0) + (s.valid_update || 0);
        var skipped = current.total_rows - valid;
        window.coraConfirm('Write ' + valid + ' valid row(s) to the Employee Master (' + (s.valid_new || 0) + ' new, ' + (s.valid_update || 0) + ' updated)?' +
            (skipped ? ' ' + skipped + ' invalid row(s) are skipped - fix them and upload them again.' : ''), function () {
            Cora.call(API, 'import_confirm', { method: 'POST', body: { id: current.id } }).then(function (res) {
                if (!res.success) { window.coraAlert(Cora.firstError(res), { variant: 'danger' }); return; }
                window.coraAlert(res.data.created + ' employee(s) created, ' + res.data.updated + ' updated.', { variant: 'success', title: 'Upload confirmed' });
                showImport(res.data);
                loadRecent();
            });
        }, { title: 'Confirm upload', variant: 'success', confirmLabel: 'Confirm' });
    });

    el('cora-imp-discard').addEventListener('click', function () {
        if (!current) { return; }
        window.coraConfirm('Discard this upload? Nothing is written to the Employee Master.', function () {
            Cora.call(API, 'import_discard', { method: 'POST', body: { id: current.id } }).then(function (res) {
                if (!res.success) { window.coraAlert(Cora.firstError(res), { variant: 'danger' }); return; }
                showImport(res.data);
                loadRecent();
            });
        });
    });

    loadRecent();
})();
