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

/**
 * Database connection
 */
function db(): PDO
{
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
                'PostgreSQL configuration incomplete hai. Environment variables check karein.'
            );
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
        $pdo = new PDO($dsn, $user, $pass, $opt);

    } elseif (($CFG['db_driver'] ?? '') === 'sqlite') {
        $path = $CFG['sqlite_path'] ?? '';

        if ($path === '') {
            throw new RuntimeException(
                'SQLite database path configure nahi hai.'
            );
        }

        $pdo = new PDO('sqlite:' . $path, null, null, $opt);
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

/**
 * Escape HTML output
 */
function e(?string $s): string
{
    return htmlspecialchars(
        (string)$s,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Base URL for root and admin pages
 */
function base_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = str_replace('\\', '/', dirname($script));

    if (basename($dir) === 'admin') {
        $dir = dirname($dir);
    }

    return rtrim($dir, '/') . '/';
}

/**
 * Redirect
 */
function redirect(string $to): never
{
    header('Location: ' . base_url() . $to);
    exit;
}

/**
 * CSRF protection
 */
function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' .
        e(csrf()) . '">';
}

function csrf_ok(): bool
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');

    return is_string($token)
        && $token !== ''
        && hash_equals(csrf(), $token);
}

/**
 * Read a setting from the database
 */
function setting(string $name, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];

        try {
            foreach (
                db()->query('SELECT name, value FROM settings') as $row
            ) {
                $cache[$row['name']] = $row['value'];
            }
        } catch (Throwable $e) {
            error_log('Settings load error: ' . $e->getMessage());
        }
    }

    return (string)($cache[$name] ?? $default);
}

/**
 * Insert or update a setting
 */
function set_setting(string $name, string $value): void
{
    $pdo = db();

    $st = $pdo->prepare(
        'UPDATE settings SET value=? WHERE name=?'
    );
    $st->execute([$value, $name]);

    if ($st->rowCount() === 0) {
        $chk = $pdo->prepare(
            'SELECT COUNT(*) FROM settings WHERE name=?'
        );
        $chk->execute([$name]);

        if (!(int)$chk->fetchColumn()) {
            $pdo->prepare(
                'INSERT INTO settings (name, value) VALUES (?, ?)'
            )->execute([$name, $value]);
        }
    }
}

/**
 * Current logged-in user
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;

    if (empty($_SESSION['uid'])) {
        return null;
    }

    $st = db()->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([$_SESSION['uid']]);
    $user = $st->fetch() ?: null;

    if (!$user || ($user['status'] ?? '') === 'banned') {
        $_SESSION = [];
        session_destroy();
        $user = null;
    }

    return $user;
}

/**
 * Require login
 */
function require_login(): array
{
    $user = current_user();

    if (!$user) {
        redirect('login.php');
    }

    return $user;
}

/**
 * Require admin
 */
function require_admin(): array
{
    $user = require_login();

    if (($user['role'] ?? '') !== 'admin') {
        redirect('chat.php');
    }

    return $user;
}

/**
 * Profile avatar URL
 */
function avatar_url(?string $file): string
{
    if (
        $file === null ||
        $file === '' ||
        basename($file) !== $file ||
        str_contains($file, '/') ||
        str_contains($file, '\\')
    ) {
        return base_url() . 'assets/default.svg';
    }

    return base_url() . 'uploads/avatars/' . rawurlencode($file);
}

/**
 * Online status
 */
function is_online(array $user): bool
{
    global $CFG;

    return (int)($user['last_seen'] ?? 0) >
        time() - (int)($CFG['online_timeout'] ?? 300);
}

/**
 * Time since last activity
 */
function time_ago(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'abhi';
    }

    if ($diff < 3600) {
        return floor($diff / 60) . ' min pehle';
    }

    if ($diff < 86400) {
        return floor($diff / 3600) . ' ghante pehle';
    }

    return date('d M Y', $timestamp);
}

/**
 * Check installation
 */
function installed(): bool
{
    if (is_file(__DIR__ . '/data/installed.lock')) {
        return true;
    }

    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Redirect to installer when necessary
 */
if (
    !installed() &&
    basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php'
) {
    header('Location: ' . base_url() . 'install.php');
    exit;
}

/**
 * Page header
 */
function page_head(string $title, bool $admin = false): void
{
    $site = installed()
        ? setting('site_name', 'TulipsChat')
        : 'TulipsChat';

    echo '<!doctype html><html lang="en"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' - ' . e($site) . '</title>';
    echo '<link rel="stylesheet" href="' . e(base_url()) . 'assets/style.css">';
    echo '</head><body' . ($admin ? ' class="admin"' : '') . '>';
}

/**
 * Page footer
 */
function page_foot(): void
{
    echo '</body></html>';
}
