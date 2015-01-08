<?php

require_once(__DIR__ . "/constants.php");

////////////////////////////////////////////////////////////////////////////////////////
//  this is an include file for cipherwallet temporary storage implementation, using
//  trivial files for storage. this is NOT AT ALL a good solution - it's slow, and just
//  doesnt work if you have a cluster of http servers.
//
// Some files become obsolete after a few seconds or minutes. To indicate the validity  
// of the files, as soon as we create them, we change the last-modified date to a 
// future date when the data is considered stale or invalid. You HAVE TO run a cron job 
// that deletes the obsoleted files, otherwise your directory will keep filling up. Use 
// the following command in a crontab that executes every hour:
//
//    find /path/to/sessions/directory -type f -mmin +60 -delete
//
////////////////////////////////////////////////////////////////////////////////////////

define('K_NONCE', "CQR_NONCE_%s_%s"); 
define('K_CW_SESSION', "CW_SESSION_%s");  // cipherwallet session id
define('K_USER_DATA', "CW_USER_DATA_%s");  // + cw session id
define('K_SIGNUP_REG', "CW_SIGNUP_REG_%s");  // + cw session id
define('K_USER_IDENT', "CW_USER_IDENT_%s");  // + cw session id


function _file_write_with_expiration(fname, content, ttl) {
    if (file_put_contents(TMPSTORE_DIR . fname, content))
        return touch(TMPSTORE_DIR . fname, ttl + time());
    else
        return False;
}

function _file_read_if_not_expired(fname) {
    // make sure the data is still valid (not expired)
    s = stat(TMPSTORE_DIR . fname);
    if ((s === FALSE) || (s['mtime'] < time()))
        return NULL;
    else
        return file_get_contents(TMPSTORE_DIR . fname);    
}


function is_nonce_valid($arg1, $arg2, $ttl) {
// adds a nonce with a limited time-to-live
// failure means that the nonce already exists
    if (!file_exists(TMPSTORE_DIR . sprintf(K_NONCE, $arg1, $arg2)))
    	return _file_write_with_expiration(sprintf(K_NONCE, $arg1, $arg2), ".", $ttl);
    else
        return FALSE;
}

function cw_session($session_id, $var, $value=NULL) {
// session variables managed in the temp store
    $s = json_decode(_file_read_if_not_expired(sprintf(K_CW_SESSION, $session_id)), TRUE);
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
        return _file_write_with_expiration(
            sprintf(K_CW_SESSION, $session_id), 
            json_encode($s), CW_SESSION_TIMEOUT
        ) ? $value : NULL;
    }
}

function set_user_data($session_id, $user_data) {
// this function temporarily stores data transmitted by user, when POSTed 
//    by the user device; the data is then picked up by the page ajax 
//    polling mechanism
	return _file_write_with_expiration(
	    sprintf(K_USER_DATA, $session_id), json_encode($user_data), 30
	) ? $session_id : "";
}

function get_user_data($session_id) {
// the complement of the above: gets called by the web page polling mechanism 
//    to retrieve data transmitted (POSTed) by the user's device, after scanning
//    a QR code
	return _file_read_if_not_expired(sprintf(K_USER_DATA, $session_id));
}

function set_signup_registration_for_session($session_id, $registration, $complete) {
// this function is called when the user's mobile app uploaded signup data, 
//    in addition to the set_user_data() above
// it returns a new login credentials record

	// the secret is a 64 character random string
	$creds = new_credentials($registration);
	return (_file_write_with_expiration(
	    sprintf(K_SIGNUP_REG, $session_id), json_encode($creds), $complete)
	) ? array_diff_key($creds, array('registration' => "")) : array();
}

function get_signup_registration_for_session($session_id) {
// when the user completes the signup process (by submitting the data on the
//    signup page), we need to call this function to retrieve the registration
//    confirmation tag that we saved with the function above
	if ($creds_json = _file_read_if_not_expired(sprintf(K_SIGNUP_REG, $session_id)))
	    return json_decode($creds_json, TRUE);
	else
	    return FALSE;
}

function set_user_ident($session_id, $user_ident) {
// on QR login, the push web service invoked by the cipherwallet API calls this function 
//    to temporarily store user identification data until it gets polled by the ajax 
//    functions on the login page
	return _file_write_with_expiration(
	    sprintf(K_USER_IDENT, $session_id), $user_ident, 30
	) ? $session_id : "";
}

function get_user_ident($session_id) {
// on QR login push, this function gets called by the login page poll mechanism 
//    to retrieve user identification data posted with the function above
	return _file_read_if_not_expired(sprintf(K_USER_IDENT, $session_id));
}

?>