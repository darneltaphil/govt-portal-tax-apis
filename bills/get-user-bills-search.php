<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;
//var_dump($requestBody);
if (empty($requestBody['userId'])) {
    $res['status'] = false;
    $res['message'] = "Incomplete request: the user is unknown";
    echo json_encode($res);
    exit();
}
$userId =  mysqli_real_escape_string($dbc, clean_text($requestBody['userId']));
$billTypeId =  mysqli_real_escape_string($dbc, clean_text($requestBody['billTypeId']));
$searchText = (isset($requestBody["searchText"]) && strlen($requestBody["searchText"]) > 0) ? mysqli_real_escape_string($dbc, trim($requestBody["searchText"])) : "";
$searchBy = isset($requestBody["searchBy"]) ? mysqli_real_escape_string($dbc, trim($requestBody["searchBy"])) : "";


if (strlen($searchText) > 0 && $searchBy == 'billNumber') {
    // echo "1 SELECT * FROM bill_view WHERE user_id =$userId AND billTypeId=$billTypeId AND billNumber='$searchText'";
    $exe = mysqli_query($dbc, "SELECT * FROM bill_view WHERE user_id =$userId AND billTypeId=$billTypeId AND billNumber='$searchText'");
} elseif (strlen($searchText) > 0 && $searchBy == 'address') {
    // echo "2 SELECT * FROM bill_view WHERE user_id =$userId AND billTypeId=$billTypeId AND user_address LIKE '%$searchText%' ";
    $exe = mysqli_query($dbc, "SELECT * FROM bill_view WHERE user_id =$userId AND billTypeId=$billTypeId AND user_address LIKE '%$searchText%' ");
} else {
    // echo "3 SELECT * FROM bill_view WHERE user_id =$userId AND billTypeId=$billTypeId";
    $exe = mysqli_query($dbc, "SELECT * FROM bill_view WHERE user_id =$userId AND billTypeId=$billTypeId");
}
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = false;
    $res['message'] = "No bills found with the details provided";
    echo json_encode($res);
    exit();
}
