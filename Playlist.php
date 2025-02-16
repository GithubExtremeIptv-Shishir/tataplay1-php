<?php
// ******** Dont Remove the Credit ********
### Script Credit @Denver1769 🥰  ### //
error_reporting(0);
header("Content-Type: application/json");
date_default_timezone_set('Asia/Kolkata');

$json_url = 'secure/TP_Custom.json';
$json_content = file_get_contents($json_url);

if ($json_content === false || empty($json_content)) {
    echo 'Error: Could not read TP_Custom.json';
    exit;
}

$data = json_decode($json_content, true);
if ($data === null) {
    echo 'Error: Invalid JSON format';
    exit;
}

if (!is_array($data)) {
    echo 'Error: JSON data is not an array';
    exit;
}

// EPG Guide
$m3uContent = "#EXTM3U\n";
$m3uContent .= "x-tvg-url=\"https://www.tsepg.cf/epg.xml.gz\"\n\n";

// Get items by $data
foreach ($data as $channel) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/');

    // Extract channel data
    $channel_id = $channel['channel_id'] ?? '';
    $channel_logo = $channel['channel_logo'] ?? '';
    $channel_genre = $channel['channel_genre'] ?? '';
    $channel_name = $channel['channel_name'] ?? '';
    $channel_url = $channel['channel_url'] ?? '';  

    if (empty($channel_id) || empty($channel_name) || empty($channel_url)) {
        continue;  // Skip if missing essential data
    }

    $UserAgent = "Shraddha/5.0";

    $license_url = "{$protocol}://{$_SERVER['HTTP_HOST']}{$base_path}/jwt.php?id=$channel_id";
    $mpd_url = "{$protocol}://{$_SERVER['HTTP_HOST']}{$base_path}/play.mpd?id=$channel_id";
    
    $catch = 'catchup-type="append" catchup-days="7" catchup-source="&begin={utc:YmdTHMS}&end=${lutc:YmdTHMS}"';

    // Check conditions
    if (strpos($channel_url, 'bpk-tv') !== false) {
        $m3uContent .= "#KODIPROP:inputstream.adaptive.license_type=com.widevine.alpha\n";
        $m3uContent .= "#KODIPROP:inputstream.adaptive.license_key={$license_url}\n";
        $m3uContent .= "#EXTINF:-1 tvg-id=\"$channel_id\" tvg-logo=\"$channel_logo\" $catch group-title=\"$channel_genre\",$channel_name\n";
        $m3uContent .= "#EXTVLCOPT:http-user-agent=$UserAgent\n";
        $m3uContent .= "{$mpd_url}|User-Agent=$UserAgent\n\n";
    } elseif (strpos($channel_url, '.m3u8') !== false) {
        $m3uContent .= "#EXTINF:-1 tvg-id=\"$channel_id\" tvg-logo=\"$channel_logo\" $catch group-title=\"$channel_genre\",$channel_name\n";
        $m3uContent .= "#EXTVLCOPT:http-user-agent=$UserAgent\n";
        $m3uContent .= "{$channel_url}\n\n";
    } elseif (strpos($channel_url, 'tatasky') !== false) {
        $m3uContent .= "#KODIPROP:inputstream.adaptive.license_type=com.widevine.alpha\n";
        $m3uContent .= "#KODIPROP:inputstream.adaptive.license_key={$license_url}\n";
        $m3uContent .= "#EXTINF:-1 tvg-id=\"$channel_id\" tvg-logo=\"$channel_logo\" $catch group-title=\"$channel_genre\",$channel_name\n";
        $m3uContent .= "#EXTVLCOPT:http-user-agent=$UserAgent\n";
        $m3uContent .= "{$channel_url}\n\n";
    }
}

header('Content-Type: text/plain');
echo $m3uContent;
exit;
?>