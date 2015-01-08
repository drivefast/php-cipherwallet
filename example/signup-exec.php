<?php

// This web service is called with a POST method by the signup web page when the user 
//    presses the "create user" submit button. From data is POSTed from the signup page.
// If data signup page data was loaded from the mobile app (QR code scanning), we also 
//    register the user to use cipherwallet (QR code scanning) for the logins

header("Content-Type: application/json");

// we create an user record with the form data
try {
    $db = new PDO("sqlite:/path/to/php-cipherwallet/your.db");
} catch (PDOException $e) {
    header("HTTP/1.0 503 Service Unavilable");
    exit;
}

// make sure we have valid data
if (
    (!array_key_exists("firstname", $_POST) || (strlen($_POST['firstname']) < 1)  || (strlen($_POST['firstname']) > 64)) ||
    (!array_key_exists("email", $_POST) || (strlen($_POST['email']) < 5)  || (strlen($_POST['email']) > 64)) ||
    (!array_key_exists("password1", $_POST) || (strlen($_POST['password1']) < 5)  || (strlen($_POST['password1']) > 64))
) {
    header("HTTP/1.0 400 Bad request");
    exit;
}

// make sure the user doesnt already exist; email is the unique identifier
// (you probably wouldnt do this in real life, instead you would warn the user that their id is already in use...)
$db->exec($db->prepare("DELETE FROM users WHERE email = :email;"), array('email' => $_POST['email']));

// create user record
$sql = $db->prepare( 
    "INSERT INTO users(firstname, email, password, created_on) " .
    "VALUES(:firstname, :email, :password, :time);"
);
if ($sql->execute(array(
    'firstname' => $_POST['firstname'],
    'email' => $_POST['email'],
    'password' => crypt($_POST['password1'], md5(mt_rand())),
    'time' => time(),
))) {
    header("HTTP/1.1 " . $http_status);
    echo json_encode(array(
        'firstname' => $_POST['firstname'],
        'email' => $_POST['email'],
    ));
} else {
    header("HTTP/1.0 500 Internal server error");
    echo print_r($db->errorInfo(), TRUE);
}

?>