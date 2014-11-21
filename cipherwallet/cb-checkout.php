<?php

// This web service is called with a POST method by the cipherwallet mobile app 
//    when an user scans the QR code on the checkout page and transmits the requested
//    credit card data.
// We expect that the request content type is json, and looks something like this:
//    {
//      "email": {"email": "john.doe@gmail.com"},
//      "phone": {"num": "2345551212", "cansms": true},
//      "creditcard": {"num": "4397078011112222", "name": "John Doe", ...},
//    }
// We will save this data in a temporary storage resource. The AJAX polling mechanism 
//    embedded in the checkout web page will discover the record in the next pass, and 
//    will dispatch the data to the appropriate form fields, similar ot an auto-fill 
//    function.

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/cqr-auth.lib.php");
require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");

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
$session = $rq['session'];

// we save the data we received from the user's mobile app in the short term storage
// this data will be dispatched on the next poll received from the browser, and 
//     the javascript on the page will distribute it to the form fields
unset($rq['callback_meta']);
if (DEBUG) file_put_contents(DEBUG, "data for session " . $session . ":\n" . print_r($rq, TRUE) . "\n", FILE_APPEND);
if (!set_user_data($session, $rq)) {
	header("HTTP/1.0 500 Server Error");
	exit;
}

// we may want to return something to the mobile app here
$response = array();
$rq_template = $qr_requests[substr($session, 1 + strpos($session, '-'))];
if (gettype($rq_template['confirm']) == "object")
    // to get the string to be displayed to the user we actually have to call 
    //    a function that returns a string
    $response['confirm'] = $rq_template['confirm']();
else
    $response['confirm'] = $rq_template['confirm'];
header("Content-Type: application/json");
echo json_encode($response);

?>