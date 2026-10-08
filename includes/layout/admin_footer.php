<?php
/** Closes <main>; loads Leaflet (if requested) and page scripts. */
$localLeaflet = is_file(ROOT_PATH . '/public/assets/vendor/leaflet/leaflet.js');
?>
</main>
<?php if (!empty($leaflet)): ?>
<script src="<?= $localLeaflet ? e(asset('vendor/leaflet/leaflet.js')) : 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js' ?>"></script>
<?php endif; ?>
<?php foreach (($scripts ?? []) as $s): ?>
<script src="<?= e(asset($s)) ?>"<?= isset($scriptAttrs[$s]) ? ' ' . $scriptAttrs[$s] : '' ?>></script>
<?php endforeach; ?>
</body>
</html>
