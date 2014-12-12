<?php

// This web service is called with a POST method by the cipherwallet mobile app 
//    when an user scans the QR code on the signup page and transmits the requested
//    personal data.
// We expect that the request conent type is json and looks something like this:
// {
//    "session": "...",
//    "user_data": {
//      "email": {"email": "john.doe@gmail.com"},
//      "phone": {"num": "2345551212", "cansms": true},
//      "user": {"last": "Doe", "first": "John"},
//    },
//    "reg_meta": {
//       "tag": "...",
//       "complete_timer": ...
//    }
// }
// We will save this data in a temporary storage resource. The ajax polling mechanism 
//    embedded in the signup web page will discover the record in the next pass, and 
//    will dispatch the data to the appropriate form fields.

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

// we get some registration metadata coming from the cipherwallet API; we're interested in 
//    the 'tag' variable, since that is the way that the cipherwallet API identifies our 
//    user. later on, when the user completes the signup process, we will need to use the 
//    metadata tag to tell the API that we want to make the registration permanent.
$response = array();
if (array_key_exists('reg_meta', $rq) && array_key_exists('tag', $rq['reg_meta'])) {
    $response['credentials'] = set_signup_registration_for_session($session, 
        $rq['reg_meta']['tag'], $rq['reg_meta']['complete_timer']
    );
    if (DEBUG) file_put_contents(DEBUG, "reg data: " . print_r($response, TRUE) . "\n", FILE_APPEND);    
}

// we place the data we received from the user's mobile app (but not the metadata) in the 
//    temporary storage. this data will be dispatched on the next poll received from the 
//    browser, and the javascript on the page will distribute it to the form fields
if (DEBUG) file_put_contents(DEBUG, "data for session " . $session . ":\n" . print_r($rq['user_data'], TRUE) . "\n", FILE_APPEND);
if (!set_user_data($session, $rq['user_data'])) {
	header("HTTP/1.0 500 Server Error");
	exit;
}

// return a response that confirms to the mobile app that the data was received
$rq_template = $qr_requests[substr($session, 1 + strpos($session, '-'))];
if (gettype($rq_template['confirm']) == "object")
    // the string to be displayed to the user is actually a function that returns a dynamic result
    $response['confirm'] = $rq_template['confirm']();
else
    $response['confirm'] = $rq_template['confirm'];
header("Content-Type: application/json");
echo json_encode($response);

?>
