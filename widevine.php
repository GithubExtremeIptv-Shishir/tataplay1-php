<?php  
error_reporting(0);  
header("Content-Type: application/json");
date_default_timezone_set('Asia/Kolkata');
  
// Check if `cache/` directory exists
if (!is_dir('cache')) {
    mkdir('cache', 0777, true);
}

// Get `id` parameter from the query string
$id = $_GET['id'] ?? null;

if ($id === null) {
    echo json_encode(['error' => 'Missing "id" parameter'], JSON_PRETTY_PRINT);
    exit;
}

// Define cache file path
$cacheFile = "cache/jwt_$id";

// Check if the cache exists and is valid
if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if (time() - filemtime($cacheFile) < 3 * 3600) {
        // Serve cached data if cache is still valid
        echo json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
  
$userAgent = 'Shraddha/5.0';  
  
// Read and decode credentials  
$UserData = file_get_contents("secure/_sessionData");  
$json = json_decode($UserData, true);  
  
if (!$json) {  
    exit(json_encode(['error' => 'Invalid or missing credentials']));  
}  
  
function fetchContent($url, $want_response_header = false) {  
    $ch = curl_init();  
    curl_setopt_array($ch, [  
        CURLOPT_URL => $url,  
        CURLOPT_RETURNTRANSFER => true,  
        CURLOPT_HEADER => $want_response_header,  
        CURLOPT_NOBODY => $want_response_header,  
        CURLOPT_HTTPHEADER => [  
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.69.69.69 YGX/537.36',  
            'Origin: https://watch.tataplay.com',  
            'Referer: https://watch.tataplay.com/'  
        ]  
    ]);  
      
    $response = curl_exec($ch);  
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
    curl_close($ch);  
    return $responseCode === 200 ? $response : null;  
}  
  
function decrypt_source_url($encrypted_url) {  
    $SECRET_KEY = "aesEncryptionKey";  
    $cipher = "AES-128-ECB";  
  
    // Remove last 3 characters  
    $encrypted_url = substr($encrypted_url, 0, -3);  
  
    $decoded_data = base64_decode($encrypted_url);  
    if ($decoded_data === false) {  
        return "Error: Base64 decoding failed.";  
    }  
  
    $decrypted = openssl_decrypt($decoded_data, $cipher, $SECRET_KEY, OPENSSL_RAW_DATA);  
    if ($decrypted === false) {  
        return "Error: Decryption failed.";  
    }  
  
    // Remove padding  
    $padding_length = ord(substr($decrypted, -1));  
    if ($padding_length >= 1 && $padding_length <= 16) {  
        $decrypted = substr($decrypted, 0, -$padding_length);  
    } else {  
        $decrypted = rtrim($decrypted);  
    }  
    return $decrypted;  
}  
  
// Get protocol (http or https)  
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';  
  
// Get base path correctly  
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/');  
  
// Construct the full URL to hmac.php  
$hmacUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}" . ($base_path ? $base_path : '') . "/hmac.php?id=" . urlencode($id);  
  
// Fetch HMAC JSON data  
$hmacResponse = @file_get_contents($hmacUrl);  
  
if ($hmacResponse === false) {  
    echo json_encode(['error' => 'Failed to fetch HMAC data'], JSON_PRETTY_PRINT);  
    exit;  
}  
  
// Decode the HMAC JSON response  
$hmacData = json_decode($hmacResponse, true);  
$widevine = $hmacData['widevine'] ?? null;  
  
if (!$widevine) {  
    echo json_encode(['error' => 'Invalid widevine URL'], JSON_PRETTY_PRINT);  
    exit;  
}  
  
$TPAUTH = [  
    'accessToken' => $json['data']['accessToken'],  
    'refreshToken' => $json['data']['refreshToken'],  
    'sid' => $json['data']['userDetails']['sid'],  
    'sname' => $json['data']['userDetails']['sName'],  
    'profileId' => $json['data']['userProfile']['id']  
];  
  
$chnDetailsAPI = "https://tm.tapi.videoready.tv/content-detail/pub/api/v6/channels/$id?platform=WEB";  
  
$channelDetailsHeaders = [  
    "accept: */*",   
    "accept-language: en-US,en;q=0.9",  
    "authorization: bearer " . $TPAUTH['accessToken'],  
    "cache-control: no-cache",  
    "device_details: {\"pl\":\"web\",\"os\":\"WINDOWS\",\"lo\":\"en-us\",\"app\":\"1.44.7\",\"dn\":\"PC\",\"bv\":129,\"bn\":\"CHROME\",\"device_id\":\"\",\"device_type\":\"WEB\",\"device_platform\":\"PC\",\"device_category\":\"open\",\"manufacturer\":\"WINDOWS_CHROME_129\",\"model\":\"PC\",\"sname\":\"" . $TPAUTH['sname'] . "\"}",   
    "platform: web",  
    "pragma: no-cache",  
    "profileid: " . $TPAUTH['profileId'],  
    "Referer: https://watch.tataplay.com/",  
    "Origin: https://watch.tataplay.com",  
    "Referrer-Policy: strict-origin-when-cross-origin",  
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0"  
];  
  
$curl = curl_init();  
curl_setopt_array($curl, [  
    CURLOPT_URL => $chnDetailsAPI,  
    CURLOPT_RETURNTRANSFER => true,  
    CURLOPT_HTTPHEADER => $channelDetailsHeaders,  
]);  
curl_setopt($curl, CURLOPT_ENCODING, 'gzip, deflate, br');  
  
$response = curl_exec($curl);  
$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);  
curl_close($curl);  
  
if ($httpcode !== 200) {  
    http_response_code(500);  
    exit(json_encode(["error" => "Error occurred while fetching channel details."]));  
}  
  
$channelDetails = json_decode($response, true);  
  
if (!isset($channelDetails['data']['detail']['entitlements'])) {  
    exit(json_encode(["error" => "Missing entitlements data."]));  
}  
  
$entitlements = $channelDetails['data']['detail']['entitlements'];  
  
$specialId = "1000001274";  
$epids = [];  
  
if (in_array($specialId, $entitlements)) {  
    $epids[] = [  
        "epid" => "Subscription",  
        "bid" => $specialId  
    ];  
} elseif (!empty($entitlements)) {  
    $epids[] = [  
        "epid" => "Subscription",  
        "bid" => $entitlements[0]  
    ];  
} 

$jwtpay = json_encode([
        'action' => 'stream',
        'epids' => $epids,
        'samplingExpiry' => 'wLixk6fGx27amZptXg2I/w==#v2'
    ]);
    
    $sherlocation = 'https://tm.tapi.videoready.tv/auth-service/v3/sampling/token-service/token';
$sherheads = array(
    "accept: */*",
        "accept-language: en-US,en;q=0.9",
        "authorization: bearer " . $TPAUTH['accessToken'],
        "content-type: application/json",
        "device_details: {\"pl\":\"web\",\"os\":\"WINDOWS\",\"lo\":\"en-us\",\"app\":\"1.44.7\",\"dn\":\"PC\",\"bv\":129,\"bn\":\"CHROME\",\"device_id\":\"7683d93848b0f472c508e38b1827038a\",\"device_type\":\"WEB\",\"device_platform\":\"PC\",\"device_category\":\"open\",\"manufacturer\":\"WINDOWS_CHROME_129\",\"model\":\"PC\",\"sname\":\"" . $TPAUTH['sname'] . "\"}", 
        "locale: ENG",
        "platform: web",
        "pragma: no-cache",
        "profileid: " . $TPAUTH['profileId'],
        "x-device-platform: PC",
        "x-device-type: WEB",
        "x-subscriber-id: " . $TPAUTH['sid'],
        "x-subscriber-name: " . $TPAUTH['sname'],
        "Referer: https://watch.tataplay.com/",
        "Origin: https://watch.tataplay.com",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0"
);

$sherposts = $jwtpay;
$process = curl_init($sherlocation);
curl_setopt($process, CURLOPT_POST, 1);
curl_setopt($process, CURLOPT_POSTFIELDS, $sherposts);
curl_setopt($process, CURLOPT_HTTPHEADER, $sherheads);
curl_setopt($process, CURLOPT_HEADER, 0);
curl_setopt($process, CURLOPT_ENCODING, '');
curl_setopt($process, CURLOPT_TIMEOUT, 10);
curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
$response = curl_exec($process);
curl_close($process);

$wv_token = @json_decode($response, true);
$JwtToken = $wv_token['data']['token'];
$exp = $wv_token['data']['expiresIn'];
$exp_time = date('d/m/Y h:i A', $exp);
$ls_session = 'ls_session=' . $wv_token['data']['token'];

$data = [  
    "Type" => "TP Jwt API",  
    "widevine" => $widevine,  
    "jwt" => $JwtToken, 
    "expire" => $exp_time, 
    "NOTE" => 'Personal Use only!'  
];  
  
// Cache the output data
file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Output the data
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// ******** Dont Remove the Credit ********
### Script Credit @Denver1769 🥰  ### //
?>