<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

$res = [];
$error  = [];

if (empty($requestBody['email'])) {
    $error[] = "Email is required";
} else {
    $un =  mysqli_real_escape_string($dbc, clean_text($requestBody['email']));
}

if (empty($requestBody['password'])) {
    $error[] = "Password is required";
} else {
    $pwd = mysqli_real_escape_string($dbc, trim($requestBody['password']));
}

if (count($error) > 0) {
    $res['message'] = $error;
    $res['status'] = false;
    echo json_encode($res);
    exit;
}

$sql = "SELECT * FROM  users  WHERE user_email = :un AND user_password = :pwd AND user_is_active =1";

$statement = $dbc_pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
$statement->execute(array(':un' => filter_var($un, FILTER_SANITIZE_EMAIL), ':pwd' => convert_string('encrypt', $pwd)));
$row = $statement->fetchAll(PDO::FETCH_ASSOC);
if (count($row) > 0) {
    $LOCALSTORAGE = array();
    $LOCALSTORAGE['firstname'] = $row[0]['user_fname'];
    $LOCALSTORAGE['lastname'] = $row[0]['user_sname'];
    $LOCALSTORAGE['email'] = $row[0]['user_email'];
    $LOCALSTORAGE['name'] = $row[0]['user_fname'] . ' ' . $row[0]['user_sname'];
    $LOCALSTORAGE['key'] =  $row[0]['user_id'];

    $res['data']      = $LOCALSTORAGE;
    $res['redirect']    = "dashboard";
    $res['status'] = true;
    $res['message'] = "Login successful";
    echo json_encode($res);
    exit;
} else {
    $error[] = "Username and Password do not match";
    $res['message']  = $error;
    $res['status']   = false;
    echo json_encode($res);
    exit;
}
