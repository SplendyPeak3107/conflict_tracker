<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $do = post_str('do', 20);
    if ($do === 'add') {
        $body = post_str('body', 500);
        if ($body !== '') {
            add_quick_reply($body);
            audit_log((int) $admin['id'], 'quick_reply_added', null, null, short_text($body, 200));
            flash('success', 'Quick reply added.');
        }
    } elseif ($do === 'delete') {
        delete_quick_reply((int) ($_POST['id'] ?? 0));
        audit_log((int) $admin['id'], 'quick_reply_deleted', null, null, 'id ' . (int) ($_POST['id'] ?? 0));
        flash('success', 'Quick reply removed.');
    }
    redirect('admin/quick_replies.php');
}

$list = get_quick_replies();
$pageTitle = 'Quick replies';
$mainClass = 'narrow';
include ROOT_PATH . '/includes/layout/admin_header.php';
render_flashes();
?>
<h1>Quick replies</h1>
<p class="muted">Ready-made messages you can send to a reporter with one click on a case page.</p>

<section class="card pad">
  <ul class="plain">
  <?php foreach ($list as $q): ?>
    <li class="qr-row">
      <span><?= e($q['body']) ?></span>
      <form method="post" onsubmit="return confirm('Remove this quick reply?');">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
        <button class="btn btn-small btn-danger" type="submit">Remove</button>
      </form>
    </li>
  <?php endforeach; ?>
  <?php if (!$list): ?><li class="muted">No quick replies yet.</li><?php endif; ?>
  </ul>
</section>

<section class="card pad">
  <h2>Add a quick reply</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="add">
    <label>Message (up to 500 characters)
      <textarea name="body" rows="3" maxlength="500" required></textarea>
    </label>
    <button class="btn btn-primary" type="submit">Add</button>
  </form>
</section>
<?php include ROOT_PATH . '/includes/layout/admin_footer.php'; ?>
