<?php
require __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    set_setting('site_name', trim($_POST['site_name'] ?? '') ?: 'TulipsChat');
    set_setting('allow_register', !empty($_POST['allow_register']) ? '1' : '0');
    set_setting('max_length', (string)max(50, min(5000, (int)($_POST['max_length'] ?? 500))));
    set_setting('history', (string)max(10, min(200, (int)($_POST['history'] ?? 50))));
    set_setting('announcement', mb_substr(trim($_POST['announcement'] ?? ''), 0, 500));
    flash('Settings save ho gayi');
    redirect('admin/settings.php');
}
admin_head('Settings', 'settings.php');
echo flash();
?>
<form class="card narrow" method="post">
  <?= csrf_field() ?>
  <label>Site name<input name="site_name" value="<?= e(setting('site_name')) ?>"></label>
  <label class="check"><input type="checkbox" name="allow_register" value="1" <?= setting('allow_register') === '1' ? 'checked' : '' ?>> Naye users register kar sakte hain</label>
  <label>Message max length<input type="number" name="max_length" value="<?= e(setting('max_length', '500')) ?>"></label>
  <label>Chat kholne par kitne purane messages dikhen<input type="number" name="history" value="<?= e(setting('history', '50')) ?>"></label>
  <label>Announcement (chat ke upar)<textarea name="announcement" rows="3"><?= e(setting('announcement')) ?></textarea></label>
  <button>Save</button>
</form>
<?php admin_foot();
