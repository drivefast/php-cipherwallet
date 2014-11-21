<?php
//////////////////  configuration file for cipherwallet SDK  ///////////////////
////////                copy this file as constants.php                /////////
////////                and adjust the values as needed                /////////

// import the hooks functions
require_once(__DIR__ . "/hooks.php");

// your customer ID and API secret key, as set on the dashboard page
define('CUSTOMER_ID', "YOUR CUSTOMER ID HERE");
define('API_SECRET', "YOUR API SECRET HERE");

// API location
define('API_URL', "http://api.cqr.io");
// preferred hashing method to use on message encryption: md5, sha1, sha256 or sha512
define('H_METHOD', "sha256");
// how long (in seconds) do we delay a "still waiting for user data" poll response
define('POLL_DELAY', 2);
// service id, always "cipherwallet"
define('SERVICE_ID', "cipherwallet");

// keep this to FALSE if not debugging; otherwise set to a log filename
define('DEBUG', FALSE);
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
}

// depending on your temporary datastore of choice, uncomment one of the following sections
// and adjust the settings accordingly
//   memcached
//define('TMP_DATASTORE', "memcached"); $mcdservers = array(array('localhost', 11211), array('localhost', 11212),);
//   redis
//define('TMP_DATASTORE', "redis"); $redis_connection = array('host' => "localhost", 'port' => 6379, 'timeout' => 10.,);
//   APC (only use if you run your web app on one single http server)
//define('TMP_DATASTORE', "apc");
//   file storage (not really recommended...)
//define('TMP_DATASTORE', "session_files"); define('TMPSTORE_DIR', "/path/to/storage/directory/");
// how long are we supposed to retain the information about a QR scanning session
// the value should be slightly larger than the maximum QR time-to-live that you use
define('CW_SESSION_TIMEOUT', 610);

// for logins via QR code scanning, you need to provide PDO access to a SQL database where users 
//     information is stored (we're assuming here you are using a SQL database). cipherwallet only 
//     needs read/write access to a table it creates (cw_logins), so feel free to restrict as needed. 
// for more details about PDO, see http://us2.php.net/manual/en/pdo.construct.php
// to set the DSN, uncomment and set one of the lines below
//define('DSN', "pgsql:dbname=usersdb;host=127.0.0.1");   // PostgreSQL
//define('DSN', "mysql:dbname=usersdb;host=127.0.0.1");   // MySQL
//define('DSN', "sqlite:/path/to/your.db");               // SQLite
//define('DSN', "oci:dbname=127.0.0.1/usersdb");          // Oracle; this DSN is worth $1750 by itself ;)
//define('DSN', "mssql:dbname=usersdb;host=127.0.0.1");   // MSSQL (just in case)
define('DB_USERNAME', "THE DATABASE USERNAME");
define('DB_PASSWORD', "THE DATABASE SUPERSECRET PASSWORD");
// in your database, YOU MUST create a table called 'cw_logins', which will have a 1-1 relationship with
//    your users table; something like this (but check the correct syntax for on your SQL server type):
/* 
CREATE TABLE cw_logins (
    user_id VARCHAR(...) PRIMARY KEY,  -- or whatever type your unique user ID is
    cw_id VARCHAR(20),
    secret VARCHAR(128),
    reg_tag CHAR(),                    -- it's an UUID
    hash_method VARCHAR(8),            -- can be md5, sha1, sha256
    created INTEGER
);
*/
// 'user_id' is the unique identifier of an user in your main users table, and should be declared as
//    a primary key and foreign index in your users table; if your SQL server supports it, cascade 
//    the changes and deletes
// 'cw_id' is the cipherwallet ID assigned to the user
// 'secret' is the secret encryption key assigned to the user; YOU MUST ENCRYPT THIS!
// 'reg_tag' is an identifier that the cipherwallet API maintains; use this identifier when you need 
//    to remove an user's registration. it is an UUID, so you may use a more appropriate data type if 
//    your database supports one
// 'hash_method' is the hash type the user will hash their user credentials with, on QR scan logins; 
//    can be md5, sha1, sha256
// 'created' is the date when the record was created, epoch format (feel free to change this field type 
//    to a date/time field, if you find it more convenient)
// you should also create an index on cw_id, it will help during your queries

// your user's secret keys must be stored in an encrypted form in the cw_logins table
// we use an AES-256 encryption algorithm for that, with the encryption key below
// the encryption itself comes in play in db-interface.lib.php
// the AES-256 encryption key must be 32-bytes long; example:
//define('CW_SECRET_ENC_KEY', "000102030405060708090A0B0C0D0E0F101112131415161718191A1B1C1D1E1F");
define('CW_SECRET_ENC_KEY', "32-BYTE RANDOM NUMBER HERE");
// hint: to easily generate a 32-byte encryption key like needed here, just generate 2 random UUIDs, 
//    concatenate them, and remove the formatting dashes

define('OP_SIGNUP', "signup");
define('OP_LOGIN', "login");
define('OP_CHECKOUT', "checkout");
define('OP_REGISTRATION', "reg");

// provide a service descriptor entry in this map for every cipherwallet QR code you are using
// on each entry, you provide:
//    - 'operation': the operation type, one of the OP_* constants above 
//    - 'qr_ttl': a time-to-live for the QR code, in seconds
//    - 'callback_url': the URL used by the mobile app to transfer the data back to your web app
//    - 'display': a message that gets displayed at the top of the screen, in the mobile app, when  
//          the user is asked to select the data they want to send; you may provide a string, or 
//          a function that returns a string (for example, you can customize the message for a 
//          checkout service, such that it mentions the amount to be charged to the credit card)
//    - 'confirmation': a message to be displayed as a popup-box in the mobile app, that informs if 
//          the last QR code scanning and data transfer operations was successful or not; you may  
//          provide a string, or a function that returns a string
// the service descriptor parameters specified here will override the ones pre-programmed with the 
//    the dashboard page
// the 'operation' must be specified; 'qr_ttl' has default and max values for each type of service; 
//    'display' is only effective for the signup and checkout services; and 'confirm' is only 
//    effective for signup, checkout and registration services
// here is an example that contains 4 services: a signup, a registration, a login and a checkout
// commented out values indicate default values
$qr_requests = array(
    'main_page_signup' => array(
        'operation' => OP_SIGNUP, 
//      'qr_ttl' => 120, 
//      'callback_url' => "https://thiswebsite.com/php-cipherwallet/cb-signup.php",
        'display' => "Select the data items below to sign up for a new customer account at\\example.com",
        'confirm' => "Thanks! Your signup data has been submitted.",
    ),
    'qrcode_login_registration' => array(
        'operation' => OP_REGISTRATION, 
//      'qr_ttl' => 30, 
//      'callback_url' => "https://thiswebsite.com/php-cipherwallet/cb-registration.php",
        'confirm' => function() {
            return array(
                'title' => "cipherwallet registration",
                'message' => "Thank you. You may now use cipherwallet to log in to cipherwallet.com.",
            );
        },
    ),
    'qrcode_login' => array(
        'operation' => OP_LOGIN, 
//      'qr_ttl' => 60, 
//      'callback_url' => "https://thiswebsite.com/php-cipherwallet/cb-login.php",
    ),
    'checkout_dash' => array(
        'operation' => OP_CHECKOUT, 
//      'qr_ttl' => 120, 
//      'callback_url' => "https://thiswebsite.com/php-cipherwallet/cb-checkout.php",
//      'display' => get_message_for_cart_value,   // implement this function in hooks.php
        'confirm' => "Thank you for your purchase."
    ),
);

function new_credentials($reg) {
// create a new set of credentials for an user
	// the secret is a 64 character random string
    define('ALPHABET', "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_@");
	$secret = "";
	while (strlen($secret) < 64)
		$secret .= substr(ALPHABET, mt_rand(0, strlen(ALPHABET) - 1), 1);
	return array(
	    'registration' => $reg,
        'cw_user' => uniqid(),
	    'secret' => $secret,
	    'hash_method' => H_METHOD,
	);
}

?>
