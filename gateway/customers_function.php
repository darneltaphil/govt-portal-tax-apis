<?php
function createCustomer()
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
    $data = ['description' => 'by GP', 'country' => 'USA', 'customerid' => uniqid('g')];
    foreach (['company', 'first_name', 'last_name', 'street', 'street2', 'city', 'state', 'postalcode', 'phone', 'email', 'notes'] as $key) {
        $value = trim(filter_input(INPUT_POST, $key));
        if ($value) {
            $data[$key] = $value;
        }
    }
    if (!((isset($data['first_name']) && isset($data['last_name'])) || isset($data['company']))) {
        echoErrorAndExit(400, 'Company or customer name is required param.');
    }
    if ($portal['Gateway'] != 'USA EPay') {
        echoErrorAndExit(500, 'Unsupported gateway.');
    }
    // Create City Customer
    $cityCustomer = createUSAEPayCustomer($data, $portal['source_olp'], 8888);
    // Create Fee Customer
    if ($portal['Service_Fee_Source_Key']) {
        $feeCustomer = createUSAEPayCustomer($data, $portal['Service_Fee_Source_Key'], 8888);
        $db->insert('gp_customers', [
            'entity' => $portal['Entity'],
            'created_dt' => date('Y-m-d H:i:s'),
            'portal_id' => $portalID,
            'api_key' => $portal['Service_Fee_Source_Key'],
            'api_secret' => '8888',
            'customer_id' => $data['customerid'],
            'customer_key' => $feeCustomer,
            'gateway' => $portal['Gateway']
        ]);
    }
    $db->insert('gp_customers', [
        'entity' => $portal['Entity'],
        'created_dt' => date('Y-m-d H:i:s'),
        'portal_id' => $portalID,
        'api_key' => $portal['source_olp'],
        'api_secret' => '8888',
        'customer_id' => $data['customerid'],
        'customer_key' => $cityCustomer,
        'gateway' => $portal['Gateway']
    ]);
    echo json_encode(['result' => 'Success', 'customerKey' => $data['customerid']]);
}
function createUSAEPayCustomer($data, $apiKey, $apiPin)
{
    $seed = rand() . time();
    $preHash = $apiKey . $seed . $apiPin;
    $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://secure.usaepay.com/api/v2/customers",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Cache-Control: no-cache",
        ),
        CURLOPT_USERPWD => $apiKey . ':' . $apiHash
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    if ($response == '') {
        echoErrorAndExit(400, 'Curl error.');
    }
    $response_obj = json_decode($response);
    if (property_exists($response_obj, 'error')) {
        echoErrorAndExit(400, $response_obj->error);
    }
    if (!property_exists($response_obj, 'key')) {
        echoErrorAndExit(400, $response);
    }
    return $response_obj->key;
}
