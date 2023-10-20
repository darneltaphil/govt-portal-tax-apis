<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;

$userId = isset($requestBody["userId"]) ? mysqli_real_escape_string($dbc, clean_text($requestBody['userId'])) : 0;
$from = isset($requestBody["from"]) ?  mysqli_real_escape_string($dbc, clean_text($requestBody['from'])) : '';
$to =  isset($requestBody["to"]) ? mysqli_real_escape_string($dbc, clean_text($requestBody['to'])) : '';

$exe = mysqli_query($dbc, "select `b`.`billId` AS `billId`,`b`.`billUserName` AS `billUserName`,`b`.`billUserAddress` AS `billUserAddress`,`b`.`billUserAccount` AS `billUserAccount`,`b`.`billNumber` AS `billNumber`,`b`.`billTypeId` AS `billTypeId`,`b`.`billType` AS `billTypeName`,`b`.`billTotalDueAmount` AS `totalDueAmount`,`b`.`billRemainingBalance` AS `billRemainingBalance`,`b`.`billDueDate` AS `billDueDate`,`b`.`billStatementDate` AS `billStatementDate`,`b`.`billTitle` AS `billTitle`,`b`.`billStatus` AS `billStatus`,`b`.`billPenalty` AS `billPenalty`,`b`.`billOverdue` AS `billOverdue`,`u`.`user_id` AS `user_id`,concat(`u`.`user_fname`,`u`.`user_sname`) AS `userFullName`,`u`.`user_mobile` AS `user_mobile`,`u`.`user_email` AS `user_email`,`u`.`user_address` AS `user_address`,`u`.`user_county` AS `user_county`,`u`.`user_city` AS `user_city`,`u`.`user_account_number` AS `user_account_number`,`s`.`state_name` AS `state_name`,`p`.`penaltyId` AS `penaltyId`,`p`.`penaltyAmount` AS `penaltyAmount`,`p`.`penaltyWaived` AS `penaltyWaived`,`p`.`penaltyWaivedReason` AS `penaltyWaivedReason`,`p`.`penaltyWaivedAuth` AS `penaltyWaivedAuth`,`pp`.`paymentDate` AS `paymentDate`,`pp`.`paymentTime` AS `paymentTime`,`pp`.`paymentMethod` AS `paymentMethod`,`pp`.`paymentType` AS `paymentType`,`pp`.`paymentFee` AS `paymentFee`,`pp`.`paymentAmount` AS `paymentAmount`,`pp`.`paymentBy` AS `paymentBy`,`pp`.`paymentByWho` AS `paymentByWho`,`pp`.`paymentId` AS `paymentId`,`pp`.`paymentAdjusted` AS `paymentAdjusted`,`pp`.`user` AS `loggedInUser` from ((((`staging23_tax`.`bills` `b` left join `staging23_tax`.`users` `u` on(`u`.`user_id` = `b`.`user`)) left join `staging23_tax`.`states` `s` on(`s`.`state_id` = `u`.`user_state`)) left join `staging23_tax`.`penalties` `p` on(`p`.`penaltyBill` = `b`.`billId`)) left join `staging23_tax`.`payments` `pp` on(`pp`.`paymentBill` = `b`.`billId`)) where `pp`.`user` = '1' and paymentAdjusted=1");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = true;
    $res['message'] = "No transactions found";
    echo json_encode($res);
    exit();
}
