<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;
if (empty($requestBody['name']) && empty($requestBody['address']) && empty($requestBody['account'])) {
    $res['status'] = false;
    $res['customeDisplay'] = "Search for bill by name, address or account number";
    $res['message'] = "Incomplete request";
    echo json_encode($res);
    exit();
}

$name =  mysqli_real_escape_string($dbc, clean_text($requestBody['name']));
$name_split = explode(" ", $name);
$address =  mysqli_real_escape_string($dbc, clean_text($requestBody['address']));
$account =  mysqli_real_escape_string($dbc, clean_text($requestBody['account']));
$billTypeId =  mysqli_real_escape_string($dbc, clean_text($requestBody['billTypeId']));
$sql = "SELECT * FROM `bill_view` WHERE userFullName LIKE '%$name_split[0]%' AND billTypeId=$billTypeId ";
$exe = mysqli_query($dbc, $sql);
// $exe = mysqli_query($dbc, "SELECT * FROM `bill_view` WHERE CONCAT('user_fname', ' ', ' user_sname');(user_fname LIKE '%$name%' OR user_sname LIKE '%$name%'");
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
