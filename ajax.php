<?php
require __DIR__ . '/core.php';
header('Content-Type: application/json; charset=utf-8');

function out(array $d): never { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$u = current_user();
if (!$u) out(['error' => 'logout']);

$act = $_GET['act'] ?? '';

if ($act === 'fetch') {
    db()->prepare('UPDATE users SET last_seen=? WHERE id=?')->execute([time(), $u['id']]);
    $last = max(0, (int)($_GET['last'] ?? 0));
    $sel = 'SELECT m.id, m.user_id, m.message, m.created_at, u.username, u.avatar, u.role
            FROM messages m JOIN users u ON u.id = m.user_id';
    if ($last === 0) {
        $limit = max(10, min(200, (int)setting('history', '50')));
        $rows = array_reverse(db()->query("$sel ORDER BY m.id DESC LIMIT $limit")->fetchAll());
    } else {
        $st = db()->prepare("$sel WHERE m.id > ? ORDER BY m.id ASC LIMIT 100");
        $st->execute([$last]);
        $rows = $st->fetchAll();
    }
    $msgs = array_map(fn($r) => [
        'id' => (int)$r['id'], 'uid' => (int)$r['user_id'], 'name' => $r['username'],
        'avatar' => avatar_url($r['avatar']), 'role' => $r['role'],
        'text' => $r['message'], 'time' => date('h:i A', (int)$r['created_at']),
    ], $rows);

    $st = db()->prepare('SELECT id, username, avatar, role, gender FROM users WHERE last_seen > ? AND status = ? ORDER BY role, username');
    $st->execute([time() - $CFG['online_timeout'], 'active']);
    $users = array_map(fn($r) => [
        'id' => (int)$r['id'], 'name' => $r['username'], 'avatar' => avatar_url($r['avatar']),
        'role' => $r['role'], 'gender' => $r['gender'],
    ], $st->fetchAll());

    // deleted messages check (admin delete ke baad sab ke screen se hatane ke liye)
    $exists = [];
    if (!empty($_GET['visible'])) {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string)$_GET['visible']))), -200);
        if ($ids) {
            $in = implode(',', $ids);
            $exists = array_map('intval', db()->query("SELECT id FROM messages WHERE id IN ($in)")->fetchAll(PDO::FETCH_COLUMN));
            $exists = array_values(array_diff($ids, $exists)); // jo ab DB me nahi hain
        }
    }
    out(['messages' => $msgs, 'users' => $users, 'removed' => $exists]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) out(['error' => 'Invalid request']);

if ($act === 'send') {
    $text = trim((string)($_POST['message'] ?? ''));
    $max = (int)setting('max_length', '500');
    if ($text === '') out(['error' => 'Khali message']);
    if (mb_strlen($text) > $max) out(['error' => "Message $max characters se lamba hai"]);
    if (time() - ($_SESSION['last_msg'] ?? 0) < 1) out(['error' => 'Itna jaldi nahi, thora ruk ke']);
    $_SESSION['last_msg'] = time();
    db()->prepare('INSERT INTO messages (user_id, message, created_at) VALUES (?,?,?)')
        ->execute([$u['id'], $text, time()]);
    out(['ok' => 1]);
}

if ($act === 'delete') {
    if ($u['role'] !== 'admin') out(['error' => 'Sirf admin']);
    db()->prepare('DELETE FROM messages WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
    out(['ok' => 1]);
}

out(['error' => 'Unknown action']);
