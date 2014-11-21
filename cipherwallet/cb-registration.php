<?php

// This web service is called with a POST method by the cipherwallet mobile app, 
//    when an user scans the QR code on the registration page (which is not the 
//    same as the signup page!)
// The registration QR code will always be displayed on a web page that is only 
//    accessible to an user that already logged in; thus, the user ID is already 
//    known by the website app, and we can ask for it with a hook function

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/cqr-auth.lib.php");
require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");
require_once(__DIR__ . "/db-interface.lib.php");

switch ($_SERVER['REQUEST_METHOD']) {
case "POST":
    break;
case "HEAD":
    // called by the URL verification facility in the cipherwallet dashboard
    exit;
default:
	header("HTTP/1.0 405 Method Not Allowed");
	exit;
}
// make sure we can decode the request content as json
$rq = json_decode(file_get_contents("php://input"), TRUE);
if (is_null($rq)) {
	header("HTTP/1.0 400 Bad Request");
	exit;
}
if (!array_key_exists('session', $rq)) {
	header("HTTP/1.0 400 Bad Request");
	exit;
}
$cw_session = $rq['session'];

// silence up the poll operation
set_user_data($cw_session, array('registration' => "failed"));

// the reg_meta variable contains a tag; this is the way we can identify the user 
//    when we are talking to the cipherwallet API,and how we can later on remove 
//    the user from the cipherwallet's database
if (array_key_exists('reg_meta', $rq) && array_key_exists('tag', $rq['reg_meta']))
    $reg_tag = $rq['reg_meta']['tag'];
else {
    header("HTTP/1.0 410 Offer Expired");
    exit;
}

// obtain the logged-in user's unique ID
$user_unique_id = cw_session($cw_session, 'user_id');
if (is_null($user_unique_id)) {
    header("HTTP/1.0 410 Offer Expired");
    exit;
}

// call cipherwallet API to perform the user registration
$method = "PUT";
$resource = "/reg/" . $reg_tag;
$request_headers = cqr_auth(CUSTOMER_ID, API_SECRET, $method, $resource, "", H_METHOD);
$request_headers['Content-Type'] = "application/x-www-form-urlencoded";
$request_headers['Content-Length'] = "0";
if (DEBUG) file_put_contents(DEBUG, 
    "calling " . $resource . " with headers:\n" . print_r($request_headers, TRUE) . "\n", 
FILE_APPEND);

// curl the PUT request
$curl = curl_init();
curl_setopt($curl, CURLOPT_HEADER, FALSE);
curl_setopt($curl, CURLOPT_URL, API_URL . $resource);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($curl, CURLOPT_HTTPHEADER, array_map(
    function($k, $v) { return $k . ": " . $v; },
    array_keys($request_headers), array_values($request_headers)
));
curl_setopt($curl, CURLOPT_POSTFIELDS, "");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

curl_exec($curl);
$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close ($curl);
if (DEBUG) file_put_contents(DEBUG, "got http status " . $http_status . "\n", FILE_APPEND);

if ($http_status != 200) {
    header("HTTP/1.0 " . $http_status);
    exit;
}

// registration with ciherwallet API was ok, we can save the QR login credentials in our database
// we also generate a JSON response containing the cipherwallet credentials that we send back to 
//    the mobile app
$creds = new_credentials($reg_tag);
if (set_user_data_for_qr_login($user_unique_id, $creds)) {
    set_user_data($cw_session, array('registration' => "success"));
    header("HTTP/1.0 200 OK");
    header("Content-Type: application/json");
    unset($creds['registration']); // dont send the registration tag to the mobile device
    echo json_encode($creds);
} else
    header("HTTP/1.0 500 Internal Server Error");

?>