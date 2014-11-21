<?php

// AJAX request to remove the record of an user registered for cipherwallet QR-based logins

if (strpos($_SERVER['REQUEST_URI'], basename(__FILE__, ".php"))) {
    // assume this file is called with a direct URL referring its name, and not imported  
    // from another php module
    $rq = json_decode(file_get_contents("php://input"), TRUE);
    if (is_null($rq) || !array_key_exists('user_id', $rq)) 
        $ret_status = "400 Bad Request";
    else
        $ret_status = remove_qr_login_data($rq['user_id']);
    header("HTTP/1.0 " . $ret_status);
    header("Content-Type: application/json");
    header("Content-Length: 0");
}

function remove_qr_login_data($user_id) {

    require_once(__DIR__ . "/constants.php");
    require_once(__DIR__ . "/cqr-auth.lib.php");
    require_once(__DIR__ . "/tmpstore_" . TMP_DATASTORE . ".lib.php");

    // get the cipherwallet id for the user
    if ($cw_user = get_user_for_qr_login($user_id)) {
        // prepare request
        $method = "DELETE";
        $resource = sprintf("/reg/%s", $cw_user);
        $api_rq_headers = cqr_auth(CUSTOMER_ID, API_SECRET, $method, $resource, "", H_METHOD);
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
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        // call the API
        curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close ($curl);
        if (DEBUG) file_put_contents(DEBUG, "got http status " . $http_status . "\n", FILE_APPEND);

        $ret_status = $http_status;
        if ($http_status == 200)
            // deregistration with ciherwallet was ok, we can remoce the QR login credentials in our database
            $ret_status = remove_user_data_for_qr_login($user_id) ? "200 OK" : "500 Internal server error";

    } else
        $ret_status = "404 Not Found";
}

?>
