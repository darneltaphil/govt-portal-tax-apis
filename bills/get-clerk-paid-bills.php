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

$exe = mysqli_query($dbc, "SELECT * FROM paid_view WHERE `admin`=$user_id AND status='paid' ORDER BY paidOn DESC");
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
