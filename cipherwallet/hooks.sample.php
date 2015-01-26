<?php

//////////////////  hook functions file for cipherwallet api  //////////////////
///////////               copy this file as 'hooks.php'              ///////////
///////////           and implement functionality as needed          ///////////

// you will need to implement this when your signup page includes registration
//    capabilities for the QR login service, or for the login service
function authorize_session_for_user($user_id) {
// called by the login AJAX poll function if QR login was successful, in order to 
//    return the user info needed by your web application
// this function should perform the same operations as your regular login (typically
//    set the session variables for the logged-in user), and is expected to return 
//    a dictionary with whatever you need to forward to the browser, in response to
//    the AJAX poll
// if something goes wrong, return an array that has a non-zero-length 'error' key
    $_SESSION['user_name'] = "Jane Doe";
    $_SESSION['user_id'] = "janedoe";
    return array(
        'user_name' => "Jane Doe",
        'user_id' => "janedoe",
    );
//  return array('error' => "Oh boy, something went wrong here...");
}

// you will need to implement this when you use QR login services registration for
//    your existing users
function get_user_id_for_current_session() {
// called by the procedure that obtains the QR code for registration
// since you should display the registration QR only on a page available to a 
//    logged-in user, it means that in normal circumstances an user ID would be 
//    available from your app, and this function would return a meaningful value
// following the example in the function above, we will just blindly assume that 
//    your user ID is stored in the superglobal $_SESSION variable; your mileage 
//    may vary
    return (isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : NULL;
}

// any other functions that dynamically create display or confirm messages for the 
//    signup and checkout messages should go here
// check the code on our demo site to see how to define functions in this module

?>