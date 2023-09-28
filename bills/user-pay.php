<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);

$country = !empty($requestBody["country"]) ? mysqli_real_escape_string($dbc, trim($requestBody["country"])) : "";
$city = !empty($requestBody["city"]) ? mysqli_real_escape_string($dbc, trim($requestBody["city"])) : "";
$cardNumber = !empty($requestBody["cardNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["cardNumber"])) : "";
$accountNumber = !empty($requestBody["accountNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["accountNumber"])) : "";
$confirmAccountNumber = !empty($requestBody["confirmAccountNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["confirmAccountNumber"])) : "";
$address = !empty($requestBody["address"]) ? mysqli_real_escape_string($dbc, trim($requestBody["address"])) : "";
$firstName = !empty($requestBody["firstName"]) ? mysqli_real_escape_string($dbc, trim($requestBody["firstName"])) : "";
$lastName = !empty($requestBody["lastName"]) ? mysqli_real_escape_string($dbc, trim($requestBody["lastName"])) : "";
$state = !empty($requestBody["state"]) ? mysqli_real_escape_string($dbc, trim($requestBody["state"])) : "";
$zipCode = !empty($requestBody["zipCode"]) ? mysqli_real_escape_string($dbc, trim($requestBody["zipCode"])) : "";
$month = !empty($requestBody["month"]) ? mysqli_real_escape_string($dbc, trim($requestBody["month"])) : "";
$year = !empty($requestBody["year"]) ? mysqli_real_escape_string($dbc, trim($requestBody["year"])) : "";
$cvc = !empty($requestBody["cvc"]) ? mysqli_real_escape_string($dbc, trim($requestBody["cvc"])) : "";
$amount = !empty($requestBody["amount"]) ? mysqli_real_escape_string($dbc, trim($requestBody["amount"])) : "0.00";
$paymentType = !empty($requestBody["paymentType"]) ? mysqli_real_escape_string($dbc, trim($requestBody["paymentType"])) : "";
$paymentMethod = !empty($requestBody["paymentMethod"]) ? mysqli_real_escape_string($dbc, trim($requestBody["paymentMethod"])) : "";
$routingNumber = !empty($requestBody["routingNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["routingNumber"])) : "";
$accountType = !empty($requestBody["accountType"]) ? mysqli_real_escape_string($dbc, trim($requestBody["accountType"])) : "";
$billNumber = !empty($requestBody["billNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["billNumber"])) : "";
$originalAmount = !empty($requestBody["originalAmount"]) ? mysqli_real_escape_string($dbc, trim($requestBody["originalAmount"])) : "0.00";
$TxId = time();
if ($paymentType === "amount-due") {
    $s = "UPDATE bills SET 
    status = 'paid' , 
    remainingBalance='0.00',
    paidOn= '" . date('Y-m-d') . "', 
    paymentMethod='$paymentMethod', 
    amountPaid=$originalAmount ,
    paidTime='" . date('H:i:s') . "'
    WHERE bill_id=$billNumber";
    $e = mysqli_query($dbc, $s);
}
if ($paymentType === "other-amount") {
    $s = "UPDATE bills SET 
    status = 'partial' , 
    remainingBalance=$originalAmount-$amount,
    paidOn= '" . date('Y-m-d') . "',
    amountPaid = $amount, 
    paymentMethod='$paymentMethod' ,
    paidTime='" . date('H:i:s') . "'
    WHERE bill_id=$billNumber";
    $e = mysqli_query($dbc, $s);
}

$tSql = "INSERT INTO `payments`
 SELECT  $TxId,`bill_id`, `user`, `billTypeId`, `billType`, `billNumber`, `totalDueAmount`, `dueDate`, `remainingBalance`, `title`, `status`, `statementDate`, `admin`, `paymentMethod`, `paidBy`, `paidOn`,`amountPaid`,`paidTime` FROM bills WHERE bill_id=$billNumber;";

$tExe = mysqli_query($dbc, $tSql);


$res['status'] = true;
$res['message'] = "Payment successful";
$res['data'] = $TxId;
echo json_encode($res);
exit();
