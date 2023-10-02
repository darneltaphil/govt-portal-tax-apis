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

$exe = mysqli_query($dbc, "SELECT * FROM bill_view WHERE user_id =$userId");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
}
