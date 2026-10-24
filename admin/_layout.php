<?php
require dirname(__DIR__) . '/core.php';
$me = require_admin();

function admin_head(string $title, string $active): void {
    page_head('Admin - ' . $title, true);
    $links = ['index.php' => 'Dashboard', 'users.php' => 'Users', 'messages.php' => 'Messages', 'settings.php' => 'Settings', 'addons.php' => 'Add-ons'];
    echo '<div class="admin-wrap"><aside class="admin-nav"><div class="brand">' . e(setting('site_name', 'TulipsChat')) . ' Admin</div>';
    foreach ($links as $f => $t) echo '<a href="' . $f . '"' . ($f === $active ? ' class="on"' : '') . '>' . $t . '</a>';
    echo '<a href="../chat.php">&larr; Chat</a><a href="../logout.php">Logout</a></aside><main class="admin-main"><h1>' . e($title) . '</h1>';
}
function admin_foot(): void { echo '</main></div>'; page_foot(); }
function flash(?string $m = null): string {
    if ($m !== null) { $_SESSION['flash'] = $m; return ''; }
    $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
    return $m ? '<div class="alert ok">' . e($m) . '</div>' : '';
}
