<?php
error_reporting(0);

function logged_in() {
    return file_exists("secure/_sessionData");
}

function getCreds() {
    if (!logged_in()) {
        http_response_code(403);
       die("Not logged in.");
    }
    $data = file_get_contents("secure/_sessionData");
    $json = json_decode($data, true);
    return [
        'accessToken' => $json['data']['accessToken'],
        'sid' => $json['data']['userDetails']['sid'],
        'sname' => $json['data']['userDetails']['sName'],
        'profileId' => $json['data']['userProfile']['id']
    ];
}

// ******** Dont Remove the Credit ********
### Script Credit @Denver1769 🥰  ### //