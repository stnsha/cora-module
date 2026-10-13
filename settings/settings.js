/**
 * Settings > Default Rules (settings/index.php) - Super Admin.
 * settings/api.php: settings_get / settings_save.
 */
(function () {
    'use strict';

    var API = 'settings/api.php';
    var el = function (id) { return document.getElementById(id); };
    var alertEl = el('cora-set-alert');

    function fill(d) {
        el('cora-set-limit').value = Number(d.default_limit).toFixed(2);
        el('cora-set-mode').value = String(d.default_limit_mode);
        el('cora-set-criteria').value = d.default_benefit_criteria || '';
        el('cora-set-types').value = (d.claim_types || []).join('\n');
    }

    Cora.call(API, 'settings_get').then(function (res) {
        if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
        fill(res.data);
    });

    el('cora-set-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = el('cora-set-save');
        btn.disabled = true;
        Cora.call(API, 'settings_save', { method: 'POST', body: {
            default_limit: el('cora-set-limit').value,
            default_limit_mode: parseInt(el('cora-set-mode').value, 10),
            default_benefit_criteria: el('cora-set-criteria').value,
            claim_types: el('cora-set-types').value.split('\n').map(function (t) { return t.trim(); }).filter(Boolean)
        } }).then(function (res) {
            btn.disabled = false;
            if (!res.success) { Cora.alertBox(alertEl, Cora.firstError(res)); return; }
            fill(res.data);
            Cora.alertBox(alertEl, 'Default rules saved.', 'success');
        }).catch(function () {
            btn.disabled = false;
            Cora.alertBox(alertEl, 'Could not reach the server.');
        });
    });
})();
