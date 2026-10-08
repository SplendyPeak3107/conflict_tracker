<?php
/** Profile + password change, for both reporters and the admin. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user  = require_login();
$admin = ($user['role'] === 'admin');
$uid   = (int) $user['id'];
$errors = [];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 20);

    if ($do === 'profile') {
        $d = [
            'name'            => post_str('name', 100),
            'phone'           => post_str('phone', 25),
            'community'       => $admin ? '' : post_str('community', 100),
            'emergency_name'  => $admin ? '' : post_str('emergency_name', 100),
            'emergency_phone' => $admin ? '' : post_str('emergency_phone', 25),
        ];
        if (mb_strlen($d['name'], 'UTF-8') < 2) {
            $errors[] = 'Please enter your full name.';
        }
        if (!preg_match('/^[0-9+\-\s()]{7,25}$/', $d['phone'])) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if ($d['emergency_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,25}$/', $d['emergency_phone'])) {
            $errors[] = 'The emergency contact phone number does not look right.';
        }
        if (!$errors) {
            update_profile($uid, $d);
            flash('success', 'Profile saved.');
            redirect('profile.php');
        }
    }

    if ($do === 'password') {
        $cur  = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
        $new  = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
        $new2 = is_string($_POST['new2'] ?? null) ? $_POST['new2'] : '';
        if (!password_verify($cur, $user['password_hash'])) {
            $errors[] = 'Your current password is not correct.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'The new password must be at least 8 characters.';
        } elseif ($new !== $new2) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            change_password($uid, $new);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }
    $user = find_user($uid); // show fresh values
}

$pageTitle = $admin ? 'My account' : 'Profile';
if ($admin) {
    $mainClass = 'narrow';
    include ROOT_PATH . '/includes/layout/admin_header.php';
} else {
    include ROOT_PATH . '/includes/layout/header.php';
}
?>
<?php if ($admin): render_flashes(); endif; ?>
<h1><?= $admin ? 'My account' : 'Your profile' ?></h1>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>

<section class="card pad">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="profile">
    <label>Email <span class="opt">cannot be changed</span>
      <input type="email" value="<?= e($user['email']) ?>" disabled>
    </label>
    <label>Full name
      <input type="text" name="name" value="<?= e($user['name']) ?>" required maxlength="100">
    </label>
    <label>Phone number
      <input type="tel" name="phone" value="<?= e($user['phone']) ?>" required maxlength="25">
    </label>
    <?php if (!$admin): ?>
    <label>Village or community
      <input type="text" name="community" value="<?= e($user['community']) ?>" maxlength="100">
    </label>
    <fieldset>
      <legend>Emergency contact</legend>
      <label>Contact name
        <input type="text" name="emergency_name" value="<?= e($user['emergency_name']) ?>" maxlength="100">
      </label>
      <label>Contact phone
        <input type="tel" name="emergency_phone" value="<?= e($user['emergency_phone']) ?>" maxlength="25">
      </label>
    </fieldset>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Save profile</button>
  </form>
</section>

<section class="card pad">
  <h2>Change password</h2>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="password">
    <label>Current password
      <input type="password" name="current" required autocomplete="current-password">
    </label>
    <label>New password <span class="opt">at least 8 characters</span>
      <input type="password" name="new" required minlength="8" autocomplete="new-password">
    </label>
    <label>Repeat new password
      <input type="password" name="new2" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</section>
<?php
if ($admin) {
    include ROOT_PATH . '/includes/layout/admin_footer.php';
} else {
    include ROOT_PATH . '/includes/layout/footer.php';
}
