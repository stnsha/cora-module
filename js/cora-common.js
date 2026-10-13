/**
 * Shared helpers for CORA page scripts (loaded before each page's own
 * script). Same conventions as edm's page scripts:
 *   - JSON API per module folder: <folder>/api.php?action=...
 *     -> { success: true, data } | { success: false, message, errors? }
 *   - status pills use .cora-pill + cora-pill-* (never Bootstrap .badge)
 *   - row actions live in a vertical-ellipsis (kebab) dropdown
 *   - dates arrive formatted (d-m-Y / d-m-Y H:i:s) and are shown as given
 *
 *   Cora.call('claims/api.php', 'claims_list', { query: { status: 2 } })
 *   Cora.call('claims/api.php', 'claims_submit', { method: 'POST', body: {...} })
 */
(function () {
    'use strict';

    var BASE = window.CORA_MODULE_BASE || '/odb/cora/';

    // Claim statuses (Models\Claim::STATUSES) - integers, never words.
    var CLAIM_STATUS = {
        1: { label: 'Draft', cls: 'cora-pill-secondary' },
        2: { label: 'Submitted', cls: 'cora-pill-info' },
        3: { label: 'Pending Review', cls: 'cora-pill-warning' },
        4: { label: 'Approved', cls: 'cora-pill-success' },
        5: { label: 'Rejected', cls: 'cora-pill-danger' },
        6: { label: 'Cancelled / Void', cls: 'cora-pill-dark' },
        7: { label: 'Amendment Required', cls: 'cora-pill-primary' }
    };

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function query(params) {
        var parts = [];
        Object.keys(params || {}).forEach(function (k) {
            var v = params[k];
            if (v !== undefined && v !== null && v !== '') {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
            }
        });
        return parts.length ? '&' + parts.join('&') : '';
    }

    function call(api, action, opts) {
        opts = opts || {};
        var init = {
            method: opts.method || 'GET',
            headers: { 'Accept': 'application/json' }
        };
        if (opts.form) {
            init.body = opts.form;               // FormData (file upload)
        } else if (opts.body) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        return fetch(BASE + api + '?action=' + encodeURIComponent(action) + query(opts.query), init)
            .then(function (r) {
                return r.json().catch(function () { return { success: false, message: 'Unexpected server response (' + r.status + ').' }; });
            });
    }

    function url(api, action, params) {
        return BASE + api + '?action=' + encodeURIComponent(action) + query(params);
    }

    function firstError(res) {
        if (res && res.errors) {
            for (var k in res.errors) {
                if (Object.prototype.hasOwnProperty.call(res.errors, k)) { return res.errors[k][0]; }
            }
        }
        return (res && res.message) || 'Request failed.';
    }

    function money(v) {
        var n = Number(v || 0);
        return 'RM' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function pill(cls, label) {
        return '<span class="cora-pill ' + esc(cls) + '">' + esc(label) + '</span>';
    }

    function claimPill(status) {
        var s = CLAIM_STATUS[status] || { label: String(status), cls: 'cora-pill-secondary' };
        return pill(s.cls, s.label);
    }

    /**
     * Kebab row menu. items: [{ act, label, danger?, divider? }]
     */
    function kebab(items) {
        var html = items.map(function (it) {
            if (it.divider) { return '<li><hr class="dropdown-divider"></li>'; }
            return '<li><button type="button" class="dropdown-item' + (it.danger ? ' text-danger' : '') + '" data-act="' + esc(it.act) + '">' + esc(it.label) + '</button></li>';
        }).join('');
        return '<div class="dropdown">' +
            '<button type="button" class="cora-row-kebab" data-bs-toggle="dropdown" aria-expanded="false"' +
                ' data-bs-popper-config=\'{"strategy":"fixed"}\' aria-label="Actions" title="Actions">' +
                '<i class="bi bi-three-dots-vertical"></i></button>' +
            '<ul class="dropdown-menu dropdown-menu-end cora-row-menu">' + html + '</ul>' +
        '</div>';
    }

    function alertBox(el, msg, kind) {
        if (!el) { return; }
        if (!msg) { el.hidden = true; return; }
        el.className = 'alert alert-' + (kind || 'danger') + ' py-2 px-3 small';
        el.textContent = msg;
        el.hidden = false;
    }

    /** Multi-line plain text -> escaped HTML with <br>. */
    function lines(v) {
        return esc(v).replace(/\n/g, '<br>');
    }

    function today() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    /** 'd-m-Y' (API display) -> 'Y-m-d' (date input). */
    function toInputDate(v) {
        var m = /^(\d{2})-(\d{2})-(\d{4})/.exec(v || '');
        return m ? m[3] + '-' + m[2] + '-' + m[1] : (v || '');
    }

    window.Cora = {
        BASE: BASE,
        CLAIM_STATUS: CLAIM_STATUS,
        esc: esc,
        call: call,
        url: url,
        firstError: firstError,
        money: money,
        pill: pill,
        claimPill: claimPill,
        kebab: kebab,
        alertBox: alertBox,
        lines: lines,
        today: today,
        toInputDate: toInputDate
    };
})();
