<?php
require __DIR__ . '/core.php';
if ($u = current_user()) {
    db()->prepare('UPDATE users SET last_seen=0 WHERE id=?')->execute([$u['id']]);
}
$_SESSION = [];
session_destroy();
redirect('login.php');
