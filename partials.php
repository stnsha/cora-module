<?php
/**
 * Shared view partials for CORA screens. Included by a page before or after
 * header.php. Pairs with js/cora-crud.js (copied from edm) for plain list +
 * modal screens; bespoke screens build the same markup by hand.
 */

if (!function_exists('cora_h')) {
    /** HTML-escape helper. */
    function cora_h(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cora_title_button')) {
    /**
     * Primary "New X" button for the page-title row (atem / edm style):
     * assign to $page_title_actions before `include header.php`.
     */
    function cora_title_button(string $label, string $id, string $icon = 'bi-plus-lg'): string
    {
        return '<button type="button" class="btn btn-primary d-inline-flex align-items-center" id="'
            . cora_h($id) . '"><i class="bi ' . cora_h($icon) . ' me-1"></i>' . cora_h($label) . '</button>';
    }
}

if (!function_exists('cora_table')) {
    /**
     * Listing table in atem's view.php style: leading # column, .cora-view-tbl,
     * Action column, pager below.
     *
     * @param string $prefix id prefix: tbody = {prefix}-rows, pager = {prefix}-pager
     * @param list<string> $columns header labels between # and Action
     * @param bool $action whether an Action column is rendered
     */
    function cora_table(string $prefix, array $columns, bool $action = true): void
    {
        $colspan = count($columns) + 1 + ($action ? 1 : 0);
        ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle cora-view-tbl">
                <thead>
                    <tr>
                        <th style="width:3rem;">#</th>
                        <?php foreach ($columns as $label): ?>
                        <th><?php echo cora_h($label); ?></th>
                        <?php endforeach; ?>
                        <?php if ($action): ?>
                        <th class="text-end">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="<?php echo cora_h($prefix); ?>-rows">
                    <tr><td colspan="<?php echo $colspan; ?>" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="cora-pager" id="<?php echo cora_h($prefix); ?>-pager"></div>
        <?php
    }
}

if (!function_exists('cora_script')) {
    /** <script> tag for a module JS file with a filemtime cache-buster. */
    function cora_script(string $relative): string
    {
        $file = __DIR__ . '/' . $relative;

        return '<script src="' . CORA_BASE . cora_h($relative) . '?v=' . (is_file($file) ? filemtime($file) : time()) . '"></script>';
    }
}
