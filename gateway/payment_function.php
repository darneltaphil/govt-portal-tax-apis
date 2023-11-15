<?php
function processPayment($retry = false)
{
    global $db;
    $type = filter_input(INPUT_POST, 'type');
    if (!in_array($type, ['manual', 'swipe', 'emv', 'token', 'chip', 'quickSale', 'charge', 'customer', 'ach', 'vault', 'cash'])) {
        echoErrorAndExit(400, 'Payment Type should be one of manual, swipe, emv, chip, quickSale, ach or customer.');
    }
    // check common POST params
    $portalID = filter_input(INPUT_POST, 'portalID');
    if (!$portalID) {
        echoErrorAndExit(400, 'Portal ID is required param.');
    }
    // check exist portal ID
    $portal = $db->where('Portal_Id', $portalID)->getOne('zoho_products');
    if (!$portal) {
        echoErrorAndExit(500, 'Wrong Portal ID');
    }
    $amount = sprintf('%.2f', filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    if ($amount < 0.01) {
        echoErrorAndExit(500, 'Wrong Amount');
    }
    $integratedID = filter_input(INPUT_POST, 'integratedID', FILTER_SANITIZE_STRING);
    if (!$integratedID) {
        $integratedID = NULL;
    } else {
        if (strpos($integratedID, ',')) {
            $integratedID = explode(',', $integratedID);
        }
    }
    $data = [];
    foreach (['PersonName', 'standard1', 'standard2', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'custom6', 'custom7', 'custom8', 'list1', 'list2', 'field_1', 'field_2', 'field_3', 'field_4', 'field_5', 'field_6', 'field_7', 'field_8', 'field_9', 'field_10'] as $value) {
        if (isset($_POST[$value])) {
            $data[$value] = filter_input(INPUT_POST, $value);
        }
    }
    if (count($data) == 0) {
        $data = NULL;
    }
    if ($integratedID == NULL && $data == NULL) {
        echoErrorAndExit(500, 'Wrong data');
    }
    $user = filter_input(INPUT_POST, 'user');
    if ($user == '') {
        $user = '--Unknown';
    }

    $method = filter_input(INPUT_POST, 'method');
    if (!$method) {
        $method = 'sale';
    }

    $forceFee = filter_input(INPUT_POST, 'forceFee');
    if ($forceFee) {
        $feeAmount = round(filter_input(INPUT_POST, 'feeAmount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 2);
        $tran = new GPTransaction($portalID, $user, $amount, $integratedID, $data, true, $feeAmount);
    } else {
        $tran = new GPTransaction($portalID, $user, $amount, $integratedID, $data);
    }
    $tran->setPaymentType($method);
    if (isset($_POST['authCard'])) {
        $tran->setAuthCard();
    }

    if ($type == 'ach') {
        $accountNumber = preg_replace("/[^0-9]/", '', filter_input(INPUT_POST, 'accountNumber'));
        $routingNumber = preg_replace("/[^0-9]/", '', filter_input(INPUT_POST, 'routingNumber'));
        $accountType = filter_input(INPUT_POST, 'accountType');
        $accountName = filter_input(INPUT_POST, 'accountName');
        if (!$accountNumber || !$routingNumber || !$accountName) {
            echoErrorAndExit(400, 'accountNumber, routingNumber and accountName are required');
        }
        if (!in_array($accountType, ['Savings', 'Checking'])) {
            echoErrorAndExit(400, 'accountType is invalid');
        }
        $tran->processACHPayment($accountNumber, $routingNumber, $accountType, $accountName);
    } elseif ($type == 'manual') {
        $cardNumber = preg_replace("/[^0-9]/", '', filter_input(INPUT_POST, 'cardNumber'));
        $cardExpire = preg_replace("/[^0-9]/", '', filter_input(INPUT_POST, 'cardExpire'));
        $cardCVV = preg_replace("/[^0-9]/", '', filter_input(INPUT_POST, 'cardCVV'));
        $cardZip = preg_replace("/[^0-9\-]/", '', filter_input(INPUT_POST, 'cardZip'));
        $cardStreet = filter_input(INPUT_POST, 'cardStreet');
        $cardHolder = filter_input(INPUT_POST, 'cardHolder');

        if (!$cardNumber || !$cardExpire || !$cardHolder) {
            echoErrorAndExit(400, 'cardNumber, cardExpire and cardHolder are required');
        }
        if (!CreditCard::validCreditCard($cardNumber)['valid']) {
            echoErrorAndExit(400, 'cardNumber is invalid');
        }
        $cardType = CreditCard::getCardBrand($cardNumber);

        if (!CreditCard::validCvc($cardCVV, strtolower($cardType)) && $cardCVV) {
            echoErrorAndExit(400, 'cardCVC is invalid');
        }
        if (!CreditCard::validDate($cardExpire)) {
            echoErrorAndExit(400, 'cardExpire is invalid');
        }
        $tran->processManualPayment($cardHolder, $cardNumber, $cardExpire, $cardCVV, $cardZip, $cardStreet);
    } elseif ($type == 'emv') {
        $deviceKey = filter_input(INPUT_POST, 'deviceKey');
        if (!$deviceKey) {
            echoErrorAndExit(400, 'deviceKey is required');
        }
        $emvProcessingID = filter_input(INPUT_POST, 'emvProcessingID', FILTER_SANITIZE_NUMBER_INT);
        if ($emvProcessingID) {
            $tran->setEMVProcessingID($emvProcessingID);
        }
        $tran->processEMVPayment($deviceKey);
    } elseif ($type == 'chip') {
        $deviceKey = filter_input(INPUT_POST, 'deviceKey');
        if (!$deviceKey) {
            echoErrorAndExit(400, 'deviceKey is required');
        }
        $tran->processChipPayment($deviceKey);
    } elseif ($type == 'token') {
        $city_token = filter_input(INPUT_POST, 'city_token');
        $fee_token = filter_input(INPUT_POST, 'fee_token');
        $gateway = filter_input(INPUT_POST, 'gateway');
        if (!$city_token || !$gateway) {
            echoErrorAndExit(400, 'token and gateway are required');
        }
        $tran->processTokenPayment($gateway, $city_token, $fee_token);
    } elseif ($type == 'quickSale' || $type == 'charge') {
        $transKey = filter_input(INPUT_POST, 'transKey');
        if (!$transKey) {
            echoErrorAndExit(400, 'transKey are required');
        }
        $tran->processQuickSalePayment($transKey);
    } elseif ($type == 'customer') {
        $customerID = filter_input(INPUT_POST, 'customerID');
        if (!$customerID) {
            echoErrorAndExit(400, 'customerID are required');
        }
        $cardID = filter_input(INPUT_POST, 'cardID');
        if (!$cardID) {
            echoErrorAndExit(400, 'cardID are required');
        }
        $tran->processCustomerPayment($customerID, $cardID);
        // $tran->processQuickSalePayment($transKey);
    } elseif ($type == 'vault') {
        $cityCardID = filter_input(INPUT_POST, 'cityCardID');
        $feeCardID = filter_input(INPUT_POST, 'feeCardID');
        if (!$cityCardID) {
            echoErrorAndExit(400, 'cardID are required');
        }
        $tran->processVaultPayment($cityCardID, $feeCardID);
    } elseif ($type == 'swipe') {
        $magStripe = filter_input(INPUT_POST, 'magStripe');
        $encMagStripe = filter_input(INPUT_POST, 'encMagStripe');
        if ($encMagStripe) {
            $magStripe = base64_decode($encMagStripe);
        }
        if (!$magStripe) {
            echoErrorAndExit(400, 'magStripe is required');
        }
        $magStripe = clean($magStripe);
        if ($magStripe == 'lintest') {
            $magStripe = '%B4251319550975389^LIU/MICHAEL W^25032010010000486000000?;4251319550975389=250320100100486?';
        }
        $tran->processSwipePayment($magStripe);
    } elseif ($type == 'cash') {
        $tran->processCashPayment();
    }
    $response = $tran->getResponse();
    if ($response['result'] == GPProcessResult::APPROVED) {
        if (isset($_POST['autoPay'])) {
            if ($tran->setAutoPayParam(filter_input(INPUT_POST, 'autoPayEmail'))) {
                $response['autoPay'] = true;
            }
        }
        if (isset($_POST['saveCard'])) {
            $helper = new Recurring_Helper;
            if ($type == 'ach') {
                $body = [
                    'routingNumber' => $routingNumber,
                    'accountName' => $accountName,
                    'accountNumber' => $accountNumber,
                    'accountType' => $accountType
                ];
            } else {
                $body = [
                    'cardNumber' => $cardNumber,
                    'cardHolder' => $cardHolder,
                    'cardExpire' => $cardExpire,
                    'cardStreet' => $cardStreet,
                    'cardZip' => $cardZip,
                    'cardCVV' => $cardCVV
                ];
            }
            $savedCard = $helper->createCustomerWithCard($portal, $_POST['customerName'], $_POST['customerEmail'], $body);
            $response['city_card_id'] = $savedCard['city_card_id'];
            $response['fee_card_id'] = $savedCard['fee_card_id'];
            if (isset($_POST['recurring_id'])) {
                $tran->updateAutoPayParam($_POST['recurring_id'], $savedCard['city_card_id'], $savedCard['fee_card_id']);
                $tran->voidAuth();
            }
        }
        // Set ReferenceNumber if post value
        if (isset($_POST['referenceNumber'])) {
            $tran->setReferenceNumber(filter_input(INPUT_POST, 'referenceNumber'));
        }
        if ($type == 'customer') {
            $db->insert('gp_customers_trans', [
                'trans_dt' => date('Y-m-d H:i:s'),
                'customer_id' => $customerID,
                'card_key' => $cardID,
                'trans_id' => $response['transactionID']
            ]);
        }
    } else {
        // Retry on network error
        $networkError = 'Recv failure: Connection reset by peer';
        if (substr(strtolower($response['error']), 0, strlen($networkError)) === strtolower($networkError) && $retry == false) {
            processPayment(true);
            return;
        }
        // Send Transaction Error Email
        $tran->sendPaymentErrorEmail();
    }
    postBackPartners($response);
    $response_json = json_encode($response);
    logAPIError($response_json);
    echo $response_json;
}

function postBackPartners($response)
{
    global $db;
    if (isset($_POST['scpdc_id'])) {
        $scpdc_id = $_POST['scpdc_id'];
        $scpdc_row = $db->where('id', $scpdc_id)->getOne('scpdc_tracking');
        if ($scpdc_row) {
            $params = json_decode($scpdc_row['params'], true);
            $params['result'] = $response['result'];
            if ($response['result'] == GPProcessResult::APPROVED) {
                unset($scpdc_row['id']);
                $scpdc_row['amount'] = $response['amount'];
                $scpdc_row['status'] = 3;
                $scpdc_row['detail_status'] = 'Payment Completed - ' . $scpdc_id;
                $scpdc_row['created_dt'] = date('Y-m-d H:i:s');
                $db->insert('scpdc_tracking', $scpdc_row);
                $params['paid_amount'] = $response['amount'];
                $params['transactionID'] = $response['transactionID'];
                $params['authCode'] = $response['authCode'];
                if ($params['approve_postback_url']) {
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $params['approve_postback_url'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_DNS_CACHE_TIMEOUT => 0,
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => $params,
                    ));
                    $response = curl_exec($curl);
                    if (curl_errno($curl)) {
                        $response = curl_error($curl);
                    }
                    curl_close($curl);
                    $db->insert('scpdc_postback_logs', [
                        'url' => $params['approve_postback_url'],
                        'type' => 'Approve',
                        'post_data' => json_encode($params),
                        'response' => $response,
                        'created_dt' => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                unset($scpdc_row['id']);
                $scpdc_row['status'] = 2;
                $scpdc_row['detail_status'] = 'Payment Error - ' . $response['error'];
                $scpdc_row['created_dt'] = date('Y-m-d H:i:s');
                $db->insert('scpdc_tracking', $scpdc_row);
                $params['error'] = $response['error'];
                if ($params['error_postback_url']) {
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $params['error_postback_url'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_DNS_CACHE_TIMEOUT => 0,
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => $params,
                    ));
                    $response = curl_exec($curl);
                    if (curl_errno($curl)) {
                        $response = curl_error($curl);
                    }
                    curl_close($curl);
                    $db->insert('scpdc_postback_logs', [
                        'url' => $params['error_postback_url'],
                        'type' => 'Error',
                        'post_data' => json_encode($params),
                        'response' => $response,
                        'created_dt' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
    }
}

function getTransactionStatus()
{
    global $db;
    if (!isset($_GET['tran_id'])) {
        echoErrorAndExit(400, 'tran_id is required param.');
    }
    $id = intval($_GET['tran_id']);
    // Get transaction row
    $tran_row = $db->where('GP_id', $id)->getOne('trans_all');
    if (!$tran_row) {
        echoErrorAndExit(400, 'wrong param.');
    }
    $transaction = new GP_VRTransaction($tran_row);
    $status = $transaction->get_transaction_status();
    $arr_status = [
        'P' => 'Pending',
        'S' => 'Settled',
        'E' => 'Error',
        'V' => 'Voided'
    ];
    if (array_key_exists($status, $arr_status)) {
        echo json_encode(['result' => 'Success', 'status' => $arr_status[$status], 'amount' => $transaction->amount]);
    } else {
        echo json_encode(['result' => 'Success', 'status' => 'Unknown Status', 'amount' => $transaction->amount]);
    }
}

function processVoidOrRefundPayment()
{
    global $db;
    if (!isset($_GET['command'])) {
        echoErrorAndExit(400, 'command is required param.');
    }
    if (!isset($_GET['reason'])) {
        echoErrorAndExit(400, 'reason is required param.');
    }
    $reason = $_GET['reason'];
    $command = $_GET['command'];
    if (!in_array($command, ['void', 'refund'])) {
        echoErrorAndExit(400, 'command is invalid.');
    }
    if ($command == 'refund') {
        if (!isset($_GET['amount'])) {
            echoErrorAndExit(400, 'amount is required param.');
        }
        $amount = $_GET['amount'];
    }
    if (!isset($_GET['tran_id'])) {
        echoErrorAndExit(400, 'tran_id is required param.');
    }
    $id = intval($_GET['tran_id']);
    // Get transaction row
    $tran_row = $db->where('GP_id', $id)->getOne('trans_all');
    if (!$tran_row) {
        echoErrorAndExit(400, 'wrong param.');
    }
    $transaction = new GP_VRTransaction($tran_row);
    $result = [];
    if ($command == 'void') {
        $result = $transaction->void_pending_transaction($reason);
    }
    if ($command == 'refund') {
        $result = $transaction->refund_settled_transaction($amount, $reason);
    }
    echo json_encode($result);
}

function processCapturePayment()
{
    global $db;
    if (!isset($_GET['amount'])) {
        echoErrorAndExit(400, 'amount is required param.');
    }
    $amount = floatval($_GET['amount']);
    // if ($amount < 0.1) {
    //     echoErrorAndExit(400, 'amount should be greater than 0.1 USD.');
    // }
    if (!isset($_GET['tran_id'])) {
        echoErrorAndExit(400, 'tran_id is required param.');
    }
    $id = intval($_GET['tran_id']);
    // Get transaction row
    $tran_row = $db->where('GP_id', $id)->where('Status', 'Authorized')->getOne('trans_all');
    if (!$tran_row) {
        echoErrorAndExit(400, 'wrong param.');
    }
    $transaction = new GP_VRTransaction($tran_row);
    if ($amount < 0.1) {
        $result = $transaction->capture_authorized_transaction_without_change();
    } else {
        $result = $transaction->capture_authorized_transaction($amount);
    }

    echo json_encode($result);
}
