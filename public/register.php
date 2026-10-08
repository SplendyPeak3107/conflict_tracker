<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$d = ['name' => '', 'email' => '', 'phone' => '', 'community' => '', 'emergency_name' => '', 'emergency_phone' => ''];

if (is_post()) {
    csrf_check();
    foreach ($d as $k => $_) {
        $d[$k] = post_str($k, $k === 'email' ? 150 : 100);
    }
    $d['email'] = mb_strtolower($d['email'], 'UTF-8');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $pass  = is_string($pass) ? $pass : '';
    $pass2 = is_string($pass2) ? $pass2 : '';

    if (mb_strlen($d['name'], 'UTF-8') < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (find_user_by_email($d['email'])) {
        $errors[] = 'That email is already registered. Try logging in.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,25}$/', $d['phone'])) {
        $errors[] = 'Please enter a valid phone number (digits only, you may start with +).';
    }
    if ($d['emergency_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,25}$/', $d['emergency_phone'])) {
        $errors[] = 'The emergency contact phone number does not look right.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $d['password'] = $pass;
        $id = create_reporter($d);
        $u = find_user($id);
        login_user($u);
        flash('success', 'Welcome to ' . APP_NAME . '. Your account is ready.');
        redirect('dashboard.php');
    }
}

$pageTitle = 'Create account';
$bodyClass = 'auth';
$guest = true;
include ROOT_PATH . '/includes/layout/header.php';
?>
<section class="auth-card wide">
  <h1>Create your account</h1>
  <p class="muted">Your name and phone number let the officer reach you. Your location is only taken when you press Report.</p>
  <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label>Full name
      <input type="text" name="name" value="<?= e($d['name']) ?>" required maxlength="100" autocomplete="name">
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($d['email']) ?>" required maxlength="150" autocomplete="email">
    </label>
    <label>Phone number
      <input type="tel" name="phone" value="<?= e($d['phone']) ?>" required maxlength="25" autocomplete="tel">
    </label>
    <label>Village or community <span class="opt">optional</span>
      <input type="text" name="community" value="<?= e($d['community']) ?>" maxlength="100">
    </label>
    <fieldset>
      <legend>Emergency contact <span class="opt">optional</span></legend>
      <label>Contact name
        <input type="text" name="emergency_name" value="<?= e($d['emergency_name']) ?>" maxlength="100">
      </label>
      <label>Contact phone
        <input type="tel" name="emergency_phone" value="<?= e($d['emergency_phone']) ?>" maxlength="25">
      </label>
    </fieldset>
    <label>Password <span class="opt">at least 8 characters</span>
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label>Repeat password
      <input type="password" name="password2" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
  </form>
  <p class="muted center">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</section>
<?php include ROOT_PATH . '/includes/layout/footer.php'; ?>
