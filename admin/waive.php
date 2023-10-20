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

if (empty($requestBody['billId'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request: unknown bill number";
    echo json_encode($res);
    exit();
}

$userId = mysqli_real_escape_string($dbc, clean_text($requestBody['userId']));
$waiveReason = isset($requestBody["waiveReason"]) ?  mysqli_real_escape_string($dbc, clean_text($requestBody['waiveReason'])) : '';
$billId =  mysqli_real_escape_string($dbc, clean_text($requestBody['billId']));

$wSql = "UPDATE bills SET billOverdue=0 , billPenalty=0 WHERE billId=$billId";
$pSql = "UPDATE penalties SET penaltyWaived=1 , penaltyWaivedReason='$waiveReason', penaltyAmount =0 WHERE penaltyBill=$billId";

$wExe = mysqli_query($dbc, $wSql);
$pExe = mysqli_query($dbc, $pSql);

$res['status'] = true;
$res['message'] = "Bill Penalty and Interest waived";
echo json_encode($res);
exit();
