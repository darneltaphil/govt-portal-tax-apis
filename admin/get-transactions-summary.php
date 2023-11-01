<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['userId'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request: the user is unknown";
    echo json_encode($res);
    exit();
}

$userId =  mysqli_real_escape_string($dbc, clean_text($requestBody['userId']));

$exe = mysqli_query($dbc, "SELECT * FROM paid_bill_view WHERE `loggedInUser` =$userId AND billstatus='paid' AND paymentDate='" . date('Y-m-d') . "'");
$count = mysqli_query($dbc, "SELECT count(*) as tx FROM paid_bill_view WHERE `loggedInUser` ='$userId' AND billstatus='paid' AND paymentDate='" . date('Y-m-d') . "'");
$amount = mysqli_query($dbc, "SELECT (SUM(totalDueAmount) + SUM(paymentFee) + SUM(penaltyAmount) )as total FROM paid_bill_view WHERE `loggedInUser` ='$userId' AND billstatus='paid' AND paymentDate='" . date('Y-m-d') . "'");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    $res['tx'] = mysqli_fetch_row($count);
    $res['amount'] = mysqli_fetch_all($amount);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = true;
    $res['data'] = [];
    $res['tx'] = 0;
    $res['amount'] = 0;
    $res['message'] = "No transactions found";
    echo json_encode($res);
    exit();
}
