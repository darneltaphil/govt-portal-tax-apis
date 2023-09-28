<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['user_id'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request: the user is unknown";
    echo json_encode($res);
    exit();
}

$user_id =  mysqli_real_escape_string($dbc, clean_text($requestBody['user_id']));
//echo "SELECT * FROM bill_view WHERE `admin` =$user_id AND status='paid' AND paidOn='" . date('Y-m-d') . "'";
$exe = mysqli_query($dbc, "SELECT * FROM bill_view WHERE `admin` =$user_id AND status='paid' AND paidOn='" . date('Y-m-d') . "'");
$count = mysqli_query($dbc, "SELECT count(*) as tx FROM bill_view WHERE `admin` =$user_id AND status='paid' AND paidOn='" . date('Y-m-d') . "'");
$amount = mysqli_query($dbc, "SELECT SUM(amountPaid) FROM bill_view WHERE `admin` =$user_id AND status='paid' AND paidOn='" . date('Y-m-d') . "'");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    $res['tx'] = mysqli_fetch_row($count);
    $res['amount'] = mysqli_fetch_row($amount);
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
