<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);

$account =  11110000;
$billTypeId =  1;

$exe = mysqli_query($dbc, "SELECT * FROM `bill_view` WHERE user_account_number =$account  AND billTypeId=$billTypeId ");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = false;
    $res['message'] = "No bills found";
    echo json_encode($res);
    exit();
}
