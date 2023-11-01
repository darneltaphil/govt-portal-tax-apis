<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['userId'])) {
    $result['status'] = false;
    $result['message'] = "Incomplete request: the user is unknown";
    echo json_encode($result);
    exit();
}

$userId =  mysqli_real_escape_string($dbc, clean_text($requestBody['userId']));

$sql = "SELECT * FROM user_settings WHERE userSettingUser=$userId";
$exe = mysqli_query($dbc, $sql);
$res = mysqli_fetch_assoc($exe);

$cc_sql = "SELECT * FROM user_cc WHERE user_id=$userId";
$cc_exe = mysqli_query($dbc, $cc_sql);
$cc_res = mysqli_fetch_assoc($cc_exe);

$result['status'] = true;
$result['cc'] = $cc_res;
$result['settings'] = $res;
echo json_encode($result);
exit();
