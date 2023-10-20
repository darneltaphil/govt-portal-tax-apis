<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['billId'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request: Bill not found";
    echo json_encode($res);
    exit();
}

$billId =  mysqli_real_escape_string($dbc, clean_text($requestBody['billId']));
$action =  mysqli_real_escape_string($dbc, clean_text($requestBody['action']));
$reason =  mysqli_real_escape_string($dbc, clean_text($requestBody['reason']));

$exe_ = mysqli_query($dbc, "SELECT * FROM payments WHERE paymentBill= $billId");
$res_ = mysqli_fetch_all($exe_, MYSQLI_ASSOC);
$amountPaid = $res_[0]['paymentAmount'];

$exe = mysqli_query($dbc, "UPDATE bills SET billStatus='unpaid', billRemainingBalance='$amountPaid' WHERE billId='$billId'");

$delSql = "UPDATE payments SET paymentAdjusted=1  WHERE paymentBill='$billId'";
// $delSql = "DELETE FROM payments WHERE paymentBill=$billId";
$delExe = mysqli_query($dbc, $delSql);

$message = '';
if ($action == 'refund') {
    $message = 'Payment Refunded Successfully';
}
if ($action == 'void') {
    $message = 'Payment Voided Successfully';
}
if ($exe && $delExe) {
    $res['status'] = true;
    $res['data'] = $message;
    echo json_encode($res);
    exit();
}
