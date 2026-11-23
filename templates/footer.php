<?php
/**
 * Page footer and scripts.
 * Expects: $pageScripts (list of ['src' => ..., 'integrity' => ...], integrity only for CDN files)
 */
?>
</main>
<footer class="site-footer">
    <div class="container">PV-Monitor – Demo mit simulierten Messdaten</div>
</footer>

<!-- integrity: the browser refuses the file if the CDN delivers modified code -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs"
        crossorigin="anonymous"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($pageScripts ?? [] as $script): ?>
    <?php if (isset($script['integrity'])): ?>
        <script src="<?= e($script['src']) ?>" integrity="<?= e($script['integrity']) ?>" crossorigin="anonymous"></script>
    <?php else: ?>
        <script src="<?= e($script['src']) ?>"></script>
    <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
