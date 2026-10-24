<?php
require __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'delete') {
        db()->prepare('DELETE FROM messages WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Message delete ho gaya');
    } elseif ($act === 'bulk' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        db()->exec('DELETE FROM messages WHERE id IN (' . implode(',', $ids) . ')');
        flash(count($ids) . ' messages delete ho gaye');
    } elseif ($act === 'clear_all') {
        db()->exec('DELETE FROM messages');
        flash('Chat poori saaf ho gayi');
    } elseif ($act === 'clear_old') {
        $days = max(1, (int)($_POST['days'] ?? 30));
        db()->prepare('DELETE FROM messages WHERE created_at < ?')->execute([time() - $days * 86400]);
        flash("$days din se purane messages delete");
    }
    redirect('admin/messages.php?' . http_build_query(['q' => $_POST['q'] ?? '', 'p' => $_POST['p'] ?? 1]));
}

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 50;
$where = ''; $params = [];
if ($q !== '') { $where = 'WHERE m.message LIKE ? OR u.username LIKE ?'; $params = ["%$q%", "%$q%"]; }
$c = db()->prepare("SELECT COUNT(*) FROM messages m JOIN users u ON u.id=m.user_id $where"); $c->execute($params);
$pages = max(1, (int)ceil($c->fetchColumn() / $per));
$off = ($page - 1) * $per;
$st = db()->prepare("SELECT m.*, u.username FROM messages m JOIN users u ON u.id=m.user_id $where ORDER BY m.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();

admin_head('Messages', 'messages.php');
echo flash();
?>
<form class="search" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Message ya username"><button>Search</button></form>
<form method="post" id="bulk">
  <?= csrf_field() ?><input type="hidden" name="act" value="bulk">
  <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="p" value="<?= $page ?>">
  <div class="card table-wrap">
  <table>
    <tr><th><input type="checkbox" onclick="document.querySelectorAll('.ck').forEach(c=>c.checked=this.checked)"></th><th>User</th><th>Message</th><th>Time</th><th></th></tr>
    <?php foreach ($rows as $m): ?>
    <tr>
      <td><input type="checkbox" class="ck" name="ids[]" value="<?= (int)$m['id'] ?>"></td>
      <td><a href="users.php?edit=<?= (int)$m['user_id'] ?>"><?= e($m['username']) ?></a></td>
      <td class="msgcell"><?= e($m['message']) ?></td>
      <td class="nowrap"><?= e(date('d M h:i A', (int)$m['created_at'])) ?></td>
      <td><button class="sm danger" form="del<?= (int)$m['id'] ?>">Delete</button></td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">Koi message nahi</td></tr><?php endif; ?>
  </table>
  </div>
  <button class="danger" onclick="return confirm('Selected messages delete?')">Selected delete</button>
</form>
<?php foreach ($rows as $m): ?>
<form id="del<?= (int)$m['id'] ?>" method="post" class="hidden"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="p" value="<?= $page ?>"></form>
<?php endforeach; ?>
<div class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e(http_build_query(['q' => $q, 'p' => $i])) ?>"><?= $i ?></a><?php endfor; ?>
</div>
<div class="card actions">
  <form method="post" class="inline" onsubmit="return confirm('Purane messages delete?')"><?= csrf_field() ?><input type="hidden" name="act" value="clear_old">
    <input type="number" name="days" value="30" min="1" class="days"> din se purane <button class="sm warn">Delete</button></form>
  <form method="post" class="inline" onsubmit="return confirm('Saari chat delete? Wapis nahi aayegi')"><?= csrf_field() ?><input type="hidden" name="act" value="clear_all">
    <button class="sm danger">Poori chat clear</button></form>
</div>
<?php admin_foot();
