
<?php
require __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('Security token invalid. Page refresh karke dobara try karein.');
        redirect('admin/settings.php');
    }

    set_setting(
        'site_name',
        trim($_POST['site_name'] ?? '') ?: 'TulipsChat'
    );

    set_setting(
        'allow_register',
        !empty($_POST['allow_register']) ? '1' : '0'
    );

    set_setting(
        'max_length',
        (string) max(50, min(5000, (int)($_POST['max_length'] ?? 500)))
    );

    set_setting(
        'history',
        (string) max(10, min(200, (int)($_POST['history'] ?? 50)))
    );

    set_setting(
        'announcement',
        mb_substr(trim($_POST['announcement'] ?? ''), 0, 500)
    );

    $youtubeApiKey = trim($_POST['youtube_api_key'] ?? '');

    if ($youtubeApiKey !== '') {
        set_setting('youtube_api_key', $youtubeApiKey);
    }

    // KLIPY: blank field purani key ko preserve karega.
    $klipyApiKey = trim($_POST['klipy_api_key'] ?? '');

    if ($klipyApiKey !== '') {
        set_setting('klipy_api_key', $klipyApiKey);
    }

    flash('Settings save ho gayi.');
    redirect('admin/settings.php');
}

admin_head('Settings', 'settings.php');
echo flash();

$youtubeKeySaved = setting('youtube_api_key') !== '';
$klipyKeySaved = setting('klipy_api_key') !== '';
?>

<form class="card narrow" method="post">
  <?= csrf_field() ?>

  <label>
    Site name
    <input
      name="site_name"
      value="<?= e(setting('site_name', 'TulipsChat')) ?>"
    >
  </label>

  <label class="check">
    <input
      type="checkbox"
      name="allow_register"
      value="1"
      <?= setting('allow_register') === '1' ? 'checked' : '' ?>
    >
    Naye users register kar sakte hain
  </label>

  <label>
    Message max length
    <input
      type="number"
      name="max_length"
      min="50"
      max="5000"
      value="<?= e(setting('max_length', '500')) ?>"
    >
  </label>

  <label>
    Chat kholne par kitne purane messages dikhen
    <input
      type="number"
      name="history"
      min="10"
      max="200"
      value="<?= e(setting('history', '50')) ?>"
    >
  </label>

  <label>
    Announcement (chat ke upar)
    <textarea name="announcement" rows="3"><?= e(setting('announcement')) ?></textarea>
  </label>

  <hr>

  <h3>YouTube Add-on Settings</h3>

  <label>
    YouTube Data API v3 Key
    <input
      type="password"
      name="youtube_api_key"
      placeholder="<?= $youtubeKeySaved
        ? 'API key saved — blank chhodein to same rahegi'
        : 'YouTube API key yahan paste karein' ?>"
      autocomplete="new-password"
    >
  </label>

  <p class="muted">
    <?= $youtubeKeySaved
      ? 'YouTube key saved hai. Change karne ke liye nayi key enter karein.'
      : 'YouTube key abhi configure nahi hui hai.' ?>
  </p>

  <hr>

  <h3>KLIPY GIF Add-on Settings</h3>

  <label>
    KLIPY App API Key
    <input
      type="password"
      name="klipy_api_key"
      placeholder="<?= $klipyKeySaved
        ? 'KLIPY key saved — blank chhodein to same rahegi'
        : 'KLIPY App Key yahan paste karein' ?>"
      autocomplete="new-password"
    >
  </label>

  <p class="muted">
    <?= $klipyKeySaved
      ? 'KLIPY key saved hai. Change karne ke liye nayi key enter karein.'
      : 'KLIPY key save karne ke baad GIF search configure hogi.' ?>
  </p>

  <button type="submit">Save Settings</button>
</form>

<?php admin_foot(); ?>
