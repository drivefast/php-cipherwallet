<?php

// This web service is called with a POST method by the cipherwallet API when an user 
//    scans the login QR code and transmits the identification / authorization data 
//    from the mobile device.
// We expect that one of the POST parameters is called "session", and is set to the 
//    session identifier created when the login QR was produced. All the other POST 
//    parameters are serialized in a JSON string and stored locally in the short term
//    database. The ajax polling mechanism embedded in the login web page will discover 
//    the record in the next pass, will perform the authorization, and will set the
//    session variables accordingly.

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/cqr-auth.lib.php");
require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");
require_once(__DIR__ . "/db-interface.lib.php");

$post_data = "";
switch ($_SERVER['REQUEST_METHOD']) {
case "POST":
    if (!array_key_exists('session', $_POST)) {
        header("HTTP/1.0 400 Bad Request");
        exit;
    }
    $post_data = $_POST;
    break;
case "HEAD":
    // called by the URL verification facility in the cipherwallet dashboard
    break;
default:
	header("HTTP/1.0 405 Method Not Allowed");
	exit;
}
if (!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
	header("HTTP/1.0 401 Unauthorized");
	exit;
}

// get the request custom headers, we need them to authenticate the cipherwallet server
$x_headers = array();
foreach($_SERVER as $hname => $hval) {
	if (substr($hname, 0, 7) != "HTTP_X_")
		continue;
	$x_hname = str_replace(" ", "-", ucwords(str_replace("_", " ", strtolower(substr($hname, 5)))));
	$x_headers[$x_hname] = $hval;
}

// authorize the sender of the message - they must use our own secret key
if (!cqr_verify($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $x_headers, $post_data, $_SERVER['HTTP_AUTHORIZATION'])) {
	header("HTTP/1.0 403 Forbidden");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    // save all variables received (hopefully user credentials) in the short term storage
    // we will authenticate the user only when these variables are pulled from the storage, 
    //    by the polling procedure executed in the browser
    $user_ident = $_POST;
    unset($user_ident['session']);
    if (strlen(set_user_ident($_POST['session'], json_encode($user_ident))) == 0)
        header("HTTP/1.0 500 Server Error");
}

header("Content-Length: 0");

?>
