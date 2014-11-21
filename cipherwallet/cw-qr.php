<?php

// AJAX request for cipherwallet QR code
// this action is typically invoked by your web page containing the form, thru the code 
//    in cipherwallet.js, to obtain the image with the QR code to display
// it will return the image itself, with an 'image/png' content type, so you can use 
//    the URL to this page as a 'src=...' attribute for the <img> tag

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/cqr-auth.lib.php");
require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");

// default timeout values, do not modify because they must stay in sync with the API
$DEFAULT_TTL = array(
    OP_SIGNUP => 120,
    OP_LOGIN => 60,
    OP_CHECKOUT => 300,
    OP_REGISTRATION => 30,
);

// create an unique session identifier
$cw_session = md5(mt_rand());
$qr_tag = "";
if (isset($_GET['tag'])) {
    if (!preg_match("/^[a-zA-Z0-9.:_-]+$/", $_GET['tag'])) {
        header("HTTP/1.0 400 Bad request");
        exit;
    }
    $qr_tag = $_GET['tag'];
}
$cw_session .= "-" . $qr_tag;
if (DEBUG) file_put_contents(DEBUG, "created cw session " . $cw_session . "\n", FILE_APPEND);

// get the user data request template; templates for each type of request are pre-formatted 
//    and stored in the constants file, in the $qr_requests variable
if (!array_key_exists($qr_tag, $qr_requests)) {
    header("HTTP/1.0 501 Not Implemented");
    exit;
}
$rq_def = $qr_requests[$qr_tag];

// set the time-to-live of the cipherwallet session in the temporary storage
$cw_session_ttl = (array_key_exists('qr_ttl', $rq_def)) ? 
    $rq_def['qr_ttl'] : 
    $DEFAULT_TTL[$rq_def['operation']]
;
if (is_null(cw_session($cw_session, 'qr_expires', 1 + $cw_session_ttl + time()))) {
    header("HTTP/1.0 500 Internal server error");
    exit;
}

// for registration QR code requests, we also save the current user ID in the short term storage
if ($rq_def['operation'] == OP_REGISTRATION) {
    $uid = get_user_id_for_current_session();  // you MUST implement this in hooks.php
    if ($uid)
        cw_session($cw_session, 'user_id', $uid);
    else {
        header("HTTP/1.0 401 Unauthorized");
        exit;
    }
}

$method = "POST";
$resource = sprintf("/%s/%s.png", $qr_tag, $cw_session);

$request_params = array();
if (array_key_exists('qr_ttl', $rq_def))
    $request_params['ttl'] = $rq_def['qr_ttl'];
if (array_key_exists('callback_url', $rq_def))
	$request_params['push_url'] = $rq_def['callback_url'];
if (!in_array($rq_def['operation'], array(OP_LOGIN, OP_REGISTRATION, ))) {
    if (array_key_exists('display', $rq_def))
      // some of the operations, like logins, dont need to display a message to the user; most others do
      $request_params['display'] = (gettype($rq_def['display']) == "object") ? 
          $rq_def['display']() : 
          $rq_def['display']
      ;
    // also, formfill-type operations (checkout, signup) may want to transmit their own parameters set
    // (not implemented)
}

// create CQR headers and the query string
if (count($request_params)) {
    $api_rq_headers = cqr_auth(CUSTOMER_ID, API_SECRET, $method, $resource, $request_params, H_METHOD);
    $api_rq_params = implode("&", array_map(
        function($k, $v) { return $k . "=" . $v; },  
        array_keys($request_params), array_values($request_params)
    ));
} else {
    $api_rq_headers = cqr_auth(CUSTOMER_ID, API_SECRET, $method, $resource, "", H_METHOD);
    $api_rq_params = "";
}
$api_rq_headers['Content-Type'] = "application/x-www-form-urlencoded";
$api_rq_headers['Content-Length'] = strlen($api_rq_params);

// put everything together in a curl object
$curl = curl_init();
curl_setopt($curl, CURLOPT_HEADER, FALSE);
curl_setopt($curl, CURLOPT_URL, API_URL . $resource);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($curl, CURLOPT_HTTPHEADER, array_map(
	function($k, $v) { return $k . ": " . $v; },  
	array_keys($api_rq_headers), array_values($api_rq_headers)
));
curl_setopt($curl, CURLOPT_POSTFIELDS, $api_rq_params);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

// call the API
$rx_data = curl_exec($curl);
$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close ($curl);

// set the cipherwallet session cookie and http status, and forward the response body
header("HTTP/1.0 " . $http_status);
setcookie("cwsession" . ($qr_tag ? ("-" . $qr_tag) : ""), $cw_session, time() + CW_SESSION_TIMEOUT, "/");

if (($http_status != 200) || (strlen($rx_data) == 0))
	// avoid rendering the default "image missing" icon, send back a 1x1 pixels transparent png
	$rx_data = file_get_contents(__DIR__ . "/1x1.png");
	// all other requests just relay the data back to the browser's ajax

header("Content-Type: image/png");
header("Content-Length: " . strlen($rx_data));

echo $rx_data;

?>
