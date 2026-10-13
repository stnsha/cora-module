<?php
/**
 * CORA base layout - bottom partial.
 * Closes the container opened in header.php, loads Bootstrap, the shared
 * scripts (dialogs, pager, helpers, notification centre) and the optional
 * page scripts: $page_js_before (optional list of module scripts) then
 * $page_js.
 */
?>
    </div><!-- /.cora-container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php foreach (['js/cora-confirm.js', 'js/cora-pager.js', 'js/cora-common.js', 'js/cora-notify.js'] as $_shared_js): ?>
    <script src="<?php echo CORA_BASE . $_shared_js; ?>?v=<?php echo filemtime(__DIR__ . '/' . $_shared_js); ?>"></script>
    <?php endforeach; ?>
    <?php foreach ($page_js_before ?? [] as $_before_js): ?>
    <script src="<?php echo CORA_BASE . $_before_js; ?>?v=<?php echo filemtime(__DIR__ . '/' . $_before_js); ?>"></script>
    <?php endforeach; ?>
    <?php if (!empty($page_js)): ?>
    <?php $_page_js_file = __DIR__ . '/' . substr($page_js, strlen(CORA_BASE)); ?>
    <script src="<?php echo $page_js; ?>?v=<?php echo is_file($_page_js_file) ? filemtime($_page_js_file) : time(); ?>"></script>
    <?php endif; ?>
</body>

</html>
