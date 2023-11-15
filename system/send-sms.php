<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
include("../gateway/service.php");
// include("https://demoridge.govtportal.com/gateway/service.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['number'])) {
    $res['status'] = false;
    $res['message'] = "No number input";
    echo json_encode($res);
    exit();
}

$number =  mysqli_real_escape_string($dbc, clean_text($requestBody['number']));
$txId =  mysqli_real_escape_string($dbc, clean_text($requestBody['txId']));
$amount =  mysqli_real_escape_string($dbc, clean_text($requestBody['amount']));
$billTypeName =  mysqli_real_escape_string($dbc, clean_text($requestBody['billTypeName']));
$billNumber =  mysqli_real_escape_string($dbc, clean_text($requestBody['billNumber']));
$type =  mysqli_real_escape_string($dbc, clean_text($requestBody['type']));

$amt = $amount + ($amount * 0.03 < 2 ? 2 : $amount * 0.03);
$msg = "Thank you for your payment. " . $billTypeName . " 
Bill Number: " . $billNumber . ". 
Total Amount: $" . $amt . ". 
Thank you for choosing GOVTPORTAL";
// $msg = "Thank you";
$url = "https://api.smsglobal.com/http-api.php?action=sendsms&user=7gzdc5w5&password=jHn6r8ph&from=GOVTPORTAL&apireply=1&to=" . $number . "&text=" . $msg . "&maxsplit=3";



$res = sendSMS($msg, "+14044481630", "+14704076378");

echo json_encode($res);
