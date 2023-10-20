<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);;
$res = [];
$sname = !empty($requestBody["user_lastname"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_lastname"])) : "";
$fname = !empty($requestBody["user_firstname"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_firstname"])) : "";
$email = !empty($requestBody["user_email"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_email"])) : "";
$mobile = !empty($requestBody["user_mobile"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_mobile"])) : "";
$address = !empty($requestBody["user_address"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_address"])) : "";
$city = !empty($requestBody["user_city"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_city"])) : "";
$county = !empty($requestBody["user_county"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_county"])) : "";
$country = !empty($requestBody["user_country"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_country"])) : "1";
$state = !empty($requestBody["user_state"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_state"])) : "";
$account = !empty($requestBody["user_account"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_account"])) : "";
$pwd = !empty($requestBody["user_password"]) ? mysqli_real_escape_string($dbc, trim($requestBody["user_password"])) : "";

$check_email_exe = mysqli_query($dbc, "SELECT * FROM users WHERE user_email ='" . $email . "' OR user_mobile='" . $mobile . "'");
if (mysqli_num_rows($check_email_exe) > 0) {
    $res['status'] = false;
    $res['message'] = "The user email or mobile number already exists";
    echo json_encode($res);
    exit();
}

$sql = "INSERT INTO `users` (
    `user_id`, 
    `user_fname`, 
    `user_sname`, 
    `user_mobile`, 
    `user_email`, 
    `user_password`, 
    `user_address`, 
    `user_city`, 
    `user_county`, 
    `user_state`, 
    `user_country`, 
    `user_account_number`,
    `user_registration_date`,
    `user_is_active`,
    `user_role`) 
    VALUES (
        NULL, 
         '" . $fname . " ',
         '" . $sname . "', 
         '" . $mobile . "', 
         '" . $email . "', 
         '" . convert_string("encrypt", $pwd) . "', 
         '" . $address . "', 
         '" . $city . "', 
         '" . $county . "', 
         '" . $state . "', 
         '" . $country . "', 
         '" . $account . "', 
         '" . date("Y-m-d H:i:s") . "', 
         '1',
         'user'
        );";

$exe = mysqli_query($dbc, $sql);
if ($exe) {

    /**
     * Getting the user details back.
     * I should have used LAST_INSERTED_ROW, but the SQL connection does not stay open.
     */
    $getSql = "SELECT * FROM users WHERE user_email='$email'";
    $getExe = mysqli_query($dbc, $getSql);
    if ($getExe) {
        $userId = mysqli_fetch_row($getExe)[0];
        $r_1 = rand(1, 10000) / 100;
        $r_11 = rand(1, 10000) / 100;
        $r_2 = rand(1, 10000) / 100;
        $r_3 = rand(1, 10000) / 100;
        // Data to be inserted (you can fetch this from an array or any other source)
        $dataToInsert = [
            [
                "1",
                "Property Tax",
                rand(10000, 999999),
                number_format($r_1, 2),
                '2023-10-15',
                number_format($r_1, 2),
                "MOBILE HOME NOTICE FOR THE EVANS COUNTY",
                "unpaid",
                '2023-08-15',
                "0.00",
                null
            ],
            [
                "1",
                "Property Tax",
                rand(10000, 999999),
                number_format($r_11, 2),
                '2023-10-15',
                number_format($r_11, 2),
                "MOBILE HOME NOTICE FOR THE EVANS COUNTY",
                "unpaid",
                '2023-08-15',
                "0.00",
                null
            ],
            [
                "1",
                "Property Tax",
                rand(10000, 999999),
                number_format($r_2, 2),
                '2023-10-15',
                number_format($r_2 - 9, 2),
                "MOBILE HOME NOTICE FOR THE EVANS COUNTY", "partial",
                '2023-08-15',
                "9.00",
                null
            ],
            // [
            //     "1",
            //     "Property Tax",
            //     rand(10000, 999999),
            //     number_format($r_3, 2),
            //     '2023-10-15',
            //     '0.00',
            //     "MOBILE HOME NOTICE FOR THE EVANS COUNTY",
            //     "paid",
            //     '2023-08-15',
            //     number_format($r_3, 2),
            //     '2023-09-06'
            // ],

            [
                "2",
                "DMV",
                rand(10000, 999999),
                number_format($r_2, 2),
                '2023-10-15',
                number_format($r_2, 2),
                "DMV",
                "unpaid",
                '2023-08-15',
                "0.00",
                null
            ],
            [
                "2",
                "DMV",
                rand(10000, 999999),
                number_format($r_1, 2),
                '2023-10-15',
                number_format($r_1 - 1, 2),
                "DMV",
                "partial",
                '2023-08-15',
                "1.00",
                null
            ],
            // [
            //     "2",
            //     "DMV",
            //     rand(10000, 999999),
            //     number_format($r_3, 2),
            //     '2023-10-15',
            //     '0.00',
            //     "DMV",
            //     "paid",
            //     '2023-08-15',
            //     number_format($r_3, 2),
            //     '2023-09-06'
            // ],

            [
                "3",
                "Water / Sewer",
                rand(10000, 999999),
                number_format($r_1, 2),
                '2023-10-15',
                number_format($r_1, 2),
                "WATTER / SEWER BILL",
                "unpaid",
                '2023-08-15',
                "0.00",
                null
            ],
            [
                "3",
                "Water / Sewer",
                rand(10000, 999999),
                number_format($r_3, 2),
                '2023-10-15',
                number_format($r_3 - 5, 2),
                "WATTER / SEWER BILL",
                "partial",
                '2023-08-15',
                "5.00",
                null
            ],
            // [
            //     "3",
            //     "Water / Sewer",
            //     rand(10000, 999999),
            //     number_format($r_1, 2),
            //     '2023-10-15',
            //     '0.00',
            //     "WATTER / SEWER BILL",
            //     "paid",
            //     '2023-08-15',
            //     number_format($r_1, 2),
            //     '2023-09-06'
            // ],
        ];

        // Prepare and execute INSERT queries in a loop
        foreach ($dataToInsert as $data) {
            $billTypeId = $data[0];
            $billTypeName = $data[1];
            $billNumber = $data[2];
            $totalDueAmount = $data[3];
            $dueDate = $data[4];
            $remainingBalance = $data[5];
            $title = $data[6];
            $status = $data[7];
            $statementDate = $data[8];
            $amountPaid = $data[9];
            $paidOn = $data[10];

            $billSql =  " INSERT INTO `bills` (
                `billId`, 
                `user`, 
                `billUserName`, 
                `billUserAddress`, 
                `billUserAccount`, 
                `billNumber`, 
                `billTypeId`, 
                `billType`, 
                `billTotalDueAmount`, 
                `billRemainingBalance`, 
                `billDueDate`, 
                `billStatementDate`, 
                `billTitle`, 
                `billStatus`, 
                `billPenalty`,
                `billOverdue` )        
               VALUES (
                NULL, 
               '$userId', 
               '',
               '',
               '',
               '$billNumber', 
               '$billTypeId', 
               '$billTypeName', 
               '$totalDueAmount', 
               '$remainingBalance', 
               '$dueDate', 
               '$statementDate', 
               '$title', 
               '$status',
               '0',
               '0' 
              );";

            mysqli_query($dbc, $billSql);
        }
    }

    $res['status'] = true;
    $res['message'] = "Registration Successful";
} else {
    $res['status'] = false;
    $res['message'] = "Registration Failed";
}
echo json_encode($res);
exit();
