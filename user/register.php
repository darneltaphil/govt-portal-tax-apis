<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;
$res = [];
$sname = !empty($requestBody["user_lastname"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_lastname"])) : "";
$fname = !empty($requestBody["user_firstname"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_firstname"])) : "";
$email = !empty($requestBody["user_email"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_email"])) : "";
$mobile = !empty($requestBody["user_mobile"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_mobile"])) : "";
$address = !empty($requestBody["user_address"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_address"])) : "";
$city = !empty($requestBody["user_city"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_city"])) : "";
$county = !empty($requestBody["user_county"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_county"])) : "";
$country = !empty($requestBody["user_country"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_country"])) : "1";
$state = !empty($requestBody["user_state"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_state"])) : "";
$account = !empty($requestBody["user_account"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_account"])) : "";
$pwd = !empty($requestBody["user_password"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_password"])) : "";

$check_email_exe = mysqli_query($dbc, "SELECT * FROM users WHERE user_email ='" . $email . "' OR user_mobile='" . $mobile . "'");
if (mysqli_num_rows($check_email_exe) > 0) {
    $res['status'] = false;
    $res['message'] = "The user email or mobile number already exists";
    echo json_encode($res);
    exit();
}


$sql = "INSERT INTO `users` (
        `id`, 
    `user_fname`, 
    `user_sname`, 
    `user_email`, 
    `user_mobile`, 
    `user_password`, 
    `user_address`, 
    `user_city`, 
    `user_county`, 
    `user_state`, 
    `user_country`, 
    `user_account_number`,
    `user_registration_date`,
    `user_is_active`    ) 
    VALUES (
        NULL, 
         '" . $fname . " ',
         '" . $sname . "', 
         '" . $email . "', 
         '" . $mobile . "', 
         '" . convert_string("encrypt", $pwd) . "', 
         '" . $address . "', 
         '" . $city . "', 
         '" . $county . "', 
         '" . $state . "', 
         '" . $country . "', 
         '" . $account . "', 
         '" . date("Y-m-d H:i:s") . "', 
         '1'
        );";

$exe = mysqli_query($dbc, $sql);
if ($exe) {

    $res['status'] = true;
    $res['message'] = "Registration Successful";
} else {
    $res['status'] = false;
    $res['message'] = "Registration Failed";
}
echo json_encode($res);
exit();
