<?php
// ******** Dont Remove the Credit ********
### Script Credit @Denver1769 🥰  ### //

// Define the path to the session data file
$sessionFile = 'secure/_sessionData';

// Check if the file exists before deleting it
if (file_exists($sessionFile)) {
    unlink($sessionFile);
}

// Redirect to for re-login
http_response_code(307);
header("Location: index.php");
exit;
?>