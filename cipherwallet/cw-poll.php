<?php

// AJAX polling for the status of a page awaiting QR code scanning
// this action is typically invoked periodically from the web page, thru the code in cipherwallet.js, 
//     in order to detect when / if the user scanned the QR code and transmitted the requested info

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/cqr-auth.lib.php");
require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");
require_once(__DIR__ . "/db-interface.lib.php");

// we look for the presence of requested info associated with the data in the storage place
$session_cookie_name = "cwsession";
if (isset($_GET['tag']))
    $session_cookie_name .= "-" . $_GET['tag'];
if (!array_key_exists($session_cookie_name, $_COOKIE)) {
    if (DEBUG) file_put_contents(DEBUG, "no cookie " . $session_cookie_name . "\n", FILE_APPEND);
    header("HTTP/1.0 410 Offer Expired");
    exit;
}
$cw_session_id = $_COOKIE[$session_cookie_name];
$session_expires_on = cw_session($cw_session_id, 'qr_expires');

$browser_location = "";
if (defined('BROWSER_COORDINATES'))
    $browser_location = BROWSER_COORDINATES;
else if (isset($_GET['browser_location']))
    $browser_location = $_GET['browser_location'];

if ((is_null($session_expires_on)) || ($session_expires_on < time())) {
    // QR code validity has expired
    if (DEBUG) file_put_contents(DEBUG, "session id " . $cw_session_id . " expired\n", FILE_APPEND);
    header("HTTP/1.0 410 Offer Expired");
    exit;
}
if (DEBUG) file_put_contents(DEBUG, "checking data for session " . $cw_session_id . "\n", FILE_APPEND);

if ($user_data_json = get_user_data($cw_session_id)) {
    // QR scan data is present, submit it as AJAX response
    header("Content-Type: application/json");
    echo $user_data_json;
} else if ($user_ident_json = get_user_ident($cw_session_id)) {
    // this is user data for the login service
    $ret = array('error' => "");
    if ($user_ident = json_decode($user_ident_json, TRUE)) {
        // if the user signature was hashed properely, we can declare the user logged in
        if ($user_id = cqr_authorize($user_ident)) {
            if (defined('MOBILE_DEVICE_DISTANCE_MAX')) {
                $no_location = MOBILE_DEVICE_DISTANCE_REQUIRED ? 'error' : 'warning';
                // we need to enforce the geodistance between browser and mobile device
                if (strlen($browser_location) == 0)
                    $ret[$no_location] = "Browser did not provide required location information.";
                elseif (strlen($user_ident['location']) == 0)
                    $ret[$no_location] = "Mobile device did not provide required location information.";
                else { 
                    $distance = geodistance($browser_location, $user_ident['location']);
                    if ($distance > MOBILE_DEVICE_DISTANCE_MAX) 
                        $ret['error'] = "Browser and mobile device are too far apart geographically.";
                    elseif ($distance < 0.)
                        $ret[$no_location] = "Geographic distance between browser and mobile device could not be calculated.";
                }
            }
            if (!$ret['error'])
                $ret = array_merge($ret, authorize_session_for_user($user_id));  // you MUST implement this in cipherwllet-hooks.php
        } else 
            $ret['error'] = "User not registered";
        header("HTTP/1.0 " . ($ret['error'] ? "401 Unauthorized" : "200 OK"));
        header("Content-Type: application/json");
        echo json_encode($ret);
    } else
        header("HTTP/1.0 500 Server Error");
} else {
    // QR scan info not received yet, hang around for a while to avoid poll storms
    header("HTTP/1.0 202 Waiting for User");
    sleep(POLL_DELAY);
}

?>
