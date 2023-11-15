<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
include("../gateway/service.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;
setlocale(LC_MONETARY, "en_US");
if (empty($requestBody['number'])) {
    $res['status'] = false;
    $res['message'] = "No number input";
    echo json_encode($res);
    exit();
}

$number =  mysqli_real_escape_string($dbc, clean_text($requestBody['number']));
$txId =  mysqli_real_escape_string($dbc, clean_text($requestBody['txId']));
$amount =  mysqli_real_escape_string($dbc, clean_text($requestBody['amount']));
$billNumber =  mysqli_real_escape_string($dbc, clean_text($requestBody['billNumber']));

$amt = $amount + ($amount * 0.03 < 2 ? 2 : $amount * 0.03);
$msg = "Thank you for your payment.
Transaction Id: " . $txId . "
Bill #: " . $billNumber . "
Total Amount: $" . number_format($amt, 2, '.', '') . "
Date: " . date('Y-m-d H:i:s') . " 

Thank you for choosing Vision Govt Solutions.";


$res = sendSMS($msg, "+1" . $number);
echo json_encode($res);
