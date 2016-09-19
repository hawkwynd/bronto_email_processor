<?php

/**
 * Collect $_POST from form, and SoapApi data to bronto form.
 * Subscribe to Cafe Contact mailing list if checkbox is selected.
 * Javascript will perform form validation prior to it sending data
 * to this script. email is only required field.
 *
 */

// Knock-knock! Who's there? Not $_POST? Leave, and don't come back!
if ( $_SERVER[ 'REQUEST_METHOD' ] != "POST" ) {
    header("location: /");
}

// Lets keep our little application safer, shall we? -scott
$_POST          = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);


$client = new SoapClient('http://api.bronto.com/v4?wsdl', array('trace' => 1,
    'features' => SOAP_SINGLE_ELEMENT_ARRAYS));


// list ID's we will sign up for based on selections made in POST
$lovelessWebsite_list_id = "0bc903ec000000000000000000000005fed0";
$barn_contact_list_id    = "0bc903ec000000000000000000000006400d";


// Build array of data based on info in $_POST
// Make sure email is valid or die with honor..

if( isset($_POST['email']) && $_POST['email'] !=''){
    if(isValidEmail($_POST['email'])) {
        $contact = array('email' => $_POST['email'],
            'listIds' => $lovelessWebsite_list_id
        );
    }else{
        die('Error: bad email address provided!');
    }
}else{
    die('[{"isError":true,"errorCode":320,"errorString":"Email address is required!"}]');
}

// OK We're still alive, so let's get on with the data processing, shall we?

// subscribe to Loveless mailing list, if the form checkbox is 'true', so now we have 2 listId's subscribed.
if( isset($_POST['addToBarnList']) && $_POST['addToBarnList'] == true){
    $contact['listIds'] =  array($barn_contact_list_id, $lovelessWebsite_list_id);
}

// first name
if( isset($_POST['firstname']) && $_POST['firstname'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c29b', 'content' => $_POST['firstname']);
}

// last name
if( isset($_POST['lastname']) && $_POST['lastname'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c29c', 'content' => $_POST['lastname']);
}

// address1
if( isset($_POST['address1']) && $_POST['address1'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c29e', 'content' => $_POST['address1']);
}
// city
if( isset($_POST['city']) && $_POST['city'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c2a0', 'content' => $_POST['city']);
}

// state
if( isset($_POST['state']) && $_POST['state'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c2a1', 'content' => $_POST['state']);
}

// zipcode
if( isset($_POST['zipcode']) && $_POST['zipcode'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c2a2', 'content' => $_POST['zipcode']);
}

// country
if( isset($_POST['country']) && $_POST['country'] != ""){
    $fields[] = array('fieldId' =>'0bc903e9000000000000000000000002c2a3', 'content' => $_POST['country']);
}


// Pack our fields array payload data
$contact['fields'] = $fields;

// Fire the weapon, Mr. Sulu!
try {

    $token          = "CE98DB86-9C28-40FB-AE87-70B55EA16EEE";
    $sessionId      = $client->login(array('apiToken' => $token))->return;
    $session_header = new SoapHeader("http://api.bronto.com/v4",
                                     'sessionHeader',
                                      array('sessionId' => $sessionId));

    $client->__setSoapHeaders(array($session_header));

    // write results to Bronto to add or update existing records. -
    $write_result = $client->addOrUpdateContacts(array($contact))->return;

    // Note we are accessing the results and errors arrays.
    // Both of these arrays are returned as part of
    // writeResult object.

    if ($write_result->errors) {
        echo json_encode($write_result->results);
    } else {
        $host = $_SERVER['HTTP_HOST'];
        // we are free to go, redirect to thank you
        header("Location: http://$host/get-our-emails/confirm");
    }

} catch (Exception $e) {
    print "uncaught exception\n";
    print_r($e);
}


/**
 * @param $email
 * @return mixed
 */

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}