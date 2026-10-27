
<?php
require __DIR__ . '/core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data): never
{
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

$u = current_user();

if (!$u) {
    out(['error' => 'logout']);
}

$act = $_GET['act'] ?? '';

/*
|--------------------------------------------------------------------------
| FETCH MESSAGES, REPLIES, ONLINE USERS AND DELETED MESSAGES
|--------------------------------------------------------------------------
*/
if ($act === 'fetch') {
    // Update the current user's online heartbeat.
    db()->prepare(
        'UPDATE users SET last_seen = ? WHERE id = ?'
    )->execute([time(), (int)$u['id']]);

    $last = max(0, (int)($_GET['last'] ?? 0));

    $sel = 'SELECT
                m.id,
                m.user_id,
                m.message,
                m.created_at,
                m.reply_to_id,
                u.username,
                u.avatar,
                u.role,
                rm.message AS reply_message,
                ru.username AS reply_username
            FROM messages m
            JOIN users u ON u.id = m.user_id
            LEFT JOIN messages rm ON rm.id = m.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.user_id';

    if ($last === 0) {
        $limit = max(10, min(200, (int)setting('history', '50')));

        $rows = array_reverse(
            db()->query(
                "$sel ORDER BY m.id DESC LIMIT $limit"
            )->fetchAll(PDO::FETCH_ASSOC)
        );
    } else {
        $st = db()->prepare(
            "$sel WHERE m.id > ? ORDER BY m.id ASC LIMIT 100"
        );
        $st->execute([$last]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $msgs = array_map(static fn($r) => [
        'id' => (int)$r['id'],
        'uid' => (int)$r['user_id'],
        'name' => $r['username'],
        'avatar' => avatar_url($r['avatar']),
        'role' => $r['role'],
        'text' => $r['message'],
        'time' => date('h:i A', (int)$r['created_at']),
        'reply' => (
            $r['reply_to_id'] !== null &&
            $r['reply_message'] !== null
        ) ? [
            'id' => (int)$r['reply_to_id'],
            'name' => $r['reply_username'],
            'text' => $r['reply_message'],
        ] : null,
    ], $rows);

    /*
     * ONLINE USERS
     * Each fetch request refreshes the current user's last_seen.
     * Only active accounts seen within the timeout are returned.
     */
    global $CFG;

    $onlineTimeout = max(
        15,
        (int)($CFG['online_timeout'] ?? 60)
    );

    $st = db()->prepare(
        'SELECT id, username, avatar, role, gender
         FROM users
         WHERE last_seen > ?
           AND status = ?
         ORDER BY role, username'
    );

    $st->execute([
        time() - $onlineTimeout,
        'active',
    ]);

    $users = array_map(
        static fn($r) => [
            'id' => (int)$r['id'],
            'name' => $r['username'],
            'avatar' => avatar_url($r['avatar']),
            'role' => $r['role'],
            'gender' => $r['gender'],
        ],
        $st->fetchAll(PDO::FETCH_ASSOC)
    );

    // Find messages removed by another user/admin.
    $removed = [];

    if (!empty($_GET['visible'])) {
        $ids = array_slice(
            array_values(array_filter(
                array_map(
                    'intval',
                    explode(',', (string)$_GET['visible'])
                ),
                static fn($id) => $id > 0
            )),
            -200
        );

        if ($ids) {
            $in = implode(',', $ids);

            $existing = array_map(
                'intval',
                db()->query(
                    "SELECT id FROM messages WHERE id IN ($in)"
                )->fetchAll(PDO::FETCH_COLUMN)
            );

            $removed = array_values(array_diff($ids, $existing));
        }
    }

    out([
        'messages' => $msgs,
        'users' => $users,
        'removed' => $removed,
    ]);
}

/*
|--------------------------------------------------------------------------
| PRIVATE MESSAGE HELPERS
|--------------------------------------------------------------------------
*/

function pm_table(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    global $CFG;
    $pdo = db();

    try {
        $pdo->query('SELECT 1 FROM private_messages LIMIT 1');
        $ready = true;
        return;
    } catch (Throwable $e) {
        // Table does not exist yet.
    }

    $driver = $CFG['db_driver'] ?? 'mysql';

    if ($driver === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS private_messages (
                id SERIAL PRIMARY KEY,
                from_id INTEGER NOT NULL,
                to_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at INTEGER NOT NULL DEFAULT 0,
                read_at INTEGER NOT NULL DEFAULT 0,
                del_from SMALLINT NOT NULL DEFAULT 0,
                del_to SMALLINT NOT NULL DEFAULT 0
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_pm_to
             ON private_messages (to_id, read_at)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_pm_pair
             ON private_messages (from_id, to_id)'
        );
    } elseif ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS private_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_id INTEGER NOT NULL,
                to_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at INTEGER NOT NULL DEFAULT 0,
                read_at INTEGER NOT NULL DEFAULT 0,
                del_from INTEGER NOT NULL DEFAULT 0,
                del_to INTEGER NOT NULL DEFAULT 0
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_pm_to
             ON private_messages (to_id, read_at)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_pm_pair
             ON private_messages (from_id, to_id)'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS private_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                from_id INT NOT NULL,
                to_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at INT NOT NULL DEFAULT 0,
                read_at INT NOT NULL DEFAULT 0,
                del_from TINYINT NOT NULL DEFAULT 0,
                del_to TINYINT NOT NULL DEFAULT 0,
                INDEX idx_pm_to (to_id, read_at),
                INDEX idx_pm_pair (from_id, to_id)
            ) DEFAULT CHARSET=utf8mb4'
        );
    }

    $ready = true;
}

function pm_user(int $id): ?array
{
    if ($id < 1) {
        return null;
    }

    $st = db()->prepare(
        'SELECT id, username, avatar, role, status, last_seen
         FROM users WHERE id = ?'
    );
    $st->execute([$id]);

    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function pm_target(int $id, array $me): ?array
{
    if ($id < 1 || $id === (int)$me['id']) {
        return null;
    }

    $row = pm_user($id);

    if (!$row || ($row['status'] ?? '') === 'banned') {
        return null;
    }

    return $row;
}

function pm_time(int $ts): string
{
    return date('d/m h:i A', $ts);
}

function pm_preview(string $text): string
{
    if (preg_match(
        '/^__TC_FILE__:(image|video|document):/',
        $text,
        $m
    )) {
        return match ($m[1]) {
            'image' => 'Image',
            'video' => 'Video',
            default => 'Document',
        };
    }

    return mb_strlen($text) > 60
        ? mb_substr($text, 0, 60) . '…'
        : $text;
}

function pm_is_online(array $user): bool
{
    global $CFG;

    return (int)($user['last_seen'] ?? 0)
        > time() - (int)($CFG['online_timeout'] ?? 60);
}

/*
|--------------------------------------------------------------------------
| PM: CHAT LIST + UNREAD COUNT
|--------------------------------------------------------------------------
*/
if ($act === 'pm_list') {
    pm_table();
    $me = (int)$u['id'];

    $st = db()->prepare(
        'SELECT id, from_id, to_id, message, created_at
         FROM private_messages
         WHERE (from_id = ? AND del_from = 0)
            OR (to_id = ? AND del_to = 0)
         ORDER BY id DESC
         LIMIT 500'
    );
    $st->execute([$me, $me]);

    $last = [];

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $other = ((int)$r['from_id'] === $me)
            ? (int)$r['to_id']
            : (int)$r['from_id'];

        if (!isset($last[$other])) {
            $last[$other] = $r;
        }
    }

    $st = db()->prepare(
        'SELECT from_id, COUNT(*) AS c
         FROM private_messages
         WHERE to_id = ? AND read_at = 0 AND del_to = 0
         GROUP BY from_id'
    );
    $st->execute([$me]);

    $unread = [];
    $totalUnread = 0;

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $unread[(int)$r['from_id']] = (int)$r['c'];
        $totalUnread += (int)$r['c'];
    }

    $ids = array_slice(array_keys($last), 0, 50);
    $users = [];

    if ($ids) {
        $in = implode(',', array_map('intval', $ids));

        foreach (
            db()->query(
                "SELECT id, username, avatar, role, last_seen
                 FROM users WHERE id IN ($in)"
            )->fetchAll(PDO::FETCH_ASSOC) as $r
        ) {
            $users[(int)$r['id']] = $r;
        }
    }

    $list = [];

    foreach ($ids as $id) {
        if (!isset($users[$id])) {
            continue;
        }

        $usr = $users[$id];
        $row = $last[$id];

        $list[] = [
            'id' => $id,
            'name' => $usr['username'],
            'avatar' => avatar_url($usr['avatar']),
            'role' => $usr['role'],
            'online' => pm_is_online($usr),
            'preview' => pm_preview((string)$row['message']),
            'mine' => ((int)$row['from_id'] === $me),
            'time' => pm_time((int)$row['created_at']),
            'unread' => $unread[$id] ?? 0,
        ];
    }

    out(['conversations' => $list, 'unread' => $totalUnread]);
}

/*
|--------------------------------------------------------------------------
| PM: ONE CHAT (HISTORY / NEW MESSAGES + SEEN STATUS)
|--------------------------------------------------------------------------
*/
if ($act === 'pm_fetch') {
    pm_table();
    $me = (int)$u['id'];
    $with = (int)($_GET['with'] ?? 0);
    $peer = pm_user($with);

    if (!$peer || $with === $me) {
        out(['error' => 'User nahi mila']);
    }

    $last = max(0, (int)($_GET['last'] ?? 0));

    $base = 'SELECT id, from_id, message, created_at, read_at
             FROM private_messages
             WHERE ((from_id = ? AND to_id = ? AND del_from = 0)
                 OR (from_id = ? AND to_id = ? AND del_to = 0))';

    if ($last === 0) {
        $st = db()->prepare("$base ORDER BY id DESC LIMIT 50");
        $st->execute([$me, $with, $with, $me]);
        $rows = array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
    } else {
        $st = db()->prepare(
            "$base AND id > ? ORDER BY id ASC LIMIT 100"
        );
        $st->execute([$me, $with, $with, $me, $last]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $msgs = array_map(static fn($r) => [
        'id' => (int)$r['id'],
        'mine' => ((int)$r['from_id'] === $me),
        'text' => $r['message'],
        'time' => pm_time((int)$r['created_at']),
        'seen' => ((int)$r['read_at'] > 0),
    ], $rows);

    $st = db()->prepare(
        'SELECT id FROM private_messages
         WHERE from_id = ? AND to_id = ? AND read_at > 0 AND del_from = 0
         ORDER BY id DESC
         LIMIT 100'
    );
    $st->execute([$me, $with]);

    out([
        'messages' => $msgs,
        'seen' => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)),
        'user' => [
            'id' => (int)$peer['id'],
            'name' => $peer['username'],
            'avatar' => avatar_url($peer['avatar']),
            'online' => pm_is_online($peer),
        ],
    ]);
}

/*
|--------------------------------------------------------------------------
| ALL ACTIONS BELOW REQUIRE POST + CSRF
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    out(['error' => 'Invalid request']);
}

/*
|--------------------------------------------------------------------------
| SEND MESSAGE OR REPLY
|--------------------------------------------------------------------------
*/
if ($act === 'send') {
    $text = trim((string)($_POST['message'] ?? ''));
    $replyId = (int)($_POST['reply_to_id'] ?? 0);
    $max = max(1, (int)setting('max_length', '500'));

    if ($text === '') {
        out(['error' => 'Khali message']);
    }

    if (mb_strlen($text) > $max) {
        out(['error' => "Message $max characters se lamba hai"]);
    }

    if (time() - (int)($_SESSION['last_msg'] ?? 0) < 1) {
        out(['error' => 'Thoda ruk kar message bhejein']);
    }

    if ($replyId > 0) {
        $check = db()->prepare(
            'SELECT id FROM messages WHERE id = ?'
        );
        $check->execute([$replyId]);

        if (!$check->fetchColumn()) {
            out(['error' => 'Original message nahi mila']);
        }
    } else {
        $replyId = null;
    }

    db()->prepare(
        'INSERT INTO messages
            (user_id, message, created_at, reply_to_id)
         VALUES (?, ?, ?, ?)'
    )->execute([
        $u['id'],
        $text,
        time(),
        $replyId,
    ]);

    $_SESSION['last_msg'] = time();

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| UPLOAD IMAGE, VIDEO OR DOCUMENT
|--------------------------------------------------------------------------
*/
if ($act === 'upload') {
    $pmTo = (int)($_POST['to'] ?? 0);

    if ($pmTo > 0) {
        pm_table();

        if (!pm_target($pmTo, $u)) {
            out(['error' => 'Ye user message nahi le sakta']);
        }
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        out(['error' => 'File select karein']);
    }

    $file = $_FILES['file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        $message = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'File PHP upload limit se badi hai',
            UPLOAD_ERR_PARTIAL => 'File poori upload nahi hui',
            UPLOAD_ERR_NO_FILE => 'File select karein',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP temporary folder missing hai',
            UPLOAD_ERR_CANT_WRITE => 'Server file write nahi kar saka',
            default => 'Upload fail hua. File size aur PHP settings check karein',
        };

        out(['error' => $message]);
    }

    if (
        empty($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        out(['error' => 'Invalid upload']);
    }

    if (!class_exists('finfo')) {
        out(['error' => 'PHP Fileinfo extension enabled nahi hai']);
    }

    $size = (int)($file['size'] ?? 0);

    if ($size < 1) {
        out(['error' => 'File khali hai']);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => [
            'ext' => 'jpg', 'kind' => 'image',
            'max' => 5 * 1024 * 1024,
        ],
        'image/png' => [
            'ext' => 'png', 'kind' => 'image',
            'max' => 5 * 1024 * 1024,
        ],
        'image/gif' => [
            'ext' => 'gif', 'kind' => 'image',
            'max' => 5 * 1024 * 1024,
        ],
        'image/webp' => [
            'ext' => 'webp', 'kind' => 'image',
            'max' => 5 * 1024 * 1024,
        ],

        'video/mp4' => [
            'ext' => 'mp4', 'kind' => 'video',
            'max' => 25 * 1024 * 1024,
        ],
        'video/webm' => [
            'ext' => 'webm', 'kind' => 'video',
            'max' => 25 * 1024 * 1024,
        ],
        'video/quicktime' => [
            'ext' => 'mov', 'kind' => 'video',
            'max' => 25 * 1024 * 1024,
        ],

        'application/pdf' => [
            'ext' => 'pdf', 'kind' => 'document',
            'max' => 10 * 1024 * 1024,
        ],
        'text/plain' => [
            'ext' => 'txt', 'kind' => 'document',
            'max' => 10 * 1024 * 1024,
        ],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
            'ext' => 'docx', 'kind' => 'document',
            'max' => 10 * 1024 * 1024,
        ],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
            'ext' => 'xlsx', 'kind' => 'document',
            'max' => 10 * 1024 * 1024,
        ],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => [
            'ext' => 'pptx', 'kind' => 'document',
            'max' => 10 * 1024 * 1024,
        ],
    ];

    if (!is_string($mime) || !isset($allowed[$mime])) {
        out(['error' => 'Sirf supported image, video aur document files allowed hain']);
    }

    $info = $allowed[$mime];

    if ($size > $info['max']) {
        $limitMB = (int)($info['max'] / (1024 * 1024));
        out(['error' => "Is type ki file maximum {$limitMB} MB ho sakti hai"]);
    }

    $dir = __DIR__ . '/uploads/chat';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        out(['error' => 'Upload folder create nahi hua']);
    }

    // Block execution of uploaded scripts.
    $htaccess = $dir . '/.htaccess';

    if (!is_file($htaccess)) {
        $rules = "Options -ExecCGI\n"
               . "<FilesMatch \"\\.(php[0-9]*|phtml|phar|cgi|pl|py|sh)$\">\n"
               . "Require all denied\n"
               . "</FilesMatch>\n";

        if (file_put_contents($htaccess, $rules, LOCK_EX) === false) {
            out(['error' => 'Upload folder security setup fail hua']);
        }
    }

    try {
        $filename = bin2hex(random_bytes(16)) . '.' . $info['ext'];
    } catch (Throwable $e) {
        out(['error' => 'Secure filename create nahi hua']);
    }

    $destination = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        out(['error' => 'File save nahi hui']);
    }

    $message = '__TC_FILE__:'
             . $info['kind']
             . ':uploads/chat/'
             . $filename;

    try {
        if ($pmTo > 0) {
            db()->prepare(
                'INSERT INTO private_messages
                    (from_id, to_id, message, created_at,
                     read_at, del_from, del_to)
                 VALUES (?, ?, ?, ?, 0, 0, 0)'
            )->execute([
                $u['id'],
                $pmTo,
                $message,
                time(),
            ]);
        } else {
            db()->prepare(
                'INSERT INTO messages
                    (user_id, message, created_at, reply_to_id)
                 VALUES (?, ?, ?, NULL)'
            )->execute([
                $u['id'],
                $message,
                time(),
            ]);
        }
    } catch (Throwable $e) {
        @unlink($destination);
        error_log('TulipsChat upload DB error: ' . $e->getMessage());

        out(['error' => 'File database mein save nahi hui']);
    }

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| DELETE MESSAGE: OWNER OR ADMIN
|--------------------------------------------------------------------------
*/
if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id < 1) {
        out(['error' => 'Invalid message ID']);
    }

    $st = db()->prepare(
        'SELECT user_id FROM messages WHERE id = ?'
    );
    $st->execute([$id]);
    $ownerId = $st->fetchColumn();

    if ($ownerId === false) {
        out(['error' => 'Message nahi mila']);
    }

    $isAdmin = (($u['role'] ?? '') === 'admin');
    $isOwner = ((int)$ownerId === (int)$u['id']);

    if (!$isAdmin && !$isOwner) {
        out(['error' => 'Aap sirf apna message delete kar sakte hain']);
    }

    db()->prepare(
        'DELETE FROM messages WHERE id = ?'
    )->execute([$id]);

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| PM: SEND TEXT MESSAGE
|--------------------------------------------------------------------------
*/
if ($act === 'pm_send') {
    pm_table();

    $to = (int)($_POST['to'] ?? 0);

    if (!pm_target($to, $u)) {
        out(['error' => 'Ye user message nahi le sakta']);
    }

    $text = trim((string)($_POST['message'] ?? ''));
    $max = max(1, (int)setting('max_length', '500'));

    if ($text === '') {
        out(['error' => 'Khali message']);
    }

    if (mb_strlen($text) > $max) {
        out(['error' => "Message $max characters se lamba hai"]);
    }

    if (
        str_starts_with($text, '__TC_FILE__:') ||
        str_starts_with($text, '__TC_UPLOAD__:')
    ) {
        out(['error' => 'Invalid message']);
    }

    if (microtime(true) - (float)($_SESSION['last_pm'] ?? 0) < 0.7) {
        out(['error' => 'Thoda ruk kar message bhejein']);
    }

    db()->prepare(
        'INSERT INTO private_messages
            (from_id, to_id, message, created_at,
             read_at, del_from, del_to)
         VALUES (?, ?, ?, ?, 0, 0, 0)'
    )->execute([$u['id'], $to, $text, time()]);

    $_SESSION['last_pm'] = microtime(true);

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| PM: MARK ONE CHAT AS READ
|--------------------------------------------------------------------------
*/
if ($act === 'pm_read') {
    pm_table();

    $with = (int)($_POST['with'] ?? 0);

    db()->prepare(
        'UPDATE private_messages
         SET read_at = ?
         WHERE to_id = ? AND from_id = ? AND read_at = 0'
    )->execute([time(), $u['id'], $with]);

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| PM: MARK ALL CHATS AS READ
|--------------------------------------------------------------------------
*/
if ($act === 'pm_read_all') {
    pm_table();

    db()->prepare(
        'UPDATE private_messages
         SET read_at = ?
         WHERE to_id = ? AND read_at = 0'
    )->execute([time(), $u['id']]);

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| PM: REMOVE ONE CHAT (SIRF MERE LIYE)
|--------------------------------------------------------------------------
*/
if ($act === 'pm_hide') {
    pm_table();

    $with = (int)($_POST['with'] ?? 0);

    if ($with < 1) {
        out(['error' => 'Invalid user']);
    }

    $pdo = db();

    $pdo->prepare(
        'UPDATE private_messages SET del_from = 1
         WHERE from_id = ? AND to_id = ?'
    )->execute([$u['id'], $with]);

    $pdo->prepare(
        'UPDATE private_messages SET del_to = 1
         WHERE to_id = ? AND from_id = ?'
    )->execute([$u['id'], $with]);

    out(['ok' => 1]);
}

/*
|--------------------------------------------------------------------------
| PM: REMOVE ALL CHATS (SIRF MERE LIYE)
|--------------------------------------------------------------------------
*/
if ($act === 'pm_hide_all') {
    pm_table();

    $pdo = db();

    $pdo->prepare(
        'UPDATE private_messages SET del_from = 1 WHERE from_id = ?'
    )->execute([$u['id']]);

    $pdo->prepare(
        'UPDATE private_messages SET del_to = 1 WHERE to_id = ?'
    )->execute([$u['id']]);

    out(['ok' => 1]);
}

out(['error' => 'Unknown action']);
