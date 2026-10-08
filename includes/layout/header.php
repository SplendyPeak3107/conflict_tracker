<?php
/**
 * Reporter / guest page header.
 * Before including, a page may set: $pageTitle, $bodyClass, $guest (true for login/register).
 */
$user   = current_user();
$guest  = $guest ?? false;
$unread = ($user && !$guest) ? unread_count((int) $user['id']) : 0;
$here   = basename($_SERVER['SCRIPT_NAME']);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0e3b38">
<title><?= e($pageTitle ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/reporter.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="topbar">
  <a class="brand" href="<?= e(url($user && !$guest ? 'dashboard.php' : 'login.php')) ?>"><span class="brand-mark"></span><?= e(APP_NAME) ?></a>
<?php if ($user && !$guest): ?>
  <nav class="nav" aria-label="Main">
    <a href="<?= e(url('dashboard.php')) ?>" class="<?= $here === 'dashboard.php' ? 'on' : '' ?>">Home</a>
    <a href="<?= e(url('history.php')) ?>" class="<?= $here === 'history.php' ? 'on' : '' ?>">History</a>
    <a href="<?= e(url('notifications.php')) ?>" class="<?= $here === 'notifications.php' ? 'on' : '' ?>">Alerts<span class="count" data-live="notif-badge"><?= $unread > 0 ? e($unread) : '' ?></span></a>
    <a href="<?= e(url('profile.php')) ?>" class="<?= $here === 'profile.php' ? 'on' : '' ?>">Profile</a>
    <form method="post" action="<?= e(url('logout.php')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="linkbtn">Log out</button></form>
  </nav>
<?php endif; ?>
</header>
<main class="wrap">
<?php render_flashes(); ?>
