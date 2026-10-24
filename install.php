<?php
require __DIR__ . '/core.php';

if (installed()) { echo 'Already installed. install.php delete kar dein.'; exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $site = trim($_POST['site_name'] ?? 'TulipsChat') ?: 'TulipsChat';
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $user)) $err = 'Username 3-20 letters/numbers/_ hona chahiye';
    elseif (strlen($pass) < 6) $err = 'Password kam se kam 6 characters';
    else {
        try {
            $sqlite = $CFG['db_driver'] === 'sqlite';
            $pk  = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
            $eng = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $pdo = db();
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id $pk,
                username VARCHAR(30) NOT NULL UNIQUE,
                email VARCHAR(120) NOT NULL DEFAULT '',
                password VARCHAR(255) NOT NULL,
                role VARCHAR(10) NOT NULL DEFAULT 'user',
                status VARCHAR(10) NOT NULL DEFAULT 'active',
                avatar VARCHAR(100) NOT NULL DEFAULT '',
                gender VARCHAR(10) NOT NULL DEFAULT '',
                bio VARCHAR(300) NOT NULL DEFAULT '',
                last_ip VARCHAR(45) NOT NULL DEFAULT '',
                created_at INT NOT NULL DEFAULT 0,
                last_seen INT NOT NULL DEFAULT 0
            )$eng");
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id $pk,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at INT NOT NULL DEFAULT 0
            )$eng");
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                name VARCHAR(50) NOT NULL PRIMARY KEY,
                value TEXT NOT NULL
            )$eng");
            $pdo->exec('CREATE INDEX idx_msg_user ON messages (user_id)');

            $defaults = ['site_name' => $site, 'allow_register' => '1', 'max_length' => '500',
                         'history' => '50', 'announcement' => 'Welcome to the chat!'];
            foreach ($defaults as $k => $v) set_setting($k, $v);

            $st = $pdo->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?,?,?,?)');
            $st->execute([$user, password_hash($pass, PASSWORD_DEFAULT), 'admin', time()]);

            file_put_contents(__DIR__ . '/data/installed.lock', date('c'));
            header('Location: login.php?installed=1'); exit;
        } catch (Throwable $ex) {
            $err = 'DB error: ' . $ex->getMessage();
        }
    }
}
page_head('Install');
?>
<div class="auth-box">
  <h1>TulipsChat Install</h1>
  <p class="muted">Pehle config.php me database details set karein.</p>
  <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <label>Site name<input name="site_name" value="TulipsChat"></label>
    <label>Admin username<input name="username" required></label>
    <label>Admin password<input type="password" name="password" required></label>
    <button>Install</button>
  </form>
</div>
<?php page_foot();
