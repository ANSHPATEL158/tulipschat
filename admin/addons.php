<?php
require __DIR__ . '/_layout.php';

$addonsRoot = dirname(__DIR__) . '/addons';
$registryFile = $addonsRoot . '/registry.json';
if (!is_dir($addonsRoot)) @mkdir($addonsRoot, 0755, true);
if (!is_file($registryFile)) @file_put_contents($registryFile, "{}", LOCK_EX);

function tc_addon_registry_read(string $file): array {
    $raw = @file_get_contents($file);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}
function tc_addon_registry_write(string $file, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return is_string($json) && file_put_contents($file, $json, LOCK_EX) !== false;
}
function tc_addon_id_ok(string $id): bool { return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $id); }
function tc_addon_remove_tree(string $path): void {
    if (is_link($path) || !is_dir($path)) { if (is_file($path) || is_link($path)) @unlink($path); return; }
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) continue;
        $p = $item->getPathname();
        if ($item->isDir() && !$item->isLink()) tc_addon_remove_tree($p); else @unlink($p);
    }
    @rmdir($path);
}
function tc_addon_safe_archive_name(string $name): bool {
    if ($name === '' || str_contains($name, "\0")) return false;
    $name = str_replace('\\', '/', $name);
    if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) || preg_match('~(^|/)\.\.?(/|$)~', $name)) return false;
    if (str_contains($name, '//')) return false;
    $base = basename($name);
    if (strtolower($base) === '.htaccess') return false;
    if (str_ends_with($name, '/')) return true;
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    return in_array($ext, ['json','js','css','png','jpg','jpeg','gif','webp','woff','woff2','ttf','txt'], true);
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $msg = 'Security token invalid. Page refresh karke dobara try karein.';
    else {
        $registry = tc_addon_registry_read($registryFile);
        $action = (string)($_POST['action'] ?? '');
        $id = (string)($_POST['id'] ?? '');
        if (($action === 'enable' || $action === 'disable') && tc_addon_id_ok($id) && isset($registry[$id]) && is_dir($addonsRoot . '/' . $id)) {
            $registry[$id]['enabled'] = $action === 'enable';
            $registry[$id]['updated_at'] = date('c');
            $msg = tc_addon_registry_write($registryFile, $registry) ? 'Add-on status update ho gaya.' : 'Registry save nahi ho saki.';
        } elseif ($action === 'uninstall' && tc_addon_id_ok($id) && isset($registry[$id])) {
            tc_addon_remove_tree($addonsRoot . '/' . $id);
            unset($registry[$id]);
            $msg = tc_addon_registry_write($registryFile, $registry) ? 'Add-on uninstall ho gaya.' : 'Registry save nahi ho saki.';
        } elseif ($action === 'upload') {
            if (!class_exists('ZipArchive')) $msg = 'PHP ZipArchive extension enabled nahi hai.';
            elseif (empty($_FILES['package']) || $_FILES['package']['error'] !== UPLOAD_ERR_OK) $msg = 'ZIP upload fail hua. File size aur PHP upload limits check karein.';
            elseif ((int)$_FILES['package']['size'] > 10 * 1024 * 1024) $msg = 'ZIP maximum 10 MB ka ho sakta hai.';
            else {
                $zip = new ZipArchive();
                if ($zip->open($_FILES['package']['tmp_name']) !== true) $msg = 'Valid ZIP file select karein.';
                else {
                    $manifest = null; $total = 0; $bad = $zip->numFiles < 1 || $zip->numFiles > 300; $seen = [];
                    for ($i = 0; $i < $zip->numFiles && !$bad; $i++) {
                        $st = $zip->statIndex($i);
                        if (!$st) { $bad = true; break; }
                        $name = str_replace('\\', '/', (string)$st['name']);
                        if (!tc_addon_safe_archive_name($name) || isset($seen[$name])) { $bad = true; break; }
                        $seen[$name] = true;
                        // Reject Unix symlinks and other special file types.
                        $opsys = 0; $attr = 0;
                        if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX) {
                            $type = ($attr >> 16) & 0170000;
                            if ($type !== 0 && $type !== 0100000 && $type !== 0040000) { $bad = true; break; }
                        }
                        $total += (int)($st['size'] ?? 0);
                        if ($total > 30 * 1024 * 1024) { $bad = true; break; }
                        if ($name === 'manifest.json') {
                            $raw = $zip->getFromIndex($i);
                            $manifest = is_string($raw) ? json_decode($raw, true) : null;
                        }
                    }
                    if ($bad) $msg = 'ZIP mein unsafe path, unsupported file type, duplicate path, ya size limit issue hai. PHP/HTML files allowed nahi hain.';
                    elseif (!is_array($manifest)) $msg = 'ZIP ke root mein valid manifest.json hona zaroori hai.';
                    else {
                        $id = (string)($manifest['id'] ?? '');
                        $name = trim((string)($manifest['name'] ?? ''));
                        $version = trim((string)($manifest['version'] ?? '1.0.0'));
                        $description = trim((string)($manifest['description'] ?? ''));
                        if (!tc_addon_id_ok($id) || $name === '' || strlen($name) > 100) $msg = 'manifest.json mein valid id aur name dein.';
                        elseif (isset($registry[$id]) || file_exists($addonsRoot . '/' . $id)) $msg = 'Ye add-on pehle se installed hai. Pehle uninstall karein.';
                        else {
                            $stage = $addonsRoot . '/.stage_' . bin2hex(random_bytes(6));
                            if (!@mkdir($stage, 0755, true)) $msg = 'Add-ons folder writable nahi hai.';
                            else {
                                $extractOk = true;
                                // Extract manually after validation; never allow executable server-side files.
                                for ($i = 0; $i < $zip->numFiles && $extractOk; $i++) {
                                    $st = $zip->statIndex($i); $nameInZip = str_replace('\\', '/', (string)$st['name']);
                                    if (str_ends_with($nameInZip, '/')) { $dir = $stage . '/' . rtrim($nameInZip, '/'); if (!is_dir($dir) && !@mkdir($dir, 0755, true)) $extractOk = false; continue; }
                                    $dest = $stage . '/' . $nameInZip; $dir = dirname($dest);
                                    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $extractOk = false; break; }
                                    $in = $zip->getStream($nameInZip); $out = @fopen($dest, 'xb');
                                    if (!$in || !$out) { if (is_resource($in)) fclose($in); if (is_resource($out)) fclose($out); $extractOk = false; break; }
                                    while (!feof($in)) { $chunk = fread($in, 8192); if ($chunk === false || fwrite($out, $chunk) !== strlen($chunk)) { $extractOk = false; break; } }
                                    fclose($in); fclose($out);
                                }
                                if (!$extractOk) { tc_addon_remove_tree($stage); $msg = 'ZIP extract nahi ho saki.'; }
                                else {
                                    $target = $addonsRoot . '/' . $id;
                                    if (!@rename($stage, $target)) { tc_addon_remove_tree($stage); $msg = 'Add-on folder create nahi ho saka.'; }
                                    else {
                                        $registry[$id] = ['name'=>$name, 'id'=>$id, 'version'=>$version, 'description'=>$description, 'enabled'=>false, 'installed_at'=>date('c'), 'updated_at'=>date('c')];
                                        if (tc_addon_registry_write($registryFile, $registry)) $msg = 'Add-on install ho gaya. Ab Enable kar sakte ho.';
                                        else { tc_addon_remove_tree($target); $msg = 'Registry save nahi hui; add-on folder rollback kar diya.'; }
                                    }
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            }
        } else $msg = 'Invalid action.';
    }
}
$registry = tc_addon_registry_read($registryFile);
admin_head('Add-ons', 'addons.php');
if ($msg !== '') echo '<div class="alert">' . e($msg) . '</div>';
?>
<div class="card narrow">
  <h3>ZIP Add-on Upload</h3>
  <p class="muted">ZIP ke root mein manifest.json hona chahiye. Maximum ZIP 10 MB; extracted content 30 MB tak. PHP/HTML files reject hoti hain. Sirf trusted add-ons upload karein, kyunki JavaScript chat page par run hota hai.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <label>ZIP package<input type="file" name="package" accept=".zip,application/zip" required></label>
    <button type="submit">Upload &amp; Install</button>
  </form>
</div>
<div class="card">
  <h3>Installed Add-ons</h3>
  <?php if (!$registry): ?><p class="muted">Abhi koi add-on install nahi hai.</p><?php else: ?>
  <table><tr><th>Name</th><th>Version</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($registry as $key => $a): $safeId = (string)($a['id'] ?? $key); if (!tc_addon_id_ok($safeId) || !is_dir($addonsRoot . '/' . $safeId)) continue; ?>
    <tr><td><b><?= e((string)($a['name'] ?? $safeId)) ?></b><br><small><?= e((string)($a['description'] ?? '')) ?></small></td>
    <td><?= e((string)($a['version'] ?? '')) ?></td><td><?= !empty($a['enabled']) ? 'Enabled' : 'Disabled' ?></td><td>
      <form method="post" style="display:inline-block"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($safeId) ?>"><input type="hidden" name="action" value="<?= !empty($a['enabled']) ? 'disable' : 'enable' ?>"><button type="submit"><?= !empty($a['enabled']) ? 'Disable' : 'Enable' ?></button></form>
      <form method="post" style="display:inline-block" onsubmit="return confirm('Is add-on ko permanently uninstall karein?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($safeId) ?>"><input type="hidden" name="action" value="uninstall"><button type="submit">Uninstall</button></form>
    </td></tr>
  <?php endforeach; ?></table>
  <?php endif; ?>
</div>
<?php admin_foot();
