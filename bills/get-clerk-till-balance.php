<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

$userId = isset($requestBody["userId"]) ? mysqli_real_escape_string($dbc, clean_text($requestBody['userId'])) : 0;
$from = isset($requestBody["from"]) ?  mysqli_real_escape_string($dbc, clean_text($requestBody['from'])) : '';
$to =  isset($requestBody["to"]) ? mysqli_real_escape_string($dbc, clean_text($requestBody['to'])) : '';
$exe_ = mysqli_query($dbc, "SELECT * FROM till_balance WHERE setDate ='" . date('Y-m-d') . "' AND user='1'");

$res_ = mysqli_fetch_assoc($exe_);
$tillAmount = mysqli_num_rows($exe_) > 0 ? $res_['startAmount'] : 0;
$exe = mysqli_query($dbc, "SELECT SUM(paymentAmount) as main , SUM(paymentFee) as fee FROM paid_bill_view WHERE billStatus='paid' AND paymentBy=1 AND paymentDate='" . date('Y-m-d') . "' AND paymentMethod='cash'");
$res = mysqli_fetch_assoc($exe);
if ($exe) {
    $res__['status'] = true;
    $res__['till'] = $tillAmount;
    $res__['cash'] = $res['main'];
    $res__['fees'] = $res['fee'];;
    echo json_encode($res__);
    exit();
}
