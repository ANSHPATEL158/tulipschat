
<?php
declare(strict_types=1);

// Load configuration
$CFG = require __DIR__ . '/config.php';

date_default_timezone_set($CFG['timezone'] ?? 'Asia/Kolkata');

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
        'path' => '/',
    ]);

    session_start();
}

function db(): PDO {
    static $pdo = null;
    global $CFG;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (($CFG['db_driver'] ?? '') === 'pgsql') {
        $host = $CFG['db_host'] ?? '';
        $port = $CFG['db_port'] ?? '5432';
        $name = $CFG['db_name'] ?? '';
        $user = $CFG['db_user'] ?? '';
        $pass = $CFG['db_pass'] ?? '';
        $sslmode = $CFG['db_sslmode'] ?? 'require';

        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException(
                'PostgreSQL configuration is incomplete. Check Render environment variables.'
            );
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
        $pdo = new PDO($dsn, $user, $pass, $opt);

    } elseif (($CFG['db_driver'] ?? '') === 'sqlite') {
        $pdo = new PDO(
            'sqlite:' . $CFG['sqlite_path'],
            null,
            null,
            $opt
        );

        $pdo->exec('PRAGMA journal_mode=WAL');

    } else {
        $host = $CFG['db_host'] ?? 'localhost';
        $name = $CFG['db_name'] ?? 'tulipschat';
        $user = $CFG['db_user'] ?? 'root';
        $pass = $CFG['db_pass'] ?? '';

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, $opt);
    }

    return $pdo;
}



function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function base_url(): string {
    // project root ka web path (admin folder se bhi sahi)
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (basename($dir) === 'admin') $dir = dirname($dir);
    return rtrim($dir, '/') . '/';
}

function redirect(string $to): never { header('Location: ' . base_url() . $to); exit; }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    return is_string($t) && hash_equals(csrf(), $t);
}

function setting(string $name, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT name, value FROM settings') as $r) $cache[$r['name']] = $r['value'];
    }
    return $cache[$name] ?? $default;
}
function set_setting(string $name, string $value): void {
    $st = db()->prepare('UPDATE settings SET value=? WHERE name=?');
    $st->execute([$value, $name]);
    if ($st->rowCount() === 0) {
        $chk = db()->prepare('SELECT COUNT(*) FROM settings WHERE name=?'); $chk->execute([$name]);
        if (!$chk->fetchColumn()) db()->prepare('INSERT INTO settings (name, value) VALUES (?,?)')->execute([$name, $value]);
    }
}

function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT * FROM users WHERE id=?');
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if (!$u || $u['status'] === 'banned') { $_SESSION = []; session_destroy(); $u = null; }
    }
    return $u;
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') redirect('chat.php');
    return $u;
}

function avatar_url(?string $file): string {
    return base_url() . ($file ? 'uploads/avatars/' . rawurlencode($file) : 'assets/default.svg');
}

function is_online(array $u): bool {
    global $CFG;
    return (int)$u['last_seen'] > time() - $CFG['online_timeout'];
}

function time_ago(int $t): string {
    $d = time() - $t;
    if ($d < 60) return 'abhi';
    if ($d < 3600) return floor($d / 60) . ' min pehle';
    if ($d < 86400) return floor($d / 3600) . ' ghante pehle';
    return date('d M Y', $t);
}

function installed(): bool { return is_file(__DIR__ . '/data/installed.lock'); }
if (!installed() && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    header('Location: ' . base_url() . 'install.php'); exit;
}

function page_head(string $title, bool $admin = false): void {
    $site = installed() ? setting('site_name', 'TulipsChat') : 'TulipsChat';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' - ' . e($site) . '</title>'
       . '<link rel="stylesheet" href="' . base_url() . 'assets/style.css">'
       . '</head><body' . ($admin ? ' class="admin"' : '') . '>';
}
function page_foot(): void { echo '</body></html>'; }
