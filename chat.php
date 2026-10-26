<?php
require __DIR__ . '/core.php';

$me = require_login();
page_head('Chat');

$ann = setting('announcement');

$tcAddonRoot = __DIR__ . '/addons';
$tcRegistryPath = $tcAddonRoot . '/registry.json';

$tcRegistryRaw = is_file($tcRegistryPath)
    ? file_get_contents($tcRegistryPath)
    : false;

$tcRegistry = is_string($tcRegistryRaw)
    ? json_decode($tcRegistryRaw, true)
    : null;

$tcEnabledAddons = [];

if (is_array($tcRegistry)) {
    foreach ($tcRegistry as $tcId => $tcInfo) {
        if (
            is_string($tcId)
            && preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $tcId)
            && is_array($tcInfo)
            && !empty($tcInfo['enabled'])
            && is_dir($tcAddonRoot . '/' . $tcId)
        ) {
            $tcEnabledAddons[$tcId] = true;
        }
    }
}

$tcYouTubeEnabled = !empty($tcEnabledAddons['youtube_embed']);
$tcGiphyEnabled = !empty($tcEnabledAddons['giphy_gifs']);
$tcKlipyEnabled = !empty($tcEnabledAddons['klipy_gifs']);

/*
 * KLIPY API key:
 * Admin Settings mein saved key read karein.
 * API key ko HTML mein safely encode karke expose kiya jata hai.
 */
$tcKlipyKey = $tcKlipyEnabled
    ? trim((string) setting('klipy_api_key', ''))
    : '';

/*
 * GIPHY configuration.
 */
$tcGiphyKey = '';

if ($tcGiphyEnabled) {
    $tcConfigPath = $tcAddonRoot . '/giphy_gifs/config.json';

    if (is_file($tcConfigPath)) {
        $tcConfigRaw = file_get_contents($tcConfigPath);
        $tcConfig = is_string($tcConfigRaw)
            ? json_decode($tcConfigRaw, true)
            : null;

        if (
            is_array($tcConfig)
            && isset($tcConfig['api_key'])
            && is_string($tcConfig['api_key'])
        ) {
            $tcGiphyKey = trim($tcConfig['api_key']);
        }
    }
}
?>

<div class="chat-app">
  <header class="topbar">
    <button
      class="icon-btn"
      id="toggleUsers"
      type="button"
      title="Users"
      aria-label="Toggle users"
    >&#9776;

<button type="button" onclick="refreshWall()"
style="padding:4px 8px;
background:#e75480;
color:white;
border:none;
border-radius:5px;
cursor:pointer;
font-size:11px;
font-weight:bold;">
🔄 Refresh Wall
</button>



<div id="wall-container" class="wall-scroll">
    <!-- Your existing PHP wall messages -->
</div>
<script>
function refreshWall() {
    window.location.reload();
}
</script>

    <strong><?= e(setting('site_name', 'Chaos Chat')) ?></strong>

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
          name="message"
          maxlength="<?= (int) setting('max_length', '500') ?>"
          placeholder="Message likhein..."
          aria-label="Write a message"
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
  me: <?= (int) $me['id'] ?>,
  admin: <?= $me['role'] === 'admin' ? 'true' : 'false' ?>,
  poll: <?= (int) $CFG['poll_ms'] ?>
};

window.TC_KLIPY_APP_KEY = <?= json_encode(
    $tcKlipyKey,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

window.TC_GIPHY_API_KEY = <?= json_encode(
    $tcGiphyKey,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
</script>

<?php
/*
 * Load CSS and JavaScript from enabled add-ons only.
 * Add-on PHP files are not executed here.
 */
if (is_array($tcRegistry)) {
    foreach ($tcRegistry as $tcId => $tcInfo) {
        if (
            !is_string($tcId)
            || !preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $tcId)
            || !is_array($tcInfo)
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

        try {
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
                    echo '<link rel="stylesheet" href="'
                        . e($tcUrl)
                        . '">' . "\n";
                } else {
                    echo '<script src="'
                        . e($tcUrl)
                        . '"></script>' . "\n";
                }
            }
        } catch (Throwable $e) {
            error_log(
                'Chat add-on asset loading failed for '
                . $tcId . ': ' . $e->getMessage()
            );
        }
    }
}
?>

<style>
/* ===== CHAT COMPOSER ===== */
#sendForm.send-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #2c3442;
    border-radius: 16px;
    background: #11151d;
    box-shadow: 0 5px 18px rgba(0, 0, 0, .18);
}

#sendForm #msgInput {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
    height: 48px;
    box-sizing: border-box;
    padding: 0 14px;
    border: 1px solid #303949;
    border-radius: 12px;
    outline: none;
    background: #0c1017;
    color: #fff;
    font-size: 14px;
    transition: border-color .2s, box-shadow .2s;
}

#sendForm #msgInput:focus {
    border-color: #687fff;
    box-shadow: 0 0 0 3px rgba(104, 127, 255, .12);
}

/* ===== COMPOSER BUTTONS ===== */
#sendForm > button,
#sendForm > .upload-btn {
    flex: 0 0 62px;
    width: 62px;
    min-width: 62px;
    height: 62px;
    min-height: 62px;
    box-sizing: border-box;
    padding: 5px;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border: 1px solid #343d4e;
    border-radius: 13px;
    background: linear-gradient(145deg, #252c3a, #171c26);
    color: #f5f7ff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.1;
    text-align: center;
    cursor: pointer;
    transition:
        transform .18s ease,
        background .18s ease,
        border-color .18s ease,
        box-shadow .18s ease;
}

#sendForm > button:hover,
#sendForm > .upload-btn:hover {
    transform: translateY(-2px);
    border-color: #7888a5;
    background: linear-gradient(145deg, #343e51, #202735);
    box-shadow: 0 5px 14px rgba(0, 0, 0, .22);
}

#sendForm > button:active,
#sendForm > .upload-btn:active {
    transform: scale(.96);
}

/* ===== YOUTUBE BUTTON ===== */
#sendForm #tcYouTubeOpen {
    border-color: #5149ec;
    background: linear-gradient(145deg, #6257ff, #4035d6);
    color: #fff;
}

#sendForm #tcYouTubeOpen:hover {
    border-color: #918aff;
    box-shadow: 0 0 15px rgba(98, 87, 255, .25);
}

/* ===== KLIPY BUTTON ===== */
#sendForm #tcKlipyButton {
    border-color: #414958;
    background: linear-gradient(145deg, #202633, #10131b);
    color: #fff;
}

#sendForm #tcKlipyButton:hover {
    border-color: #f5c928;
    box-shadow: 0 0 13px rgba(245, 201, 40, .16);
}

/* ===== GIPHY BUTTON ===== */
#sendForm #tcGiphyOpen {
    border-color: #b64cf5;
    background: linear-gradient(145deg, #9b43db, #6329a9);
    color: #fff;
}

/* ===== UPLOAD BUTTON ===== */
#sendForm > .upload-btn {
    background: linear-gradient(145deg, #252d3b, #171c25);
}

#sendForm > .upload-btn svg,
#sendForm > .upload-btn img {
    width: 23px;
    height: 23px;
    object-fit: contain;
}

/* ===== SEND BUTTON ===== */
#sendForm > button[type="submit"] {
    border-color: #168d73;
    background: linear-gradient(145deg, #18b994, #087b68);
    color: #fff;
}

#sendForm > button[type="submit"]:hover {
    border-color: #4ce6bf;
    background: linear-gradient(145deg, #20d0a7, #0a9278);
    box-shadow: 0 0 15px rgba(24, 185, 148, .22);
}

/* ===== YOUTUBE PANEL ===== */
.tc-yt-search button,
.tc-yt-heading button {
    min-height: 40px;
    padding: 8px 13px;
    border: 1px solid #414b5d;
    border-radius: 9px;
    background: #222a39;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, border-color .2s;
}

.tc-yt-search button:hover,
.tc-yt-heading button:hover {
    background: #303b4e;
    border-color: #7888a5;
}

/* ===== MOBILE ===== */
@media (max-width: 600px) {
    #sendForm.send-bar {
        gap: 5px;
        padding: 6px;
        border-radius: 12px;
    }

    #sendForm #msgInput {
        height: 48px;
        padding: 0 9px;
        font-size: 13px;
    }

    #sendForm > button,
    #sendForm > .upload-btn {
        flex-basis: 49px;
        width: 49px;
        min-width: 49px;
        height: 52px;
        min-height: 52px;
        gap: 4px;
        padding: 3px;
        border-radius: 9px;
        font-size: 9px;
    }

    #sendForm > .upload-btn svg,
    #sendForm > .upload-btn img {
        width: 20px;
        height: 20px;
    }
}
</style>

<script src="assets/chat.js"></script>

<?php page_foot(); ?>
