<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);

$till1 = !empty($requestBody["till1"]) ? mysqli_real_escape_string($dbc, trim($requestBody["till1"])) : '0.00';
$till2 = !empty($requestBody["till2"]) ? mysqli_real_escape_string($dbc, trim($requestBody["till2"])) : "0.00";
$user1 = !empty($requestBody["user1"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user1"])) : "";
$user2 = !empty($requestBody["user2"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user2"])) : "";

$find = mysqli_query($dbc, "SELECT * FROM till_balance WHERE setDate ='" . date('Y-m-d') . "'");
if (mysqli_num_rows($find) > 0) {
    exit();
    $exe = mysqli_query($dbc, "UPDATE `till_balance` SET `startAmount` = '$till1'  WHERE `till_balance`.`till` = '1' AND `setDate` = '" . date("Y-m-d") . "';");
    $exe_ = mysqli_query($dbc, "UPDATE `till_balance` SET `startAmount` = '$till2'  WHERE `till_balance`.`till` = '2' AND `setDate` = '" . date("Y-m-d") . "';");

    if ($exe && $exe_) {
        $res['status'] = true;
        $res['message'] = "Till Balances Updated";
        echo json_encode($res);
        exit();
    }
}
$exe = mysqli_query($dbc, "INSERT INTO `till_balance` (`id`, `startAmount`, `endAmount`, `setBy`, `setDate`, `setTime`, `confirmed`, `till`, `user`)
 VALUES (NULL, '$till1', NULL, '2', '" . date("Y-m-d") . "', '" . date("H:i:s") . "', NULL, '1','$user1');");
$exe_ = mysqli_query($dbc, "INSERT INTO `till_balance` (`id`, `startAmount`, `endAmount`, `setBy`, `setDate`, `setTime`, `confirmed`, `till`, `user`)
 VALUES (NULL, '$till2', NULL, '2', '" . date("Y-m-d") . "', '" . date("H:i:s") . "', NULL, '2','$user2');");


if ($exe && $exe_) {
    $res['status'] = true;
    $res['message'] = "Till opening balance set";
    echo json_encode($res);
    exit();
} else {
    $res['status'] = true;
    $res['message'] = "There was an error";
    echo json_encode($res);
    exit();
}
