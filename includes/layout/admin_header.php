<?php
/**
 * Admin page header.
 * Optional before including: $pageTitle, $bodyClass, $leaflet (true to load the map library).
 */
$user   = current_user();
$here   = basename($_SERVER['SCRIPT_NAME']);
$leaflet = $leaflet ?? false;
$localLeaflet = is_file(ROOT_PATH . '/public/assets/vendor/leaflet/leaflet.js');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0e3b38">
<title><?= e($pageTitle ?? 'Admin') ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php if ($leaflet): ?>
<link rel="stylesheet" href="<?= $localLeaflet ? e(asset('vendor/leaflet/leaflet.css')) : 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' ?>">
<?php endif; ?>
</head>
<body class="admin <?= e($bodyClass ?? '') ?>">
<header class="topbar">
  <a class="brand" href="<?= e(url('admin/dashboard.php')) ?>"><span class="brand-mark"></span><?= e(APP_NAME) ?> <em>Control room</em></a>
  <nav class="nav" aria-label="Admin">
    <a href="<?= e(url('admin/dashboard.php')) ?>" class="<?= $here === 'dashboard.php' ? 'on' : '' ?>">Live map</a>
    <a href="<?= e(url('admin/users.php')) ?>" class="<?= in_array($here, ['users.php', 'user.php'], true) ? 'on' : '' ?>">Reporters</a>
    <a href="<?= e(url('admin/quick_replies.php')) ?>" class="<?= $here === 'quick_replies.php' ? 'on' : '' ?>">Quick replies</a>
    <a href="<?= e(url('admin/audit_log.php')) ?>" class="<?= $here === 'audit_log.php' ? 'on' : '' ?>">Audit log</a>
    <a href="<?= e(url('profile.php')) ?>" class="<?= $here === 'profile.php' ? 'on' : '' ?>">My account</a>
    <form method="post" action="<?= e(url('logout.php')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="linkbtn">Log out</button></form>
  </nav>
</header>
<main class="admin-main <?= e($mainClass ?? '') ?>">
