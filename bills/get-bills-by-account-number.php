<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);

if (empty($requestBody['name']) && empty($requestBody['address']) && empty($requestBody['account'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request";
    echo json_encode($res);
    exit();
}

// $name =  mysqli_real_escape_string($dbc, clean_text($requestBody['name']));
// $address =  mysqli_real_escape_string($dbc, clean_text($requestBody['address']));
$account =  mysqli_real_escape_string($dbc, clean_text($requestBody['account']));
$billTypeId =  mysqli_real_escape_string($dbc, clean_text($requestBody['billTypeId']));

$exe = mysqli_query($dbc, "SELECT * FROM `bill_view` WHERE accountNumber =$account  AND billTypeId=$billTypeId ");
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
