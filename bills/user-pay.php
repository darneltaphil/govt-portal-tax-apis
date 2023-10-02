<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);

$accountNumber = !empty($requestBody["accountNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["accountNumber"])) : "";
$confirmAccountNumber = !empty($requestBody["confirmAccountNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["confirmAccountNumber"])) : "";
$accountType = !empty($requestBody["accountType"]) ? mysqli_real_escape_string($dbc, trim($requestBody["accountType"])) : "";
$address = !empty($requestBody["address"]) ? mysqli_real_escape_string($dbc, trim($requestBody["address"])) : "";
$amount = !empty($requestBody["amount"]) ? mysqli_real_escape_string($dbc, trim($requestBody["amount"])) : "0.00";
$billDueDate = !empty($requestBody["billDueDate"]) ? mysqli_real_escape_string($dbc, trim($requestBody["billDueDate"])) : "";

$billId = !empty($requestBody["billId"]) ? mysqli_real_escape_string($dbc, trim($requestBody["billId"])) : "";
$billNumber = !empty($requestBody["billNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["billNumber"])) : "";

$billOverdue = !empty($requestBody["billOverdue"]) ? mysqli_real_escape_string($dbc, trim($requestBody["billOverdue"])) : "";
$billPenalty = !empty($requestBody["billPenalty"]) ? mysqli_real_escape_string($dbc, trim($requestBody["billPenalty"])) : "";
$penaltyId = !empty($requestBody["penaltyId"]) ? mysqli_real_escape_string($dbc, trim($requestBody["penaltyId"])) : 'null';
$penaltyAmount = !empty($requestBody["penaltyAmount"]) ? mysqli_real_escape_string($dbc, trim($requestBody["penaltyAmount"])) : "0.00";
$waiveReason = !empty($requestBody["penaltyWaivedReason"]) ? mysqli_real_escape_string($dbc, trim($requestBody["penaltyWaivedReason"])) : "";

$userId = !empty($requestBody["userId"]) ? mysqli_real_escape_string($dbc, trim($requestBody["userId"])) : "";
$firstName = !empty($requestBody["firstName"]) ? mysqli_real_escape_string($dbc, trim($requestBody["firstName"])) : "";
$lastName = !empty($requestBody["lastName"]) ? mysqli_real_escape_string($dbc, trim($requestBody["lastName"])) : "";

//PaymentInfo
$country = !empty($requestBody["country"]) ? mysqli_real_escape_string($dbc, trim($requestBody["country"])) : "";
$city = !empty($requestBody["city"]) ? mysqli_real_escape_string($dbc, trim($requestBody["city"])) : "";
$cardNumber = !empty($requestBody["cardNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["cardNumber"])) : "";
$state = !empty($requestBody["state"]) ? mysqli_real_escape_string($dbc, trim($requestBody["state"])) : "";
$zipCode = !empty($requestBody["zipCode"]) ? mysqli_real_escape_string($dbc, trim($requestBody["zipCode"])) : "";
$month = !empty($requestBody["month"]) ? mysqli_real_escape_string($dbc, trim($requestBody["month"])) : "";
$year = !empty($requestBody["year"]) ? mysqli_real_escape_string($dbc, trim($requestBody["year"])) : "";
$cvc = !empty($requestBody["cvc"]) ? mysqli_real_escape_string($dbc, trim($requestBody["cvc"])) : "";
$routingNumber = !empty($requestBody["routingNumber"]) ? mysqli_real_escape_string($dbc, trim($requestBody["routingNumber"])) : "";

$paymentType = !empty($requestBody["paymentType"]) ? mysqli_real_escape_string($dbc, trim($requestBody["paymentType"])) : "";
$paymentMethod = !empty($requestBody["paymentMethod"]) ? mysqli_real_escape_string($dbc, trim($requestBody["paymentMethod"])) : "";
$originalAmount = !empty($requestBody["originalAmount"]) ? mysqli_real_escape_string($dbc, trim($requestBody["originalAmount"])) : "0.00";
$TxId = time();
$fees = 0.00;
if ($paymentType === "amount-due") {
    $s = "UPDATE bills SET 
    billStatus = 'paid' , 
    billRemainingBalance='0.00'
    WHERE billId='$billId'";
    $e = mysqli_query($dbc, $s);

    $paymentAmount = $originalAmount;
    $fees = (($originalAmount * 0.03) < 1.80)
        ?  1.8
        : ($originalAmount * 0.03);
}


if ($paymentType === "other-amount") {
    $s = "UPDATE bills SET 
    billStatus = 'partial' , 
    billRemainingBalance=$originalAmount-$amount
    WHERE billId='$billId'";
    $e = mysqli_query($dbc, $s);

    $paymentAmount = $amount;
    $fees = (($amount * 0.03) < 1.80)
        ?  1.8
        : ($amount * 0.03);
}

if ($penaltyAmount > 0) {
    // $paymentAmount = $paymentAmount + $penaltyAmount;
}

if ($userId == '') {
    $paidBy = 'null';
    $paidByName = "Online Payment";
    $paidByWho = 0;
} else {
    $paidBy = $userId;
    $paidByName = "";
    $paidByWho = 1;
}

$tSql = "INSERT INTO `payments` (
`paymentId`, 
`paymentBill`, 
`user`, 
`paymentBy`, 
`paymentByName`, 
`paymentByWho`, 
`paymentAmount`, 
`paymentDate`, 
`paymentTime`, 
`paymentPenalty`, 
`paymentAdjusted`, 
`paymentAdjustedBy`, 
`paymentType`, 
`paymentMethod`, 
`paymentFee`, 
`paymentMulti`
) 

VALUES (
    $TxId, 
'$billId', 
'$userId', 
$paidBy, 
'$paidByName', 
'$paidByWho', 
'$paymentAmount', 
'" . date('Y-m-d') . "', 
'" . date('H:i:s') . "', 
$penaltyId, 
'0', 
NULL, 
'$paymentType', 
'$paymentMethod', 
'$fees', 
'0');
 ";

$tExe = mysqli_query($dbc, $tSql);

if ($tExe) {
    switch ($paymentMethod) {
        case 'ACH':
            $infoSql = ';';
            break;

        default:
            $infoSql = ';';
            break;
    }
}

$res['status'] = true;
$res['message'] = "Payment successful";
$res['data'] = $TxId;
echo json_encode($res);
exit();
