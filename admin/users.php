<?php
require __DIR__ . '/_layout.php';

function find_user(int $id): ?array {
    $s = db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$id]); return $s->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash('Session expire, dobara try karein'); redirect('admin/users.php'); }
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['act'] ?? '';
    $t = find_user($id);
    if (!$t) { flash('User nahi mila'); redirect('admin/users.php'); }
    if ($id === (int)$me['id'] && in_array($act, ['ban', 'make_user', 'delete'], true)) {
        flash('Apne aap par ye action nahi ho sakta'); redirect('admin/users.php');
    }
    switch ($act) {
        case 'ban':       db()->prepare("UPDATE users SET status='banned', last_seen=0 WHERE id=?")->execute([$id]); flash($t['username'] . ' ban ho gaya'); break;
        case 'unban':     db()->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]); flash($t['username'] . ' unban ho gaya'); break;
        case 'make_admin':db()->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]); flash($t['username'] . ' ab admin hai'); break;
        case 'make_user': db()->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$id]); flash($t['username'] . ' ab normal user hai'); break;
        case 'delete':
            db()->prepare('DELETE FROM messages WHERE user_id=?')->execute([$id]);
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            if ($t['avatar']) @unlink(dirname(__DIR__) . '/uploads/avatars/' . $t['avatar']);
            flash($t['username'] . ' delete ho gaya');
            break;
        case 'clear_msgs':
            db()->prepare('DELETE FROM messages WHERE user_id=?')->execute([$id]);
            flash($t['username'] . ' ke sab messages delete'); break;
        case 'save':
            $name = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
            $status = ($_POST['status'] ?? '') === 'banned' ? 'banned' : 'active';
            if ($id === (int)$me['id']) { $role = 'admin'; $status = 'active'; }
            if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $name)) { flash('Username ghalat hai'); redirect('admin/users.php?edit=' . $id); }
            $chk = db()->prepare('SELECT COUNT(*) FROM users WHERE LOWER(username)=LOWER(?) AND id<>?'); $chk->execute([$name, $id]);
            if ($chk->fetchColumn()) { flash('Ye username kisi aur ka hai'); redirect('admin/users.php?edit=' . $id); }
            db()->prepare('UPDATE users SET username=?, email=?, role=?, status=?, bio=? WHERE id=?')
                ->execute([$name, $email, $role, $status, mb_substr(trim($_POST['bio'] ?? ''), 0, 300), $id]);
            if (($_POST['newpass'] ?? '') !== '') {
                if (strlen($_POST['newpass']) < 6) { flash('Password kam se kam 6 characters'); redirect('admin/users.php?edit=' . $id); }
                db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($_POST['newpass'], PASSWORD_DEFAULT), $id]);
            }
            flash('User save ho gaya');
            redirect('admin/users.php?edit=' . $id);
    }
    redirect('admin/users.php?' . http_build_query(['q' => $_POST['q'] ?? '', 'p' => $_POST['p'] ?? 1]));
}

function act_btn(int $id, string $act, string $label, string $cls = '', string $confirm = ''): string {
    $c = $confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '';
    return '<form method="post" class="inline"' . $c . '>' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="act" value="' . $act . '">'
        . '<input type="hidden" name="q" value="' . e($_GET['q'] ?? '') . '"><input type="hidden" name="p" value="' . (int)($_GET['p'] ?? 1) . '">'
        . '<button class="sm ' . $cls . '">' . $label . '</button></form>';
}

// ---- Edit view ----
if (isset($_GET['edit']) && ($t = find_user((int)$_GET['edit']))) {
    admin_head('Edit: ' . $t['username'], 'users.php');
    echo flash();
    $self = $t['id'] == $me['id'];
    ?>
    <p><a href="users.php">&larr; Sab users</a> &middot; <a href="../profile.php?id=<?= (int)$t['id'] ?>">Profile dekhein</a></p>
    <form class="card narrow" method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="act" value="save">
      <label>Username<input name="username" value="<?= e($t['username']) ?>" required></label>
      <label>Email<input name="email" value="<?= e($t['email']) ?>"></label>
      <label>Role<select name="role" <?= $self ? 'disabled' : '' ?>>
        <option value="user" <?= $t['role'] === 'user' ? 'selected' : '' ?>>User</option>
        <option value="admin" <?= $t['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
      <label>Status<select name="status" <?= $self ? 'disabled' : '' ?>>
        <option value="active" <?= $t['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="banned" <?= $t['status'] === 'banned' ? 'selected' : '' ?>>Banned</option></select></label>
      <label>Bio<textarea name="bio" rows="3"><?= e($t['bio']) ?></textarea></label>
      <label>Naya password (khali chhorein to same rahega)<input type="password" name="newpass"></label>
      <p class="muted">Last IP: <?= e($t['last_ip'] ?: '-') ?> &middot; Joined: <?= e(date('d M Y', (int)$t['created_at'])) ?></p>
      <button>Save</button>
    </form>
    <?php if (!$self): ?>
      <div class="card narrow actions">
        <?= act_btn((int)$t['id'], 'clear_msgs', 'Sab messages delete', 'warn', 'Is user ke sab messages delete?') ?>
        <?= act_btn((int)$t['id'], 'delete', 'User delete', 'danger', 'User aur us ke messages hamesha ke liye delete?') ?>
      </div>
    <?php endif;
    admin_foot(); exit;
}

// ---- List view ----
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 25;
$where = ''; $params = [];
if ($q !== '') { $where = 'WHERE username LIKE ? OR email LIKE ? OR last_ip LIKE ?'; $params = ["%$q%", "%$q%", "%$q%"]; }
$c = db()->prepare("SELECT COUNT(*) FROM users $where"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$off = ($page - 1) * $per;
$st = db()->prepare("SELECT * FROM users $where ORDER BY id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();

admin_head('Users', 'users.php');
echo flash();
?>
<form class="search" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Username, email ya IP"><button>Search</button></form>
<div class="card table-wrap">
<table>
  <tr><th>ID</th><th>User</th><th>Role</th><th>Status</th><th>Last seen</th><th>IP</th><th>Actions</th></tr>
  <?php foreach ($rows as $u): $self = $u['id'] == $me['id']; ?>
  <tr>
    <td><?= (int)$u['id'] ?></td>
    <td><img class="av-sm" src="<?= e(avatar_url($u['avatar'])) ?>" alt=""> <a href="?edit=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a><?= is_online($u) ? ' <span class="dot"></span>' : '' ?></td>
    <td><?= $u['role'] === 'admin' ? '<span class="badge">Admin</span>' : 'User' ?></td>
    <td><?= $u['status'] === 'banned' ? '<span class="badge red">Banned</span>' : 'Active' ?></td>
    <td><?= $u['last_seen'] ? e(time_ago((int)$u['last_seen'])) : '-' ?></td>
    <td><?= e($u['last_ip']) ?></td>
    <td class="nowrap">
      <a class="btn sm" href="?edit=<?= (int)$u['id'] ?>">Edit</a>
      <?php if (!$self): ?>
        <?= $u['status'] === 'banned' ? act_btn((int)$u['id'], 'unban', 'Unban') : act_btn((int)$u['id'], 'ban', 'Ban', 'warn', 'Ban karein?') ?>
        <?= $u['role'] === 'admin' ? act_btn((int)$u['id'], 'make_user', 'Remove admin') : act_btn((int)$u['id'], 'make_admin', 'Make admin', '', 'Admin banayen?') ?>
        <?= act_btn((int)$u['id'], 'delete', 'Delete', 'danger', 'User delete karein?') ?>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">Koi user nahi mila</td></tr><?php endif; ?>
</table>
</div>
<div class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e(http_build_query(['q' => $q, 'p' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php admin_foot();
