<?php
header("Content-Type: application/json");

// ******** Dont Remove the Credit ********
### Script Credit @Denver1769 🥰  ### //

// Get `id` parameter from the query string
$id = $_GET['id'] ?? null;

if ($id === null) {
    echo json_encode(['error' => 'Missing "id" parameter'], JSON_PRETTY_PRINT);
    exit;
}

// Determine protocol (HTTP or HTTPS)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";

// Get base path (if needed)
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path === '/' || $base_path === '\\') {
    $base_path = ''; // Avoid double slashes in URL
}

// Construct the full URL to widevine.php
$widevineUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}{$base_path}/widevine.php?id=" . urlencode($id);

// Fetch widevineUrl JSON data
$wvResponse = @file_get_contents($widevineUrl);

if ($wvResponse === false) {
    echo json_encode(['error' => 'Failed to fetch widevine data'], JSON_PRETTY_PRINT);
    exit;
}

// Decode the widevine JSON response
$wvData = json_decode($wvResponse, true);

if (!isset($wvData['widevine'], $wvData['jwt'])) {
    echo json_encode(['error' => 'Invalid response from widevine.php'], JSON_PRETTY_PRINT);
    exit;
}

$licenceUrlRaw = $wvData['widevine'];
$jwtRaw = $wvData['jwt'];

// Construct final license URL
$licenceURL = $licenceUrlRaw . '&ls_session=' . urlencode($jwtRaw);

// Redirect to the constructed URL
header("Location: $licenceURL", true, 307);
exit();
?>