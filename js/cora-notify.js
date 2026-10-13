/**
 * Navbar notification centre (every page, see navbar.php). Reads
 * dashboard/api.php?action=notifications - counters for the signed-in role
 * (admin: pending review, new today, resubmitted, awaiting outlet, uploads;
 * outlet: returned for amendment, drafts) - and refreshes every 60 seconds.
 */
(function () {
    'use strict';

    var BASE    = window.CORA_MODULE_BASE || '/odb/cora/';
    var badge   = document.getElementById('cora-notif-badge');
    var list    = document.getElementById('cora-notif-list');
    if (!badge || !list) { return; }

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(data) {
        var total = (data && data.total) || 0;
        badge.textContent = total > 99 ? '99+' : String(total);
        badge.classList.toggle('cora-hidden', total === 0);

        var items = (data && data.items) || [];
        if (!items.length) {
            list.innerHTML = '<div class="cora-notif-empty">No notifications.</div>';
            return;
        }
        list.innerHTML = items.map(function (n) {
            return '<a class="cora-notif-item d-flex justify-content-between align-items-center text-decoration-none text-reset' +
                (n.count > 0 ? ' cora-notif-item-unread' : '') + '" href="' + esc(BASE + n.href) + '">' +
                '<span class="cora-notif-item-snippet">' + esc(n.label) + '</span>' +
                '<span class="cora-pill ' + (n.count > 0 ? 'cora-pill-danger' : 'cora-pill-secondary') + '">' + esc(n.count) + '</span>' +
            '</a>';
        }).join('');
    }

    function refresh() {
        fetch(BASE + 'dashboard/api.php?action=notifications', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res && res.success) { render(res.data); } })
            .catch(function () { /* the bell stays as it was */ });
    }

    refresh();
    setInterval(refresh, 60000);
})();
