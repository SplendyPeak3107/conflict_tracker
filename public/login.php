<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$email = '';

if (is_post()) {
    csrf_check();
    $email = mb_strtolower(post_str('email', 150), 'UTF-8');
    $pass  = $_POST['password'] ?? '';

    // Basic brute-force slow-down (per browser session)
    if (!empty($_SESSION['lock_until']) && $_SESSION['lock_until'] > time()) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } else {
        $u = $email !== '' ? find_user_by_email($email) : null;
        if ($u && is_string($pass) && password_verify($pass, $u['password_hash'])) {
            unset($_SESSION['fails'], $_SESSION['lock_until']);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                change_password((int) $u['id'], $pass);
            }
            login_user($u);
            redirect('index.php');
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['lock_until'] = time() + 60;
            $_SESSION['fails'] = 0;
        }
        $error = 'Wrong email or password.';
    }
}

$pageTitle = 'Log in';
$bodyClass = 'auth';
$guest = true;
include ROOT_PATH . '/includes/layout/header.php';
?>
<section class="auth-card">
  <h1>Log in</h1>
  <p class="muted">You only need to do this once. After that this phone stays logged in, so reporting is one tap.</p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
    </label>
    <label>Password
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>
  <p class="muted center">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</section>
<?php include ROOT_PATH . '/includes/layout/footer.php'; ?>
