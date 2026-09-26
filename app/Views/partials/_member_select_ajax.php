<?php

/**
 * AJAX member picker (Select2) backed by GET /member-search.
 *
 * Emits a <select> that is populated on demand — no roster is rendered into
 * the page, so it stays light no matter how large the parish is.
 *
 * Required variables:
 *   - select_id     string  DOM id for the <select>
 *
 * Optional variables:
 *   - name          string  field name (default: 'user_ids[]' when multiple, else 'user_id')
 *   - multiple      bool    default false
 *   - members_only  bool    default true; false also returns visitor/regular_attender people
 *   - placeholder   string  input placeholder
 *   - selected      array<int, array{id: int, text: string}>  preselected options
 *   - limit         int     results per search (default 20, max 50)
 *   - search_url    string  endpoint (default: member-search)
 *   - mark_no_portal bool   append "no portal access" note for users without portal access
 *   - required      bool    add the required attribute (default false)
 */

$selectId        = (string) ($select_id ?? '');
$isMultiple      = (bool) ($multiple ?? false);
$fieldName       = (string) ($name ?? ($isMultiple ? 'user_ids[]' : 'user_id'));
$membersOnly     = (bool) ($members_only ?? true);
$placeholderText = (string) ($placeholder ?? lang('Attendance.selectMembersPlaceholder'));
$searchLimit     = max(1, min((int) ($limit ?? 20), 50));
$selectedOptions = (array) ($selected ?? []);
$searchUrl       = (string) ($search_url ?? base_url('member-search'));
$markNoPortal    = (bool) ($mark_no_portal ?? false);
?>
<select id="<?= esc($selectId, 'attr') ?>"
        name="<?= esc($fieldName, 'attr') ?>"
        class="form-select"
        <?= $isMultiple ? 'multiple' : '' ?>
        <?= !empty($required) ? 'required' : '' ?>
        data-placeholder="<?= esc($placeholderText, 'attr') ?>">
    <?php if (!$isMultiple): ?>
        <option value=""><?= esc($placeholderText) ?></option>
    <?php endif; ?>
    <?php foreach ($selectedOptions as $opt): ?>
        <option value="<?= (int) ($opt['id'] ?? 0) ?>" selected><?= esc((string) ($opt['text'] ?? '')) ?></option>
    <?php endforeach; ?>
</select>

<script>
(function() {
    var selectId = <?= json_encode($selectId) ?>;

    function withSelect2(cb) {
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            cb();
            return;
        }
        if (!document.getElementById('select2-css')) {
            var link = document.createElement('link');
            link.id = 'select2-css';
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
            document.head.appendChild(link);
        }
        if (!document.getElementById('select2-js')) {
            var s = document.createElement('script');
            s.id = 'select2-js';
            s.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
            s.onload = cb;
            document.body.appendChild(s);
            return;
        }
        var poll = setInterval(function() {
            if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                clearInterval(poll);
                cb();
            }
        }, 50);
    }

    function init() {
        var el = document.getElementById(selectId);
        if (!el || !window.jQuery) {
            return;
        }
        withSelect2(function() {
            jQuery(el).select2({
                width: '100%',
                placeholder: el.getAttribute('data-placeholder') || '',
                allowClear: <?= $isMultiple ? 'false' : 'true' ?>,
                ajax: {
                    url: <?= json_encode($searchUrl) ?>,
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || '',
                            limit: <?= $searchLimit ?>,
                            members_only: <?= $membersOnly ? '1' : '0' ?>
                        };
                    },
                    processResults: function(data) {
                        var members = (data && data.members) || [];
                        return {
                            results: members.map(function(m) {
                                var text = m.full_name || ('#' + m.id);
                                if (m.email) {
                                    text += ' (' + m.email + ')';
                                }
                                <?php if ($markNoPortal): ?>
                                if (m.user_active === false || (m.portal_access !== null && m.portal_access !== 'enabled')) {
                                    text += ' — <?= esc(lang('Leadership.noPortalAccess'), 'js') ?>';
                                }
                                <?php endif; ?>
                                return { id: m.id, text: text };
                            })
                        };
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
