<?php

require_once(__DIR__ . "/constants.php");

////////////////////////////////////////////////////////////////////////////////////////
//  this is an include file for cipherwallet temporary storage implementation, using
//  redis as a backend. using phpredis client https://github.com/nicolasff/phpredis
////////////////////////////////////////////////////////////////////////////////////////

define('K_NONCE', "CQR_NONCE_%s_%s"); 
define('K_CW_SESSION', "CW_SESSION_%s");  // cipherwallet session id
define('K_USER_DATA', "CW_USER_DATA_%s");  // + cw session id
define('K_SIGNUP_REG', "CW_SIGNUP_REG_%s");  // + cw session id
define('K_USER_IDENT', "CW_USERIDENT_%s");  // + cw session id

$redis = new Redis();
$redis->pconnect(
    $redis_connection['host'] ?: "127.0.0.1",
    $redis_connection['port'] ?: 6379,
    $redis_connection['timeout'] ?: 10.,
    "cipherwallet_tmpstore"
);

function is_nonce_valid($arg1, $arg2, $ttl) {
// adds a nonce with a limited time-to-live
// failure means that a nonce with the same key already exists
    global $redis;
    $k = sprintf(K_NONCE, $arg1, $arg2);
    if ($redis->exists($k))
        return FALSE;
	return $redis->set($k, 0, $ttl);
}

function cw_session($session_id, $var, $value=NULL) {
// session variables managed in the temp store
	$mcd = get_mcd_handle();
    $s = json_decode($redis->get(sprintf(K_CW_SESSION, $session_id)), TRUE);
    if (is_null($value)) {
        // reading the variable
        if (is_null($s))
            return NULL;
        else
            return array_key_exists($var, $s) ? $s[$var] : NULL;
    } else {
        // setting the variable
        if (is_null($s)) $s = array();
        $s[$var] = $value;
        return $redis->set(sprintf(K_CW_SESSION, $session_id), json_encode($s), CW_SESSION_TIMEOUT) ? $value : NULL;
    }
}

function set_user_data($session_id, $user_data) {
// this function temporarily stores data transmitted by user, when POSTed 
//    by the user device; the data is then picked up by the page ajax 
//    polling mechanism
    global $redis;
	return $redis->set(sprintf(K_USER_DATA, $session_id), json_encode($user_data), 30) ? $session_id : "";
}

function get_user_data($session_id) {
// the complement of the above: gets called by the web page polling mechanism 
//    to retrieve data transmitted (POSTed) by the user's device, after scanning
//    a QR code
    global $redis;
	return $redis->get(sprintf(K_USER_DATA, $session_id));
}


function set_signup_registration_for_session($session_id, $registration, $complete) {
// this function is called when the user's mobile app uploaded signup data, 
//    in addition to the set_user_data() above
// it returns a new login credentials record

	// the secret is a 64 character random string
    global $redis;
	$creds = new_credentials($registration);
	return ($redis->set(sprintf(K_SIGNUP_REG, $session_id), json_encode($creds), $complete)) ? 
	    array_diff_key($creds, array('registration' => "")) : 
	    array()
	;
}

function get_signup_registration_for_session($session_id) {
// when the user completes the signup process (by submitting the data on the
//    signup page), we need to call this function to retrieve the registration
//    confirmation tag that we saved with the function above
    global $redis;
	if ($creds_json = $redis->get(sprintf(K_SIGNUP_REG, $session_id)))
	    return json_decode($creds_json, TRUE);
	else
	    return FALSE;
}

function set_user_ident($session_id, $user_ident) {
// on QR login, the push web service invoked by the cipherwallet API calls this function 
//    to temporarily store user identification data until it gets polled by the ajax 
//    functions on the login page
    global $redis;
	return $redis->set(sprintf(K_USER_IDENT, $session_id), $user_ident, 30) ? $session_id : "";
}

function get_user_ident($session_id) {
// on QR login, this function gets called by the login page poll mechanism 
//    to retrieve user identification data posted with the function above
    global $redis;
	return $redis->get(sprintf(K_USER_IDENT, $session_id));
}

?>