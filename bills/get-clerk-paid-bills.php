<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

$userId = isset($requestBody["userId"]) ? mysqli_real_escape_string($dbc, clean_text($requestBody['userId'])) : 1;
$from = isset($requestBody["from"]) ?  mysqli_real_escape_string($dbc, clean_text($requestBody['from'])) : '';
$to =  isset($requestBody["to"]) ? mysqli_real_escape_string($dbc, clean_text($requestBody['to'])) : '';

$exe = mysqli_query($dbc, "SELECT * FROM paid_bill_view WHERE billStatus='paid' AND paymentBy=$userId AND paymentDate='" . date('Y-m-d') . "' ORDER BY paymentDate DESC");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = true;
    $res['message'] = "No transactions found";
    echo json_encode($res);
    exit();
}
