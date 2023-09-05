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
$from =  mysqli_real_escape_string($dbc, clean_text($requestBody['from']));
$to =  mysqli_real_escape_string($dbc, clean_text($requestBody['to']));

//echo "SELECT * FROM bill_view WHERE user =$user_id AND status='paid' AND (paidOn BETWEEN $from AND $to) ORDER BY paidOn DESC";
$exe = mysqli_query($dbc, "SELECT * FROM bill_view WHERE user =$user_id AND status='paid' AND (paidOn BETWEEN $from AND $to) ORDER BY paidOn DESC");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = true;
    $res['data'] = [];
    $res['message'] = "No Bills found";
    echo json_encode($res);
    exit();
}
