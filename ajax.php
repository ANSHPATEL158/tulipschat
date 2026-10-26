
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
    db()->prepare(
        'UPDATE users SET last_seen = ? WHERE id = ?'
    )->execute([time(), $u['id']]);

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

    global $CFG;

    $onlineTimeout = (int)($CFG['online_timeout'] ?? 60);

    $st = db()->prepare(
        'SELECT id, username, avatar, role, gender
         FROM users
         WHERE last_seen > ? AND status = ?
         ORDER BY role, username'
    );
    $st->execute([time() - $onlineTimeout, 'active']);

    $users = array_map(static fn($r) => [
        'id' => (int)$r['id'],
        'name' => $r['username'],
        'avatar' => avatar_url($r['avatar']),
        'role' => $r['role'],
        'gender' => $r['gender'],
    ], $st->fetchAll(PDO::FETCH_ASSOC));

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
        // Images: maximum 5 MB
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

        // Videos: maximum 25 MB
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

        // Documents: maximum 10 MB
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
        db()->prepare(
            'INSERT INTO messages
                (user_id, message, created_at, reply_to_id)
             VALUES (?, ?, ?, NULL)'
        )->execute([
            $u['id'],
            $message,
            time(),
        ]);
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

    $isAdmin = ($u['role'] === 'admin');
    $isOwner = ((int)$ownerId === (int)$u['id']);

    if (!$isAdmin && !$isOwner) {
        out(['error' => 'Aap sirf apna message delete kar sakte hain']);
    }

    db()->prepare(
        'DELETE FROM messages WHERE id = ?'
    )->execute([$id]);

    out(['ok' => 1]);
}

out(['error' => 'Unknown action']);
