<?php
require __DIR__ . '/core.php';
if (current_user()) redirect('chat.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0);
    if (!csrf_ok()) $err = 'Session expire ho gaya, dobara try karein';
    elseif ($_SESSION['login_tries'] >= 5 && time() - ($_SESSION['login_last'] ?? 0) < 300) $err = 'Bohat zyada try kiye, 5 minute baad try karein';
    else {
        $st = db()->prepare('SELECT * FROM users WHERE username=?');
        $st->execute([$name]);
        $u = $st->fetch();
        if (!$u || !password_verify($pass, $u['password'])) {
            $_SESSION['login_tries']++; $_SESSION['login_last'] = time();
            $err = 'Username ya password ghalat hai';
        } elseif ($u['status'] === 'banned') {
            $err = 'Aap ka account ban hai';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['login_tries'] = 0;
            db()->prepare('UPDATE users SET last_seen=?, last_ip=? WHERE id=?')
                ->execute([time(), $_SERVER['REMOTE_ADDR'] ?? '', $u['id']]);
            redirect('chat.php');
        }
    }
}
page_head('Login');
?>
<div class="auth-box">
  <h1><?= e(setting('site_name', 'TulipsChat')) ?></h1>
  <?php if (isset($_GET['installed'])): ?><div class="alert ok">Install ho gaya. Ab install.php delete kar dein.</div><?php endif; ?>
  <?php if (isset($_GET['registered'])): ?><div class="alert ok">Account ban gaya, login karein.</div><?php endif; ?>
  <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username<input name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus></label>
    <label>Password<input type="password" name="password" required></label>
    <button>Login</button>
  </form>
  <?php if (setting('allow_register', '1') === '1'): ?>
    <p class="muted center">Account nahi hai? <a href="register.php">Register</a></p>
  <?php endif; ?>
</div>
<?php page_foot();
