<?php
function addCard()
{
    global $db;
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

    $customerKey = filter_input(INPUT_POST, 'customerKey');
    if (!$customerKey) {
        echoErrorAndExit(400, 'Customer key is required param.');
    }

    $cardType = filter_input(INPUT_POST, 'cardType');
    if (!$cardType) {
        echoErrorAndExit(400, 'Card type is required param.');
    }

    if (!in_array($cardType, ['cc', 'transaction'])) {
        echoErrorAndExit(400, 'Card type should be cc or transaction.');
    }

    $customers = $db->where('portal_id', $portalID)->where('customer_id', $customerKey)->where('gateway', 'USA EPay')->get('gp_customers');

    if (!$customers) {
        echoErrorAndExit(400, 'Wrong customer key.');
    }

    $data = [
        'method_name' => 'Govtportal',
        'default' => true,
        'pay_type' => $cardType
    ];
    if ($cardType == 'cc') {
        foreach (['number', 'expiration', 'avs_street', 'avs_postalcode'] as $key) {
            $value = trim(filter_input(INPUT_POST, $key));
            if ($value) {
                $data[$key] = $value;
            }
        }
        if (!isset($data['number']) || !isset($data['expiration'])) {
            echoErrorAndExit(400, 'Card number and expiration are required params.');
        }
        $data['number'] = preg_replace('/[^0-9]/', '', $data['number']);
        $data['expiration'] = preg_replace('/[^0-9]/', '', $data['expiration']);
        if (!CreditCard::validCreditCard($data['number'])['valid']) {
            echoErrorAndExit(400, 'Card number is invalid.');
        }
        if (!CreditCard::validDate($data['expiration'])) {
            echoErrorAndExit(400, 'Card expiration is invalid.');
        }
    } elseif ($cardType == 'transaction') {
        $transactionID = filter_input(INPUT_POST, 'transactionID');
        if (!$transactionID) {
            echoErrorAndExit(400, 'Transaction ID is required param.');
        }
        $trans = $db->where('tran_id', $transactionID)->get('gp_trans');
        if (!$trans) {
            echoErrorAndExit(400, 'Transaction ID is wrong value.');
        }
        // Check if has same api key 
        $matchCount = 0;
        foreach ($customers as $index => $customer) {
            foreach ($trans as $tran) {
                if ($customer['api_key'] == $tran['merchant_key'] && $customer['api_secret'] == $tran['merchant_secret']) {
                    $customers[$index]['transaction_key'] = $tran['merchant_tran_key'];
                    $matchCount++;
                    break;
                }
            }
        }
        if ($matchCount == 0) {
            echoErrorAndExit(400, 'Wrong transaction ID.');
        }
        $customers = array_filter($customers, function ($v) {
            return isset($v['transaction_key']);
        });
    }



    $cards = [];
    foreach ($customers as $customer) {
        if ($cardType == 'transaction') {
            $data['transaction_key'] = $customer['transaction_key'];
        }
        $cards[$customer['id']] = addUSAEPayCard($data, $customer['api_key'], $customer['api_secret'], $customer['customer_key']);
        $cards[$customer['id']]->customer_id = $customer['id'];
    }
    $cardKey = uniqid('cc');
    foreach ($cards as $card) {
        $db->insert('gp_customer_methods', [
            'customer_id' => $card->customer_id,
            'card_key' => $cardKey,
            'method_key' => $card->key,
            'card_type' => $card->card_type,
            'card_last4' => $card->ccnum4last,
            'pay_type' => $card->pay_type
        ]);
    }
    echo json_encode(['result' => 'Success', 'cardKey' => $cardKey]);
}
function addUSAEPayCard($data, $apiKey, $apiPin, $customerKey)
{
    $seed = rand() . time();
    $preHash = $apiKey . $seed . $apiPin;
    $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://secure.usaepay.com/api/v2/customers/{$customerKey}/payment_methods",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([$data]),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Cache-Control: no-cache",
        ),
        CURLOPT_USERPWD => $apiKey . ':' . $apiHash
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    // echo $response;
    if ($response == '') {
        echoErrorAndExit(400, 'Curl error.');
    }
    $response_obj = json_decode($response);
    if (!is_array($response_obj)) {
        if (property_exists($response_obj, 'error')) {
            echoErrorAndExit(400, $response_obj->error);
        }
        echoErrorAndExit(400, $response);
    }
    $response_obj = $response_obj[0];
    if (!property_exists($response_obj, 'key')) {
        echoErrorAndExit(400, $response);
    }
    return $response_obj;
}
