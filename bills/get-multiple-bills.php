<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);

$bill_1 =  mysqli_real_escape_string($dbc, clean_text($requestBody["cart"]['1']));
$bill_2 =  mysqli_real_escape_string($dbc, clean_text($requestBody["cart"]['2']));
$sql = "SELECT * FROM `bill_view` WHERE billId =$bill_1  OR billId =$bill_2 ";
if (!empty($requestBody["cart"]['3'])) {
    $bill_3 = mysqli_real_escape_string($dbc, clean_text($requestBody["cart"]['3']));
    $sql = "SELECT * FROM `bill_view` WHERE billId =$bill_1  OR billId =$bill_2 OR billId=$bill_3 ";
}


$exe = mysqli_query($dbc, $sql);
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
