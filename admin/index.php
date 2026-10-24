<?php
require __DIR__ . '/_layout.php';
$q = fn(string $sql, array $p = []) => (function () use ($sql, $p) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
$stats = [
    'Total users'     => $q('SELECT COUNT(*) FROM users'),
    'Online abhi'     => $q('SELECT COUNT(*) FROM users WHERE last_seen > ?', [time() - $CFG['online_timeout']]),
    'Banned'          => $q('SELECT COUNT(*) FROM users WHERE status = ?', ['banned']),
    'Total messages'  => $q('SELECT COUNT(*) FROM messages'),
    'Aaj ke messages' => $q('SELECT COUNT(*) FROM messages WHERE created_at >= ?', [strtotime('today')]),
    'Aaj ke naye users' => $q('SELECT COUNT(*) FROM users WHERE created_at >= ?', [strtotime('today')]),
];
$newest = db()->query('SELECT id, username, created_at FROM users ORDER BY id DESC LIMIT 8')->fetchAll();
admin_head('Dashboard', 'index.php');
?>
<div class="stats">
  <?php foreach ($stats as $k => $v): ?><div class="stat"><b><?= (int)$v ?></b><span><?= e($k) ?></span></div><?php endforeach; ?>
</div>
<div class="card">
  <h3>Naye users</h3>
  <table><tr><th>User</th><th>Joined</th></tr>
  <?php foreach ($newest as $u): ?>
    <tr><td><a href="users.php?edit=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a></td><td><?= e(date('d M Y h:i A', (int)$u['created_at'])) ?></td></tr>
  <?php endforeach; ?>
  </table>
</div>
<?php admin_foot();
