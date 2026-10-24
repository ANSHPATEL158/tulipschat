<?php

require __DIR__ . '/core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function yt_json(array $data, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Require login without allowing HTML errors to corrupt JSON responses.
try {
    require_login();
} catch (Throwable $e) {
    error_log('YouTube search login error: ' . $e->getMessage());
    yt_json(['error' => 'Login verify nahi ho saka. Dobara login karein.'], 500);
}

$query = trim((string) ($_GET['q'] ?? ''));

if ($query === '' || strlen($query) > 400) {
    yt_json(['error' => 'Search mein 1 se 100 characters enter karein.'], 400);
}

// Validate the query length in characters when mbstring is available.
if (function_exists('mb_strlen') && mb_strlen($query, 'UTF-8') > 100) {
    yt_json(['error' => 'Search mein 1 se 100 characters enter karein.'], 400);
}

try {
    $apiKey = trim((string) setting('youtube_api_key'));
} catch (Throwable $e) {
    error_log('YouTube setting error: ' . $e->getMessage());
    yt_json(['error' => 'YouTube API setting read nahi ho saki.'], 500);
}

if ($apiKey === '') {
    yt_json(['error' => 'Admin Settings mein YouTube API key save karein.'], 503);
}

if (!function_exists('curl_init')) {
    yt_json(['error' => 'PHP cURL extension enabled nahi hai.'], 500);
}

$url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
    'part'       => 'snippet',
    'type'       => 'video',
    'maxResults' => 10,
    'safeSearch' => 'moderate',
    'q'          => $query,
    'key'         => $apiKey,
]);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => false,
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($response === false) {
    error_log('YouTube API connection failed: ' . $curlError);
    yt_json(['error' => 'YouTube se connection nahi ho paya.'], 502);
}

$data = json_decode($response, true);

if ($httpCode !== 200 || !is_array($data)) {
    $reason = $data['error']['errors'][0]['reason'] ?? '';

    error_log(
        'YouTube API HTTP ' . $httpCode . '; reason=' . $reason
    );

    if ($reason === 'quotaExceeded') {
        yt_json([
            'error' => 'YouTube API quota khatam ho gayi hai. Baad mein try karein.'
        ], 429);
    }

    if (in_array($reason, [
        'keyInvalid',
        'ipRefererBlocked',
        'accessNotConfigured',
        'forbidden'
    ], true)) {
        yt_json([
            'error' => 'YouTube API key, restrictions ya API configuration check karein.'
        ], 502);
    }

    yt_json([
        'error' => 'YouTube search fail hua. API key aur API settings check karein.'
    ], 502);
}

$results = [];

foreach (($data['items'] ?? []) as $item) {
    $id = $item['id']['videoId'] ?? '';
    $snippet = $item['snippet'] ?? [];

    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
        continue;
    }

    $thumbs = $snippet['thumbnails'] ?? [];

    $thumbnail = $thumbs['medium']['url']
        ?? $thumbs['default']['url']
        ?? '';

    $results[] = [
        'id'        => $id,
        'title'     => (string) ($snippet['title'] ?? 'YouTube video'),
        'channel'   => (string) ($snippet['channelTitle'] ?? ''),
        'thumbnail' => (string) $thumbnail,
    ];
}

yt_json(['items' => $results]);
