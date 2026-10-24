
<?php
require __DIR__ . '/core.php';
$me = require_login();

$id = (int)($_GET['id'] ?? $me['id']);

$st = db()->prepare('SELECT * FROM users WHERE id=?');
$st->execute([$id]);
$user = $st->fetch();

if (!$user) {
    http_response_code(404);
    page_head('Not found');
    echo '<div class="auth-box">User nahi mila. <a href="chat.php">Wapis</a></div>';
    page_foot();
    exit;
}

$own = (int)$user['id'] === (int)$me['id'];

/**
 * Avatar upload:
 * - JPG, PNG, GIF, WEBP input
 * - 3 MB maximum
 * - 200x200 JPEG output
 * - Purani image ko yahan delete nahi karte
 */
function save_avatar(array $f, int $uid): string
{
    $error = $f['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE ||
            $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException(
                'Image server ki upload limit se badi hai.'
            );
        }

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Pehle image select karein.');
        }

        if ($error === UPLOAD_ERR_PARTIAL) {
            throw new RuntimeException(
                'Image poori upload nahi hui. Dobara try karein.'
            );
        }

        throw new RuntimeException(
            'Image upload fail hui. Dobara try karein.'
        );
    }

    if (
        empty($f['tmp_name']) ||
        !is_uploaded_file($f['tmp_name'])
    ) {
        throw new RuntimeException('Invalid image upload.');
    }

    if (($f['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException(
            'Image 3MB se chhoti honi chahiye.'
        );
    }

    $info = @getimagesize($f['tmp_name']);

    if (
        !$info ||
        !in_array($info[2], [
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_GIF,
            IMAGETYPE_WEBP
        ], true)
    ) {
        throw new RuntimeException(
            'Sirf JPG, PNG, GIF ya WEBP image upload karein.'
        );
    }

    if (
        !function_exists('imagecreatefromstring') ||
        !function_exists('imagejpeg')
    ) {
        throw new RuntimeException(
            'Server par PHP GD extension enable karni hogi.'
        );
    }

    $data = file_get_contents($f['tmp_name']);

    if ($data === false) {
        throw new RuntimeException('Image read nahi ho paayi.');
    }

    $src = @imagecreatefromstring($data);

    if (!$src) {
        throw new RuntimeException(
            'Image open nahi hui. Dusri image try karein.'
        );
    }

    $w = imagesx($src);
    $h = imagesy($src);

    if ($w < 1 || $h < 1) {
        imagedestroy($src);
        throw new RuntimeException('Image dimensions invalid hain.');
    }

    $side = min($w, $h);
    $dst = imagecreatetruecolor(200, 200);

    if (!$dst) {
        imagedestroy($src);
        throw new RuntimeException('Image process nahi ho paayi.');
    }

    // Transparent images ke liye white background.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $sx = (int)(($w - $side) / 2);
    $sy = (int)(($h - $side) / 2);

    $copied = imagecopyresampled(
        $dst,
        $src,
        0, 0,
        $sx, $sy,
        200, 200,
        $side, $side
    );

    if (!$copied) {
        imagedestroy($src);
        imagedestroy($dst);
        throw new RuntimeException('Image resize nahi ho paayi.');
    }

    $dir = __DIR__ . '/uploads/avatars';

    if (
        !is_dir($dir) &&
        !mkdir($dir, 0755, true) &&
        !is_dir($dir)
    ) {
        imagedestroy($src);
        imagedestroy($dst);
        throw new RuntimeException(
            'Avatar folder create nahi ho saka.'
        );
    }

    if (!is_writable($dir)) {
        imagedestroy($src);
        imagedestroy($dst);
        throw new RuntimeException(
            'Avatar folder writable nahi hai.'
        );
    }

    $name = $uid . '_' . bin2hex(random_bytes(8)) . '.jpg';
    $dest = $dir . '/' . $name;

    $saved = imagejpeg($dst, $dest, 85);

    imagedestroy($src);
    imagedestroy($dst);

    if (!$saved) {
        throw new RuntimeException('Image save nahi ho paayi.');
    }

    return $name;
}

$msg = '';
$err = '';

if ($own && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = 'Session expire ho gaya. Page refresh karke try karein.';
    } elseif (($_POST['form'] ?? '') === 'info') {
        $email = trim($_POST['email'] ?? '');

        $genderInput = $_POST['gender'] ?? '';
        $gender = in_array(
            $genderInput,
            ['male', 'female', 'other'],
            true
        ) ? $genderInput : '';

        $bioInput = trim($_POST['bio'] ?? '');
        $bio = function_exists('mb_substr')
            ? mb_substr($bioInput, 0, 300)
            : substr($bioInput, 0, 300);

        if (
            $email !== '' &&
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $err = 'Email sahi nahi hai.';
        } else {
            $newAvatarPath = null;

            try {
                $oldAvatar = $user['avatar'] ?? '';
                $avatar = $oldAvatar;

                $uploadError = $_FILES['avatar']['error']
                    ?? UPLOAD_ERR_NO_FILE;

                if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    $avatar = save_avatar(
                        $_FILES['avatar'],
                        (int)$me['id']
                    );

                    $newAvatarPath =
                        __DIR__ . '/uploads/avatars/' . $avatar;
                }

                // Remove checkbox takes priority over new upload.
                if (!empty($_POST['remove_avatar'])) {
                    $avatar = '';
                }

                db()->prepare(
                    'UPDATE users
                     SET email=?, gender=?, bio=?, avatar=?
                     WHERE id=?'
                )->execute([
                    $email,
                    $gender,
                    $bio,
                    $avatar,
                    $me['id']
                ]);

                // Database save ke baad hi old file delete karein.
                if (
                    $oldAvatar &&
                    $avatar !== $oldAvatar &&
                    basename($oldAvatar) === $oldAvatar
                ) {
                    $oldPath =
                        __DIR__ . '/uploads/avatars/' . $oldAvatar;

                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $msg = 'Profile save ho gaya.';
            } catch (Throwable $ex) {
                // Failed DB save par nayi uploaded file clean up.
                if (
                    $newAvatarPath !== null &&
                    is_file($newAvatarPath)
                ) {
                    @unlink($newAvatarPath);
                }

                error_log('Profile update error: ' . $ex->getMessage());

                $err = $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'Profile save nahi ho paaya. Dobara try karein.';
            }
        }
    } elseif (($_POST['form'] ?? '') === 'pass') {
        if (
            !password_verify(
                $_POST['old'] ?? '',
                $user['password']
            )
        ) {
            $err = 'Purana password galat hai.';
        } elseif (strlen($_POST['new'] ?? '') < 6) {
            $err = 'Naya password kam se kam 6 characters ka hona chahiye.';
        } elseif (
            ($_POST['new'] ?? '') !== ($_POST['new2'] ?? '')
        ) {
            $err = 'Naye password match nahi kar rahe.';
        } else {
            try {
                db()->prepare(
                    'UPDATE users SET password=? WHERE id=?'
                )->execute([
                    password_hash(
                        $_POST['new'],
                        PASSWORD_DEFAULT
                    ),
                    $me['id']
                ]);

                $msg = 'Password change ho gaya.';
            } catch (Throwable $ex) {
                error_log('Password update error: ' . $ex->getMessage());
                $err = 'Password change nahi ho paaya.';
            }
        }
    }

    // Latest profile data reload.
    $st->execute([$id]);
    $user = $st->fetch();

    if (!$user) {
        http_response_code(404);
        exit('User nahi mila.');
    }
}

$cnt = db()->prepare(
    'SELECT COUNT(*) FROM messages WHERE user_id=?'
);
$cnt->execute([$user['id']]);

page_head($user['username']);
?>

<div class="page">
    <p><a href="chat.php">&larr; Chat par wapis</a></p>

    <div class="card profile-card">
        <img
            class="av-lg"
            src="<?= e(avatar_url($user['avatar'])) ?>"
            alt="<?= e($user['username']) ?> profile picture"
            loading="lazy"
        >

        <div>
            <h2>
                <?= e($user['username']) ?>

                <?php if ($user['role'] === 'admin'): ?>
                    <span class="badge">Admin</span>
                <?php endif; ?>

                <?php if ($user['status'] === 'banned'): ?>
                    <span class="badge red">Banned</span>
                <?php endif; ?>
            </h2>

            <p class="muted">
                <?= is_online($user)
                    ? '<span class="dot"></span> Online'
                    : 'Last seen: ' .
                        ($user['last_seen']
                            ? e(time_ago((int)$user['last_seen']))
                            : 'kabhi nahi') ?>
            </p>

            <?php if ($user['gender']): ?>
                <p>Gender: <?= e(ucfirst($user['gender'])) ?></p>
            <?php endif; ?>

            <p>
                Joined:
                <?= e(date('d M Y', (int)$user['created_at'])) ?>
                &middot;
                Messages: <?= (int)$cnt->fetchColumn() ?>
            </p>

            <?php if ($user['bio']): ?>
                <p class="bio"><?= nl2br(e($user['bio'])) ?></p>
            <?php endif; ?>

            <?php if ($me['role'] === 'admin' && !$own): ?>
                <p>
                    <a
                        class="btn"
                        href="admin/users.php?edit=<?= (int)$user['id'] ?>"
                    >Admin me manage karein</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($own): ?>

        <?php if ($msg): ?>
            <div class="alert ok"><?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="alert"><?= e($err) ?></div>
        <?php endif; ?>

        <div class="grid2">
            <form
                class="card"
                method="post"
                enctype="multipart/form-data"
            >
                <h3>Profile edit</h3>

                <?= csrf_field() ?>
                <input type="hidden" name="form" value="info">

                <label>
                    Profile picture
                    <input
                        type="file"
                        name="avatar"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                    >
                </label>

                <p class="muted">
                    JPG, PNG, GIF ya WEBP. Maximum 3 MB.
                </p>

                <?php if (!empty($user['avatar'])): ?>
                    <label class="check">
                        <input
                            type="checkbox"
                            name="remove_avatar"
                            value="1"
                        >
                        Avatar hatayen
                    </label>
                <?php endif; ?>

                <label>
                    Email
                    <input
                        type="email"
                        name="email"
                        value="<?= e($user['email']) ?>"
                    >
                </label>

                <label>
                    Gender
                    <select name="gender">
                        <?php foreach (
                            [
                                '' => '--',
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other'
                            ] as $k => $v
                        ): ?>
                            <option
                                value="<?= e($k) ?>"
                                <?= $user['gender'] === $k
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Bio
                    <textarea
                        name="bio"
                        maxlength="300"
                        rows="3"
                    ><?= e($user['bio']) ?></textarea>
                </label>

                <button type="submit">Save Profile</button>
            </form>

            <form class="card" method="post">
                <h3>Password change</h3>

                <?= csrf_field() ?>
                <input type="hidden" name="form" value="pass">

                <label>
                    Purana password
                    <input
                        type="password"
                        name="old"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <label>
                    Naya password
                    <input
                        type="password"
                        name="new"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >
                </label>

                <label>
                    Naya password dobara
                    <input
                        type="password"
                        name="new2"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >
                </label>

                <button type="submit">Change Password</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php page_foot(); ?>
