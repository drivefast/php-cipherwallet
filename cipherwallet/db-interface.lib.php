<?php

//////////////////////////////////////////////////////////////////////////////////////
// this is an include file for the cipherwallet API implementation, that declares
//    a basic set of functions interacting with your users database
// we use PDO to connect to your database; DSN, username and password reside in the
//    constants.php module
//////////////////////////////////////////////////////////////////////////////////////

require_once(__DIR__ . "/constants.php");
global $db;
if (defined('DSN')) {
  try {
    $db = new PDO(DSN, DB_USERNAME, DB_PASSWORD);
  } catch (PDOException $e) {
    if (DEBUG) file_put_contents(DEBUG, "failed to connect to database: " . $e->getMessage() . "\n", FILE_APPEND);
  	header("HTTP/1.0 503 Service Unavilable");
  	exit;
  }
}

function verify_timestamp($ts) {
// used by the authorization verification function; checks to make sure the date 
//    indicated by the client is not too much drifted from the current date
	$now = time();
	return ($ts >= ($now - 3600)) && ($ts <= ($now + 3600));
}

function verify_nonce($user, $nonce) {
// used by the authorization verification function
// this function checks to make sure the nonce used by the client has not been used 
//    in the last few minutes / hours
// typically we defer this function to the temporary key-value store layer
    require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");
	return is_nonce_valid($user, $nonce, 3600);
}

function accepted_hash_method($h) {
// validate the signature hashing algorithm
	if ($h == "") return "sha1";
	return in_array($h, array("md5", "sha1", "sha256")) ? $h : "";
}

function encrypt_secret($user_secret) {
// use this function to encrypt user's secret key used in the signup / registration service
// this is an example using AES-256 encryption
// requires php Mcrypt library, see http://php.net/manual/en/book.mcrypt.php
    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
    return base64_encode($iv . mcrypt_encrypt(
        MCRYPT_RIJNDAEL_128, pack('H*', CW_SECRET_ENC_KEY), $user_secret, MCRYPT_MODE_CBC, $iv
    ));
}

function decrypt_secret($enc_user_secret) {
// use this function to decrypt user's secret key used in the login service
// this is an example using AES-256 encryption
// requires php Mcrypt library, see http://php.net/manual/en/book.mcrypt.php
    $ciphertext_combo = base64_decode($enc_user_secret);
    $iv = substr($ciphertext_combo, 0, mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC));
    $ciphertext = substr($ciphertext_combo, mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC));
    return mcrypt_decrypt(
        MCRYPT_RIJNDAEL_128, pack('H*', CW_SECRET_ENC_KEY), $ciphertext, MCRYPT_MODE_CBC, $iv
    );
}

function set_user_data_for_qr_login($user_id, $extra_data) {
// add cipherwallet-specific login credentials to the user record
// return a boolean value indicating the success of the operation
    // the cipherwallet usernames we generate are hex strings
    // the user ID is submitted by your app, so we assume it's safe already
    global $db;
    if (ctype_xdigit($extra_data['cw_user'])) {
        $db->exec("
DELETE FROM cw_logins WHERE user_id = '" . $user_id . "';
        ");
        $set_user_login_data = $db->prepare("
INSERT INTO cw_logins(user_id, cw_id, secret, reg_tag, created)
VALUES (:user_id, :cw_id, :secret, :reg_tag, :now);
        ");
        return $set_user_login_data->execute(array(
            ':user_id' => $user_id,
            ':cw_id' => $extra_data['cw_user'],
            ':secret' => encrypt_secret($extra_data['secret']),
            ':reg_tag' => $extra_data['registration'],
            ':now' => time(),
        ));
    } else
        return FALSE;
}

function get_key_and_id_for_qr_login($cw_user) {
// get an user's secret key from the database, in order to authenticate them
// the secret key has been associated with the user by user_data_for_qr_login() 
    global $db;
    if (ctype_xdigit($cw_user)) {
        $get_user_login_data = $db->prepare("
SELECT secret, user_id, hash_method FROM cw_logins WHERE cw_id = :cw_id;
        ");
        $get_user_login_data->execute(array(':cw_id' => $cw_user));
        if ($sec_key_encrypted = $get_user_login_data->fetch(PDO::FETCH_ASSOC))
            return array(
                decrypt_secret($sec_key_encrypted['secret']), 
                $sec_key_encrypted['user_id'],
                $sec_key_encrypted['hash_method']
            );
    }
    return "";
}

function get_user_for_qr_login($user_id) {
// get an user's cipherwallet id, based on the database normal user ID
    global $db;
    $get_cw_user_data = $db->prepare("
SELECT cw_id FROM cw_logins WHERE user_id = :user_id;
    ");
    $get_cw_user_data->execute(array(':user_id' => $user_id));
    if ($cw_user_rec = $get_cw_user_data->fetch(PDO::FETCH_ASSOC))
        return $cw_user_rec[cw_id];
    return "";
}

function remove_user_for_qr_login($user_id) {
// disables the qr login for an user, by removing the associated record from
// the cw_logins table
// invoke with the real user ID as a parameter
    global $db;
    return $db->exec("DELETE FROM cw_logins WHERE user_id = '" . $user_id . "';") == 1;    
}

?>