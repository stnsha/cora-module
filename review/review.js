/**
 * Claim Review queue (review/index.php) - Submitted (2) and Pending Review
 * (3) claims, oldest first. claims_list with status=2,3 (+ today=1 /
 * resubmitted=1); the row menu opens claims/view.php?review=1.
 * ?today=1 / ?resubmitted=1 in the URL preselect the filter (notification links).
 */
(function () {
    'use strict';

    var el = function (id) { return document.getElementById(id); };
    var rowsEl = el('cora-rv-rows');
    var alertEl = el('cora-rv-alert');
    var qEl = el('cora-rv-q');
    var companyEl = el('cora-rv-company');
    var showEl = el('cora-rv-show');
    var timer = null;

    var params = new URLSearchParams(location.search);
    if (params.get('today') === '1') { showEl.value = 'today'; }
    if (params.get('resubmitted') === '1') { showEl.value = 'resubmitted'; }

    var pager = window.coraPager(el('cora-rv-pager'), function (rows, start) {
        rowsEl.innerHTML = rows.length ? rows.map(function (c, i) {
            var flagged = c.flag_labels && c.flag_labels.length;
            return '<tr data-id="' + c.id + '"' + (flagged ? ' class="table-warning"' : '') + '>' +
                '<td class="text-muted">' + (start + i + 1) + '</td>' +
                '<td>' + Cora.esc(c.employee_name) + '<div class="text-muted small">' + Cora.esc(c.claim_no) + '</div></td>' +
                '<td>' + Cora.esc(c.company_name) + '</td>' +
                '<td>' + Cora.esc(c.outlet_name) + '</td>' +
                '<td>' + Cora.esc(c.visit_date) + '</td>' +
                '<td>' + Cora.esc(c.so_no) + (c.duplicate_authorised ? ' <i class="bi bi-files text-warning" title="Authorised duplicate SO"></i>' : '') + '</td>' +
                '<td><strong>' + Cora.money(c.claim_amount) + '</strong>' +
                    (flagged ? ' <i class="bi bi-flag-fill text-warning" title="' + Cora.esc(c.flag_labels.join('; ')) + '"></i>' : '') + '</td>' +
                '<td>' + (c.remaining === null ? '-' : Cora.money(c.remaining)) + '</td>' +
                '<td class="small">' + Cora.esc(c.submitted_at || '') + (c.resubmit_count ? '<div>' + Cora.pill('cora-pill-primary', 'resubmitted') + '</div>' : '') + '</td>' +
                '<td>' + Cora.claimPill(c.status) + '</td>' +
                '<td class="text-end">' + Cora.kebab([{ act: 'review', label: 'Review' }]) + '</td>' +
            '</tr>';
        }).join('') : '<tr><td colspan="11" class="text-center text-muted py-4">No claims waiting for review.</td></tr>';
    });

    function load() {
        Cora.alertBox(alertEl, null);
        var show = showEl.value;
        Cora.call('claims/api.php', 'claims_list', { query: {
            status: '2,3', q: qEl.value.trim(), company_id: companyEl.value,
            today: show === 'today' ? 1 : '', resubmitted: show === 'resubmitted' ? 1 : ''
        } }).then(function (res) {
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); pager.set([]); return; }
            var rows = res.data.slice().reverse();   // oldest submission first
            if (show === 'flagged') { rows = rows.filter(function (r) { return r.flag_labels && r.flag_labels.length; }); }
            pager.set(rows);
        }).catch(function () { Cora.alertBox(alertEl, 'Could not reach the server.'); });
    }

    Cora.call('companies/api.php', 'companies_options').then(function (res) {
        if (res.success) {
            companyEl.innerHTML += res.data.map(function (c) { return '<option value="' + c.id + '">' + Cora.esc(c.name) + '</option>'; }).join('');
        }
    });
    qEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 300); });
    companyEl.addEventListener('change', load);
    showEl.addEventListener('change', load);
    el('cora-rv-reset').addEventListener('click', function () {
        qEl.value = '';
        companyEl.value = '';
        showEl.value = '';
        load();
    });
    rowsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act="review"]');
        if (btn) { location.href = Cora.BASE + 'claims/view.php?review=1&id=' + btn.closest('tr').getAttribute('data-id'); }
    });

    load();
})();
