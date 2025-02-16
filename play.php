<?php
error_reporting(0);
ini_set('display_errors', 0);

$id = $_GET['id'] ?? exit(json_encode(['error' => 'Missing id!']));

$catchupRequest = false;
$beginTimestamp = $endTimestamp = null;
if (isset($_GET['begin'], $_GET['end'])) {
   // for TiviMate & Ott Navigator
    $catchupRequest = true;
    $beginTimestamp = intval($_GET['begin']);
    $endTimestamp = intval($_GET['end']);
    $beginFormatted = gmdate('Ymd\THis', $beginTimestamp);
    $endFormatted = gmdate('Ymd\THis', $endTimestamp);
}

// Get the current protocol (http or https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/');

// Fetch the HMAC value
$hmacUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}" . ($base_path ? $base_path : '') . "/hmac.php?id=" . urlencode($id);
$hmacResponse = @file_get_contents($hmacUrl);
if ($hmacResponse === false) {
    echo '<h1>Error: Failed to retrieve HMAC</h1>';
    exit;
}

// Decode the HMAC response
$hmacData = json_decode($hmacResponse, true);
$hdntlRaw = $hmacData['hmac']['hdntl']['value'] ?? null;
$userAgent = $hmacData['userAgent'] ?? null;

if (!$hdntlRaw) {
    echo '<h1>Error: hdntl value not found</h1>';
    exit;
}

// Remove the "?" from the HMAC value
$hmac = ltrim($hdntlRaw, '?');

// get manifest url by $id
$channelInfo = getChannelInfo($id);
$dashUrl = $channelInfo['channel_url'] ?? exit;

// redirect to $dashUrl
if (strpos($dashUrl, 'https://bpaita') !== 0) {
    header("Location: $dashUrl");
    exit;
}

// if Timestamp then process catchup url
if ($catchupRequest) {
	$dashUrl = str_replace("bpaita", "bpaicatchupta", $dashUrl);
    $dashUrl .= "&begin=$beginTimestamp&end=$endTimestamp";
}

// ******** Dont Remove the Credit ********
### Script Credit @Denver1769 🥰  ### //

// fetch $dashUrl URL
$manifestContent = fetchMPDManifest($dashUrl, $userAgent, $hmac) ?? exit;
//echo $manifestContent;
$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl, $userAgent, $catchupRequest, $hmac);
    $processedManifest = str_replace('dash/', "$baseUrl/dash/", $manifestContent);
    if ($widevinePssh) {
    
    $staticReplacements = [
        '<!-- Created with Broadpeak BkS350 Origin Packager  (version=1.12.8-28913) -->' => '<!-- Modified by Your bro Denver1769 (version=3.0) -->',
        '<ContentProtection value="cenc" schemeIdUri="urn:mpeg:dash:mp4protection:2011"/>' => '<!-- Common Encryption -->
          <ContentProtection schemeIdUri="urn:mpeg:dash:mp4protection:2011" value="cenc" cenc:default_KID="' . $widevinePssh['kid'] . '">
          </ContentProtection>',
        '<ContentProtection schemeIdUri="urn:uuid:edef8ba9-79d6-4ace-a3c8-27dcd51d21ed" value="Widevine"/>' => '<!-- Widevine -->
          <ContentProtection schemeIdUri="urn:uuid:EDEF8BA9-79D6-4ACE-A3C8-27DCD51D21ED">
            <cenc:pssh>' . $widevinePssh['pssh'] . '</cenc:pssh>
          </ContentProtection>',
    ];
    
 // Apply all static replacement in str_replace
    $processedManifest = str_replace(array_keys($staticReplacements), array_values($staticReplacements), $processedManifest);
    
    // Replace .dash & .m4s with hmac
    $processedManifest = strtr($processedManifest, [
        '.dash' => '.dash?' . $hmac,
        '.m4s' => '.m4s?' . $hmac
    ]);
}
if (in_array($id, ['244', '599'])) {
    $processedManifest = str_replace(
        'minBandwidth="226400" maxBandwidth="3187600" maxWidth="1920" maxHeight="1080"',
        'minBandwidth="226400" maxBandwidth="2452400" maxWidth="1280" maxHeight="720"',
        $processedManifest
    );
    $processedManifest = preg_replace('/<Representation id="video=3187600" bandwidth="3187600".*?<\/Representation>/s', '', $processedManifest);
}

// headers set for response
header('Content-Security-Policy: default-src \'self\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Access-Control-Allow-Origin: https://watch.tataplay.com");
header("Referrer: https://watch.tataplay.com/");
header("Referer-Policy: strict-origin-when-cross-origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header("Cache-Control: max-age=20, public");
header('Content-Disposition: attachment; filename="TP_' . urlencode($id) . '.mpd"');
echo $processedManifest;
function fetchMPDManifest(string $url, string $userAgent , string $hmac): ?string {
    
    $trueUrl = $url .'?'. $hmac;
    //echo $trueUrl;
    $h1 = [
        "User-Agent: $userAgent"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $trueUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h1);
    
    $content = curl_exec($ch);
    curl_close($ch);
    //echo $content;
    return $content !== false ? $content : null;
}

// extract default kid from $dashUrl
function extractKid($hexContent) {
    $psshMarker = "70737368";
    $psshOffset = strpos($hexContent, $psshMarker);
    
    if ($psshOffset !== false) {
        $headerSizeHex = substr($hexContent, $psshOffset - 8, 8);
        $headerSize = hexdec($headerSizeHex);
        $psshHex = substr($hexContent, $psshOffset - 8, $headerSize * 2);
        $kidHex = substr($psshHex, 68, 32);
        $newPsshHex = "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed000000121210" . $kidHex;
        $pssh = base64_encode(hex2bin($newPsshHex));
        $kid = substr($kidHex, 0, 8) . "-" . substr($kidHex, 8, 4) . "-" . substr($kidHex, 12, 4) . "-" . substr($kidHex, 16, 4) . "-" . substr($kidHex, 20);
        
        return [
        'pssh' => $pssh,
        'kid' => $kid
       ];
    }
    return null;
}

// extract pash from $dashUrl
function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent, ?int $catchupRequest, string $hmac): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $startNumber = $catchupRequest ? (int)($template['startNumber'] ?? 0) : (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0);
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], $startNumber], $template['media']);
                    $trueUrl = "$baseUrl/dash/$media?" . $hmac;
                    $h1 = [
                        "User-Agent: $userAgent"
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $trueUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $h1);
                    
                    $content = curl_exec($ch);
                    curl_close($ch);
                    
                    if ($content !== false) {
                        $hexContent = bin2hex($content);
                        return extractKid($hexContent);
                    }
                }
            }
        }
    }
    return null;
}

// json file path
function getChannelInfo(string $id): array {
    $json = @file_get_contents('secure/TP_Custom.json');
    $channels = $json !== false ? json_decode($json, true) : null;
    if ($channels === null) {
        exit;
    }
    foreach ($channels as $channel) {
        if ($channel['channel_id'] == $id) return $channel;
    }
    exit;
}
?>