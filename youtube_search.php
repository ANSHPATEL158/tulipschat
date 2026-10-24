
<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function yt_json_error(string $message, int $status = 400): void
{
    http_response_code($status);

    echo json_encode(
        ['error' => $message, 'items' => []],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {
    require_once __DIR__ . '/core.php';

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        yt_json_error('Sirf GET request allowed hai.', 405);
    }

    // Sirf logged-in users ko search use karne do.
    require_login();

    $q = trim((string)($_GET['q'] ?? ''));

    if ($q === '') {
        yt_json_error('Search text enter karein.');
    }

    if (strlen($q) > 200) {
        yt_json_error('Search text bahut lamba hai.');
    }

    // API key server-side environment variable mein rakhein.
    $apiKey = getenv('YOUTUBE_API_KEY') ?: '';

    if ($apiKey === '') {
        yt_json_error(
            'YouTube API key configure nahi hai. Server settings check karein.',
            503
        );
    }

    $url = 'https://www.googleapis.com/youtube/v3/search?' .
        http_build_query([
            'part' => 'snippet',
            'type' => 'video',
            'maxResults' => 10,
            'safeSearch' => 'moderate',
            'q' => $q,
            'key' => $apiKey,
        ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        yt_json_error('YouTube server se connection nahi ho paya.', 502);
    }

    $api = json_decode($body, true);

    if (!is_array($api)) {
        yt_json_error('YouTube server ka response invalid hai.', 502);
    }

    if (!empty($api['error'])) {
        error_log(
            'YouTube API error: ' .
            ($api['error']['message'] ?? 'Unknown API error')
        );

        yt_json_error(
            'YouTube API request fail hui. API key, permissions aur quota check karein.',
            502
        );
    }

    $items = [];

    foreach (($api['items'] ?? []) as $item) {
        $id = $item['id']['videoId'] ?? '';
        $snippet = $item['snippet'] ?? [];

        if (!is_string($id) ||
            !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            continue;
        }

        $thumbnails = $snippet['thumbnails'] ?? [];
        $thumbnail = $thumbnails['medium']['url']
            ?? $thumbnails['default']['url']
            ?? '';

        $items[] = [
            'id' => $id,
            'title' => (string)($snippet['title'] ?? 'YouTube video'),
            'channel' => (string)($snippet['channelTitle'] ?? ''),
            'thumbnail' => (string)$thumbnail,
        ];
    }

    echo json_encode(
        ['items' => $items],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $e) {
    error_log('YouTube search endpoint error: ' . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'error' => 'Server par YouTube search mein problem aayi.',
        'items' => [],
    ]);
}
