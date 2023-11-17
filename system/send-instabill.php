<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
include("../gateway/service.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['number']) || empty($requestBody['bill'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete Request";
    echo json_encode($res);
    exit();
}

$number =  mysqli_real_escape_string($dbc, clean_text($requestBody['number']));
$bill =  mysqli_real_escape_string($dbc, clean_text($requestBody['bill']));

$sql = "SELECT * FROM bill_view WHERE billId=$bill";
$exe = mysqli_query($dbc, $sql);
$row = mysqli_fetch_assoc($exe);

$msg = "Use the link below to pay the " . $row['billTypeName'] . " bill for 
" . $row['userFullName'] . "
" . $row['user_address'] . "
+" . $row['user_mobile'] . "

https://vision.staging.ipwebsolutions.com/app/pay-for-me/?bill=" . $row['billId'] . "

-- Vision Govt Solutions. --";

$res = sendSMS($msg, "+1" . $number);
echo json_encode($res);
