<?php
require __DIR__ . '/core.php';

// Only logged-in admins can access this page.
$admin = require_admin();

$themeFile = __DIR__ . '/themes/current.json';

$defaults = [
    'name' => 'Chaos Neon',
    'page_bg' => '#090611',
    'panel_bg' => '#160a22',
    'message_bg' => '#101a2b',
    'text_color' => '#f7efff',
    'muted_color' => '#b9a9ca',
    'accent' => '#f02caa',
    'accent2' => '#00d9ff',
    'button_bg' => '#d72d85',
    'button_text' => '#ffffff',
    'border_color' => '#08d9f5',
    'wall_image' => '',
    'message_radius' => 16,
    'glow' => true
];

if (!is_dir(dirname($themeFile))) {
    if (!mkdir(dirname($themeFile), 0750, true) && !is_dir(dirname($themeFile))) {
        http_response_code(500);
        exit('Could not create the themes folder.');
    }
}

$current = $defaults;

if (is_file($themeFile)) {
    $loaded = json_decode((string) file_get_contents($themeFile), true);

    if (is_array($loaded)) {
        $current = array_merge($defaults, $loaded);
    }
}

function chaos_valid_color(mixed $value, string $fallback): string
{
    return is_string($value)
        && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
        ? $value
        : $fallback;
}

function chaos_valid_image_url(string $url): string
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        http_response_code(403);
        exit('Invalid request token. Reload the page and try again.');
    }

    $new = [
        'name' => trim(substr((string) ($_POST['name'] ?? 'Chaos Neon'), 0, 60)),
        'page_bg' => chaos_valid_color($_POST['page_bg'] ?? '', $defaults['page_bg']),
        'panel_bg' => chaos_valid_color($_POST['panel_bg'] ?? '', $defaults['panel_bg']),
        'message_bg' => chaos_valid_color($_POST['message_bg'] ?? '', $defaults['message_bg']),
        'text_color' => chaos_valid_color($_POST['text_color'] ?? '', $defaults['text_color']),
        'muted_color' => chaos_valid_color($_POST['muted_color'] ?? '', $defaults['muted_color']),
        'accent' => chaos_valid_color($_POST['accent'] ?? '', $defaults['accent']),
        'accent2' => chaos_valid_color($_POST['accent2'] ?? '', $defaults['accent2']),
        'button_bg' => chaos_valid_color($_POST['button_bg'] ?? '', $defaults['button_bg']),
        'button_text' => chaos_valid_color($_POST['button_text'] ?? '', $defaults['button_text']),
        'border_color' => chaos_valid_color($_POST['border_color'] ?? '', $defaults['border_color']),
        'wall_image' => chaos_valid_image_url(trim((string) ($_POST['wall_image'] ?? ''))),
        'message_radius' => max(0, min(32, (int) ($_POST['message_radius'] ?? 16))),
        'glow' => isset($_POST['glow'])
    ];

    $json = json_encode(
        $new,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if (
        $json !== false
        && file_put_contents($themeFile, $json . PHP_EOL, LOCK_EX) !== false
    ) {
        $current = $new;
        $message = 'Theme saved successfully.';
    } else {
        $message = 'Could not save theme. Check folder permissions.';
    }
}

$csrf = csrf();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chaos Chat — Theme Creator</title>
</head>
<body style="margin:0;background:#090611;color:#f7efff;font-family:Arial,sans-serif;">
<div style="padding:18px 22px;background:#160a22;border-bottom:3px solid #f02caa;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div style="font-size:25px;font-weight:900;font-style:italic;letter-spacing:1px;">CHAOS <span style="color:#f02caa;">CHAT</span> <span style="font-size:14px;color:#b9a9ca;font-style:normal;">/ ADMIN THEME CREATOR</span></div>
  
<a href="/tulipschat/chat.php"
   style="display:inline-block;padding:10px 14px;background:#d72d85;color:#fff;border-radius:8px;text-decoration:none;font-weight:bold;box-shadow:0 0 12px rgba(215,45,133,.35);">
   View Chat
</a>

</div>

<div style="display:grid;grid-template-columns: minmax(280px, 430px) minmax(280px, 1fr);gap:18px;padding:18px;max-width:1500px;margin:auto;box-sizing:border-box;">
  <form method="post" style="background:#160a22;border:1px solid #49305c;border-radius:14px;padding:18px;box-sizing:border-box;">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h2 style="margin:0 0 6px;color:#ff4cbd;">Theme settings</h2>
    <p style="margin:0 0 18px;color:#b9a9ca;font-size:13px;">Change the colors, then save to apply them to the chat frontend.</p>
    <?php if ($message !== ''): ?>
      <div role="status" style="padding:11px;margin-bottom:14px;background:#102b27;border:1px solid #16c79a;border-radius:8px;color:#a8ffe4;"><?= e($message) ?></div>
    <?php endif; ?>

    <label style="display:block;margin:12px 0 5px;">Theme name</label>
    <input name="name" maxlength="60" value="<?= e($current['name']) ?>" style="box-sizing:border-box;width:100%;padding:10px;border-radius:7px;border:1px solid #49305c;background:#090611;color:#fff;">

    <?php
    $fields = [
      'page_bg' => 'Page background',
      'panel_bg' => 'Header / sidebar background',
      'message_bg' => 'Message bubble background',
      'text_color' => 'Main text',
      'muted_color' => 'Muted text / timestamps',
      'accent' => 'Primary accent (pink)',
      'accent2' => 'Secondary accent (cyan)',
      'button_bg' => 'Button background',
      'button_text' => 'Button text',
      'border_color' => 'Message / input border'
    ];
    foreach ($fields as $key => $label):
    ?>
      <label for="<?= e($key) ?>" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin:13px 0 5px;">
        <span><?= e($label) ?></span>
        <input id="<?= e($key) ?>" type="color" name="<?= e($key) ?>" value="<?= e($current[$key]) ?>" data-theme="<?= e($key) ?>" style="width:48px;height:32px;padding:2px;background:#090611;border:1px solid #49305c;border-radius:6px;">
      </label>
    <?php endforeach; ?>

    <label style="display:block;margin:14px 0 5px;">Chat wall background image URL (optional)</label>
    <input name="wall_image" type="url" placeholder="https://example.com/background.jpg" value="<?= e($current['wall_image']) ?>" style="box-sizing:border-box;width:100%;padding:10px;border-radius:7px;border:1px solid #49305c;background:#090611;color:#fff;">
    <label style="display:block;margin:14px 0 5px;">Message corner roundness: <span id="radiusValue"><?= (int)$current['message_radius'] ?></span>px</label>
    <input id="radius" type="range" min="0" max="32" name="message_radius" value="<?= (int)$current['message_radius'] ?>" style="width:100%;">
    <label style="display:flex;align-items:center;gap:9px;margin:15px 0;"><input type="checkbox" name="glow" value="1" <?= !empty($current['glow']) ? 'checked' : '' ?>> Neon glow effect</label>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
      <button type="submit" style="border:0;background:#d72d85;color:#fff;font-weight:bold;padding:12px 18px;border-radius:8px;cursor:pointer;">Save & Apply Theme</button>
      <button type="button" id="resetPreview" style="border:1px solid #674477;background:#241333;color:#fff;padding:12px 18px;border-radius:8px;cursor:pointer;">Reset preview</button>
    </div>
    <p style="font-size:12px;color:#b9a9ca;line-height:1.5;margin-bottom:0;">Saving updates <code style="color:#00d9ff;">themes/current.json</code>. Ensure this admin page is protected by your actual admin login before deploying.</p>
  </form>

  <section>
    <h2 style="margin:0 0 12px;color:#ff4cbd;">Live preview</h2>
    <div id="preview" style="min-height:540px;overflow:hidden;border:1px solid <?= e($current['border_color']) ?>;border-radius:14px;background:<?= e($current['page_bg']) ?>;color:<?= e($current['text_color']) ?>;box-shadow:<?= !empty($current['glow']) ? '0 0 18px ' . e($current['accent']) . '55' : 'none' ?>;">
      <div id="previewHeader" style="padding:17px;background:<?= e($current['panel_bg']) ?>;border-bottom:3px solid <?= e($current['accent']) ?>;font-weight:900;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <span>CHAOS CHAT</span><span style="font-size:13px;font-weight:normal;">✉ &nbsp; Spidy &nbsp; Admin &nbsp; Logout</span>
      </div>
      <div style="padding:10px 14px;background:<?= e($current['panel_bg']) ?>;border-bottom:1px solid #49305c;color:<?= e($current['accent']) ?>;font-weight:bold;">Welcome to the chat!</div>
      <div id="previewWall" style="padding:22px;min-height:300px;box-sizing:border-box;background-color:<?= e($current['page_bg']) ?>;background-image:<?= $current['wall_image'] !== '' ? 'url("' . e($current['wall_image']) . '")' : 'none' ?>;background-position:center;background-size:cover;">
        <div id="previewBubble" style="max-width:360px;background:<?= e($current['message_bg']) ?>;border:1px solid <?= e($current['border_color']) ?>;border-radius:<?= (int)$current['message_radius'] ?>px;padding:14px;box-shadow:<?= !empty($current['glow']) ? '0 0 12px ' . e($current['accent2']) . '44' : 'none' ?>;">
          <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:10px;"><strong id="previewName" style="color:<?= e($current['accent2']) ?>;">Spidy</strong><span id="previewTime" style="color:<?= e($current['muted_color']) ?>;font-size:13px;">01:23 PM · ↩ Reply</span></div>
          <div id="previewMessage">Welcome to Chaos Chat! ⚡<br><span style="font-size:13px;color:<?= e($current['muted_color']) ?>;">Your theme preview appears here.</span></div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;padding:12px;background:<?= e($current['panel_bg']) ?>;flex-wrap:wrap;">
        <div id="previewInput" style="flex:1;min-width:150px;padding:13px;border:1px solid <?= e($current['border_color']) ?>;border-radius:10px;color:<?= e($current['muted_color']) ?>;">Message likhein...</div>
        <button class="previewButton" type="button" style="border:0;background:<?= e($current['button_bg']) ?>;color:<?= e($current['button_text']) ?>;padding:13px 18px;border-radius:9px;font-weight:bold;">▶ Send</button>
        <button class="previewButton" type="button" style="border:0;background:<?= e($current['button_bg']) ?>;color:<?= e($current['button_text']) ?>;padding:13px 18px;border-radius:9px;font-weight:bold;">GIF</button>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  const defaults = <?= json_encode($defaults, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const colorInputs = document.querySelectorAll('[data-theme]');
  const $ = id => document.getElementById(id);

  function updatePreview() {
    const values = {};
    colorInputs.forEach(input => values[input.dataset.theme] = input.value);
    const radius = Number($('radius').value);
    $('radiusValue').textContent = radius;
    $('preview').style.backgroundColor = values.page_bg;
    $('preview').style.color = values.text_color;
    $('preview').style.borderColor = values.border_color;
    $('preview').style.boxShadow = document.querySelector('[name="glow"]').checked ? '0 0 18px ' + values.accent + '55' : 'none';
    $('previewHeader').style.backgroundColor = values.panel_bg;
    $('previewHeader').style.borderBottomColor = values.accent;
    $('previewWall').style.backgroundColor = values.page_bg;
    $('previewBubble').style.backgroundColor = values.message_bg;
    $('previewBubble').style.borderColor = values.border_color;
    $('previewBubble').style.borderRadius = radius + 'px';
    $('previewBubble').style.boxShadow = document.querySelector('[name="glow"]').checked ? '0 0 12px ' + values.accent2 + '44' : 'none';
    $('previewName').style.color = values.accent2;
    $('previewTime').style.color = values.muted_color;
    $('previewInput').style.borderColor = values.border_color;
    $('previewInput').style.color = values.muted_color;
    document.querySelectorAll('.previewButton').forEach(button => {
      button.style.backgroundColor = values.button_bg;
      button.style.color = values.button_text;
    });
    document.querySelectorAll('#previewHeader, #preview > div:nth-child(2), #preview > div:last-child').forEach(el => el.style.backgroundColor = values.panel_bg);
    const url = document.querySelector('[name="wall_image"]').value.trim();
    $('previewWall').style.backgroundImage = /^https?:\/\/\S+$/i.test(url) ? 'url("' + url.replace(/["\\]/g, '') + '")' : 'none';
    $('previewWall').style.backgroundPosition = 'center';
    $('previewWall').style.backgroundSize = 'cover';
  }
  colorInputs.forEach(input => input.addEventListener('input', updatePreview));
  ['radius', 'wall_image', 'glow'].forEach(name => {
    const el = document.querySelector('[name="' + name + '"]');
    if (el) el.addEventListener('input', updatePreview);
    if (el && el.type === 'checkbox') el.addEventListener('change', updatePreview);
  });
  $('resetPreview').addEventListener('click', () => {
    colorInputs.forEach(input => input.value = defaults[input.dataset.theme]);
    $('radius').value = defaults.message_radius;
    document.querySelector('[name="wall_image"]').value = defaults.wall_image;
    document.querySelector('[name="glow"]').checked = defaults.glow;
    updatePreview();
  });
  updatePreview();
})();
</script>
</body>
</html>
