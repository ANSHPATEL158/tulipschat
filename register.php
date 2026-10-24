<?php
require __DIR__ . '/core.php';
if (current_user()) redirect('chat.php');
if (setting('allow_register', '1') !== '1') { page_head('Register'); echo '<div class="auth-box"><div class="alert">Registration band hai.</div><a href="login.php">Login</a></div>'; page_foot(); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : '';
    if (!csrf_ok()) $err = 'Session expire ho gaya, dobara try karein';
    elseif (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $name)) $err = 'Username 3-20 letters/numbers/_ hona chahiye';
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email sahi nahi hai';
    elseif (strlen($pass) < 6) $err = 'Password kam se kam 6 characters';
    elseif ($pass !== ($_POST['password2'] ?? '')) $err = 'Dono password match nahi kar rahe';
    else {
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username)=LOWER(?)');
        $st->execute([$name]);
        if ($st->fetchColumn()) $err = 'Ye username pehle se le liya gaya hai';
        else {
            db()->prepare('INSERT INTO users (username, email, password, gender, last_ip, created_at) VALUES (?,?,?,?,?,?)')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $gender, $_SERVER['REMOTE_ADDR'] ?? '', time()]);
            redirect('login.php?registered=1');
        }
    }
}
page_head('Register');
?>
<div class="auth-box">
  <h1>Register</h1>
  <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username<input name="username" value="<?= e($_POST['username'] ?? '') ?>" required></label>
    <label>Email (optional)<input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Gender
      <select name="gender"><option value="">--</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select>
    </label>
    <label>Password<input type="password" name="password" required></label>
    <label>Password dobara<input type="password" name="password2" required></label>
    <button>Register</button>
  </form>
  <p class="muted center">Account hai? <a href="login.php">Login</a></p>
</div>
<?php page_foot();
