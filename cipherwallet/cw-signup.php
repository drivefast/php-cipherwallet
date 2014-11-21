<?php

// This API contains functionality that needs to be executed at the end of a traditional (or at
//    the end of your existing) signup process, after the data from the signup page has been 
//    successfully committed to the database. This code adds the credentials needed by future 
//    logins by QR code scanning, and confirms to the cipherwallet API that the user is indeed 
//    registered with us 

if (strpos($_SERVER['REQUEST_URI'], basename(__FILE__, ".php"))) {
    // assume this file is called with a direct URL referring its name, and not imported 
    // from another php module
    $stat = set_qr_login_data($_POST['user_id'], $_POST['tag']);
    header("HTTP/1.0 " . $stat);
}

function set_qr_login_data($user_unique_id, $qr_tag="") {
// practically this function does the job; it should be OK if you would want to include 
//    (aka "require") this file and call the function directly from your code

    require_once(__DIR__ . "/constants.php");
    require_once(__DIR__ . "/cqr-auth.lib.php");
    require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");
    require_once(__DIR__ . "/db-interface.lib.php");

    // if cipherwallet QR scan was used, and we're not too late, we will also confirm 
    //    the registration with the API, and we then append the cw-specific data to
    //    the user record
    $session_cookie_name = "cwsession" . ($qr_tag ? ("-" . $qr_tag) : "");
    if (DEBUG) file_put_contents(DEBUG, "checking cookie " . $session_cookie_name . "\n",  FILE_APPEND);
    if (!array_key_exists($session_cookie_name, $_COOKIE))
        return "410 Session timeout";
    $cw_session_id = $_COOKIE[$session_cookie_name];
    if (DEBUG) file_put_contents(DEBUG, "checking session " . $cw_session_id . "\n",  FILE_APPEND);
    
    // get the cw session data from the short term storage
    if ($reg_data = get_signup_registration_for_session($cw_session_id)) {
        if (DEBUG) file_put_contents(DEBUG, "reg session: " . print_r($reg_data, TRUE) . "\n",  FILE_APPEND);
        // call cipherwallet API to perform the final user registration for QR-based logins
        $method = "PUT";
        $resource = "/reg/" . $reg_data['registration'];
        $request_headers = cqr_auth(CUSTOMER_ID, API_SECRET, $method, $resource, "", H_METHOD);
        // some web servers really like to get these headers explicitely
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
        if (DEBUG) file_put_contents(DEBUG, "set_qr_login_data: got http status " . $http_status . "\n", FILE_APPEND);
        
        if ($http_status == 200)
            // registration with ciherwallet server was ok, we can save the QR login credentials in our database
            return set_user_data_for_qr_login($user_unique_id, $reg_data) ? "200 OK" : "500 Internal server error";
        else
            return $http_status;

    } else
        return "410 Session timeout"; // this is our best guess, that the signup registration QR code expired...
}

?>