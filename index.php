<?php
require __DIR__ . '/core.php';
redirect(current_user() ? 'chat.php' : 'login.php');
