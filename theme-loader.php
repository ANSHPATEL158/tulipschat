<?php
/*
 * Include this file before rendering the chat frontend.
 * Example: $chatTheme = require __DIR__ . '/theme-loader.php';
 * Then use the inline style snippets from README.md on your existing elements.
 */
$themeDefaults = [
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
$themePath = __DIR__ . '/themes/current.json';
$stored = is_file($themePath) ? json_decode(file_get_contents($themePath), true) : [];
$chatTheme = array_merge($themeDefaults, is_array($stored) ? $stored : []);
$chatTheme['message_radius'] = max(0, min(32, (int)$chatTheme['message_radius']));
$chatTheme['glow'] = (bool)$chatTheme['glow'];
$chatTheme['wall_image'] = (string)$chatTheme['wall_image'];
return $chatTheme;
