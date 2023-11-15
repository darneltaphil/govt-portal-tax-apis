<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
include("../gateway/service.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['number'])) {
    $res['status'] = false;
    $res['message'] = "No number input";
    echo json_encode($res);
    exit();
}

$number =  mysqli_real_escape_string($dbc, clean_text($requestBody['number']));

$msg = "<#>Your authorization code is 1111.
Please share this code with the cashier to complete payment.
Vision Govt Solutions.";

$res = sendSMS($msg, "+1" . $number);
echo json_encode($res);
