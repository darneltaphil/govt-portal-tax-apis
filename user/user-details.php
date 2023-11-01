<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['userId'])) {
    $result['status'] = false;
    $result['message'] = "Incomplete request: the user is unknown";
    echo json_encode($result);
    exit();
}

$userId =  mysqli_real_escape_string($dbc, clean_text($requestBody['userId']));

$sql = "SELECT user_id, user_fname, user_sname, user_mobile, user_email, user_address, user_city, user_county, user_account_number, user_is_active, user_registration_date FROM `users` WHERE user_id=$userId";
$exe = mysqli_query($dbc, $sql);
$res = mysqli_fetch_assoc($exe);


$result['status'] = true;
$result['data'] = $res;
echo json_encode($result);
exit();
