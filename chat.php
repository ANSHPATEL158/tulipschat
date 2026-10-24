
<?php
require __DIR__ . '/core.php';

$me = require_login();
page_head('Chat');

$ann = setting('announcement');

$tcAddonRoot = __DIR__ . '/addons';
$tcRegistryRaw = @file_get_contents($tcAddonRoot . '/registry.json');
$tcRegistry = is_string($tcRegistryRaw)
    ? json_decode($tcRegistryRaw, true)
    : null;

$tcEnabledAddons = [];

if (is_array($tcRegistry)) {
    foreach ($tcRegistry as $tcId => $tcInfo) {
        if (
            is_string($tcId)
            && preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $tcId)
            && !empty($tcInfo['enabled'])
            && is_dir($tcAddonRoot . '/' . $tcId)
        ) {
            $tcEnabledAddons[$tcId] = true;
        }
    }
}

$tcYouTubeEnabled = !empty($tcEnabledAddons['youtube_embed']);
$tcGiphyEnabled = !empty($tcEnabledAddons['giphy_gifs']);
?>

<div class="chat-app">
  <header class="topbar">
    <button
      class="icon-btn"
      id="toggleUsers"
      type="button"
      title="Users"
    >&#9776;</button>

    <strong><?= e(setting('site_name', 'TulipsChat')) ?></strong>

    <nav>
      <a href="profile.php">
        <img
          class="av-sm"
          src="<?= e(avatar_url($me['avatar'])) ?>"
          alt=""
        >
        <?= e($me['username']) ?>
      </a>

      <?php if ($me['role'] === 'admin'): ?>
        <a href="admin/">Admin</a>
      <?php endif; ?>

      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <div class="chat-body">
    <main class="chat-main">

      <?php if ($ann !== ''): ?>
        <div class="announce"><?= e($ann) ?></div>
      <?php endif; ?>

      <div id="messages" class="messages"></div>

      <form id="sendForm" class="send-bar" autocomplete="off">
        <input
          id="msgInput"
          maxlength="<?= (int)setting('max_length', '500') ?>"
          placeholder="Message likhein..."
          required
        >

        <?php if ($tcYouTubeEnabled): ?>
          <button
            type="button"
            id="tcYouTubeOpen"
            title="Search YouTube"
          >&#9654; YouTube</button>
        <?php endif; ?>

        <?php if ($tcGiphyEnabled): ?>
          <button
            type="button"
            class="tc-giphy-open"
            id="tcGiphyOpen"
            title="Search GIFs"
          >GIFs</button>
        <?php endif; ?>

        <button type="submit">Send</button>
      </form>

      <?php if ($tcYouTubeEnabled): ?>
        <section
          id="tcYouTubePanel"
          class="tc-yt-panel"
          aria-label="YouTube search"
          hidden
        >
          <div class="tc-yt-heading">
            <strong>YouTube Search</strong>

            <button
              type="button"
              id="tcYouTubeClose"
              aria-label="Close YouTube search"
            >&times;</button>
          </div>

          <form id="tcYouTubeSearchForm" class="tc-yt-search">
            <input
              id="tcYouTubeQuery"
              type="search"
              maxlength="100"
              placeholder="Video ka naam search karein..."
              required
            >

            <button type="submit">Search</button>
          </form>

          <p id="tcYouTubeStatus" role="status"></p>

          <div id="tcYouTubeResults" class="tc-yt-results"></div>

          <div
            id="tcYouTubePreview"
            class="tc-yt-preview"
            hidden
          ></div>
        </section>
      <?php endif; ?>

    </main>

    <aside class="userlist" id="userlist">
      <div class="ul-head">
        Online (<span id="onCount">0</span>)
      </div>

      <div id="users"></div>
    </aside>
  </div>
</div>

<script>
window.AK = {
  csrf: <?= json_encode(csrf()) ?>,
  me: <?= (int)$me['id'] ?>,
  admin: <?= $me['role'] === 'admin' ? 'true' : 'false' ?>,
  poll: <?= (int)$CFG['poll_ms'] ?>
};
</script>

<?php
// Load CSS and JavaScript from enabled add-ons only.
// Add-on PHP files are not executed here.

if (is_array($tcRegistry)) {
    foreach ($tcRegistry as $tcId => $tcInfo) {
        if (
            !is_string($tcId)
            || !preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $tcId)
            || empty($tcInfo['enabled'])
        ) {
            continue;
        }

        $tcDir = realpath($tcAddonRoot . '/' . $tcId);

        if (!$tcDir || !is_dir($tcDir)) {
            continue;
        }

        $tcPublic = $tcDir . DIRECTORY_SEPARATOR . 'public';

        if (!is_dir($tcPublic)) {
            continue;
        }

        $tcFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $tcPublic,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($tcFiles as $tcFile) {
            if (!$tcFile->isFile() || $tcFile->isLink()) {
                continue;
            }

            $tcExt = strtolower($tcFile->getExtension());

            if (!in_array($tcExt, ['css', 'js'], true)) {
                continue;
            }

            $tcReal = realpath($tcFile->getPathname());

            if (
                !$tcReal
                || !str_starts_with(
                    $tcReal,
                    $tcPublic . DIRECTORY_SEPARATOR
                )
            ) {
                continue;
            }

            $tcRelative = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($tcReal, strlen($tcDir) + 1)
            );

            $tcUrl = base_url()
                . 'addons/'
                . rawurlencode($tcId)
                . '/'
                . implode(
                    '/',
                    array_map(
                        'rawurlencode',
                        explode('/', $tcRelative)
                    )
                );

            if ($tcExt === 'css') {
                echo '<link rel="stylesheet" href="' . e($tcUrl) . '">' . "\n";
            } else {
                echo '<script src="' . e($tcUrl) . '"></script>' . "\n";
            }
        }
    }
}

// Read the existing GIPHY configuration, if enabled.
$tcGiphyKey = '';

if ($tcGiphyEnabled) {
    $tcConfigPath = $tcAddonRoot . '/giphy_gifs/config.json';

    $tcConfig = is_file($tcConfigPath)
        ? json_decode((string)file_get_contents($tcConfigPath), true)
        : null;

    if (
        is_array($tcConfig)
        && isset($tcConfig['api_key'])
        && is_string($tcConfig['api_key'])
    ) {
        $tcGiphyKey = $tcConfig['api_key'];
    }
}

echo '<script>window.TC_GIPHY_API_KEY = '
    . json_encode(
        $tcGiphyKey,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    )
    . ';</script>';
?>

<script src="assets/chat.js"></script>

<?php page_foot(); ?>
