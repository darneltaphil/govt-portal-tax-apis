<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

if (empty($requestBody['id'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request: the account is unknown";
    echo json_encode($res);
    exit();
}

$id =  mysqli_real_escape_string($dbc, clean_text($requestBody['id']));

$exe = mysqli_query($dbc, "SELECT * FROM operations WHERE operationAccount =$id AND operationDate='" . date('Y-m-d') . "' ;");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = false;
    $res['data'] = [];
    $res['message'] = "no operations found";
    echo json_encode($res);
    exit();
}
