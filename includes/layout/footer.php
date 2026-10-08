<?php
/** Closes <main>. Pages may set $scripts = ['js/file.js', ...] before including. */
?>
</main>
<?php foreach (($scripts ?? []) as $s): ?>
<script src="<?= e(strpos($s, 'http') === 0 ? $s : asset($s)) ?>"<?= isset($scriptAttrs[$s]) ? ' ' . $scriptAttrs[$s] : '' ?>></script>
<?php endforeach; ?>
</body>
</html>
