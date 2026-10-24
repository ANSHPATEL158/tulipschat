<?php
require __DIR__ . '/core.php';
$me = require_login();

$id = (int)($_GET['id'] ?? $me['id']);
$st = db()->prepare('SELECT * FROM users WHERE id=?');
$st->execute([$id]);
$user = $st->fetch();
if (!$user) { http_response_code(404); page_head('Not found'); echo '<div class="auth-box">User nahi mila. <a href="chat.php">Wapis</a></div>'; page_foot(); exit; }
$own = $user['id'] == $me['id'];

function save_avatar(array $f, int $uid): string {
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload fail hua');
    if ($f['size'] > 3 * 1024 * 1024) throw new RuntimeException('Image 3MB se choti honi chahiye');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true))
        throw new RuntimeException('Sirf JPG, PNG, GIF, WEBP');
    $name = $uid . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $dest = __DIR__ . '/uploads/avatars/' . $name;
    if (function_exists('imagecreatefromstring')) {
        // GD se re-encode: 200x200 crop, isse image ke andar chupa code bhi khatam
        $src = imagecreatefromstring(file_get_contents($f['tmp_name']));
        if (!$src) throw new RuntimeException('Image kharab hai');
        $w = imagesx($src); $h = imagesy($src); $s = min($w, $h);
        $dst = imagecreatetruecolor(200, 200);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, (int)(($w - $s) / 2), (int)(($h - $s) / 2), 200, 200, $s, $s);
        imagejpeg($dst, $dest, 85);
    } else {
        move_uploaded_file($f['tmp_name'], $dest);
    }
    return $name;
}

$msg = ''; $err = '';
if ($own && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $err = 'Session expire ho gaya';
    elseif (($_POST['form'] ?? '') === 'info') {
        $email = trim($_POST['email'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : '';
        $bio = mb_substr(trim($_POST['bio'] ?? ''), 0, 300);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email sahi nahi hai';
        else {
            try {
                $avatar = $user['avatar'];
                if (!empty($_FILES['avatar']['name'])) {
                    $avatar = save_avatar($_FILES['avatar'], (int)$me['id']);
                    if ($user['avatar'] && is_file(__DIR__ . '/uploads/avatars/' . $user['avatar']))
                        @unlink(__DIR__ . '/uploads/avatars/' . $user['avatar']);
                }
                if (!empty($_POST['remove_avatar'])) {
                    if ($user['avatar']) @unlink(__DIR__ . '/uploads/avatars/' . $user['avatar']);
                    $avatar = '';
                }
                db()->prepare('UPDATE users SET email=?, gender=?, bio=?, avatar=? WHERE id=?')
                    ->execute([$email, $gender, $bio, $avatar, $me['id']]);
                $msg = 'Profile save ho gaya';
            } catch (RuntimeException $ex) { $err = $ex->getMessage(); }
        }
    } elseif (($_POST['form'] ?? '') === 'pass') {
        if (!password_verify($_POST['old'] ?? '', $user['password'])) $err = 'Purana password ghalat hai';
        elseif (strlen($_POST['new'] ?? '') < 6) $err = 'Naya password kam se kam 6 characters';
        elseif (($_POST['new'] ?? '') !== ($_POST['new2'] ?? '')) $err = 'Naye password match nahi kar rahe';
        else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($_POST['new'], PASSWORD_DEFAULT), $me['id']]);
            $msg = 'Password change ho gaya';
        }
    }
    $st->execute([$id]); $user = $st->fetch();
}

$cnt = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id=?');
$cnt->execute([$user['id']]);
page_head($user['username']);
?>
<div class="page">
  <p><a href="chat.php">&larr; Chat par wapis</a></p>
  <div class="card profile-card">
    <img class="av-lg" src="<?= e(avatar_url($user['avatar'])) ?>" alt="">
    <div>
      <h2><?= e($user['username']) ?>
        <?php if ($user['role'] === 'admin'): ?><span class="badge">Admin</span><?php endif; ?>
        <?php if ($user['status'] === 'banned'): ?><span class="badge red">Banned</span><?php endif; ?>
      </h2>
      <p class="muted"><?= is_online($user) ? '<span class="dot"></span> Online' : 'Last seen: ' . ($user['last_seen'] ? e(time_ago((int)$user['last_seen'])) : 'kabhi nahi') ?></p>
      <?php if ($user['gender']): ?><p>Gender: <?= e(ucfirst($user['gender'])) ?></p><?php endif; ?>
      <p>Joined: <?= e(date('d M Y', (int)$user['created_at'])) ?> &middot; Messages: <?= (int)$cnt->fetchColumn() ?></p>
      <?php if ($user['bio']): ?><p class="bio"><?= nl2br(e($user['bio'])) ?></p><?php endif; ?>
      <?php if ($me['role'] === 'admin' && !$own): ?><p><a class="btn" href="admin/users.php?edit=<?= (int)$user['id'] ?>">Admin me manage karein</a></p><?php endif; ?>
    </div>
  </div>

  <?php if ($own): ?>
    <?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
    <div class="grid2">
      <form class="card" method="post" enctype="multipart/form-data">
        <h3>Profile edit</h3>
        <?= csrf_field() ?><input type="hidden" name="form" value="info">
        <label>Avatar<input type="file" name="avatar" accept="image/*"></label>
        <?php if ($user['avatar']): ?><label class="check"><input type="checkbox" name="remove_avatar" value="1"> Avatar hatayen</label><?php endif; ?>
        <label>Email<input type="email" name="email" value="<?= e($user['email']) ?>"></label>
        <label>Gender
          <select name="gender">
            <?php foreach (['' => '--', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= $user['gender'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Bio<textarea name="bio" maxlength="300" rows="3"><?= e($user['bio']) ?></textarea></label>
        <button>Save</button>
      </form>
      <form class="card" method="post">
        <h3>Password change</h3>
        <?= csrf_field() ?><input type="hidden" name="form" value="pass">
        <label>Purana password<input type="password" name="old" required></label>
        <label>Naya password<input type="password" name="new" required></label>
        <label>Naya password dobara<input type="password" name="new2" required></label>
        <button>Change</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php page_foot();
