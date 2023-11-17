<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
set_time_limit(3600);

function echoErrorAndExit($status, $error)
{
    if ($status == 401) {
        header('WWW-Authenticate: Basic api_key=secret');
    }
    http_response_code($status);
    echo json_encode([
        "result" => 'Error',
        "error" => $error
    ]);
    //logAPIError($error);
    exit;
}

// function logAPIError($error)
// {
//     global $db;
//     if (isset($_POST['cardCVV'])) {
//         unset($_POST['cardCVV']);
//     }
//     if (isset($_POST['cardNumber'])) {
//         unset($_POST['cardNumber']);
//     }
//     if (isset($_POST['magStripe'])) {
//         unset($_POST['magStripe']);
//     }
//     global $db;
//     $db->insert('api_error_logs', [
//         'post_time' => date('Y-m-d H:i:s'),
//         'get_param' => json_encode($_GET),
//         'post_param' => json_encode($_POST),
//         'message' => $error
//     ]);
// }

// function getOption($entity, $key, $default = false)
// {
//     global $db;
//     $value = $db->where('entity', $entity)->where('option_key', $key)->getValue('gp_options', 'option_value');
//     if ($value === NULL) {
//         return $default;
//     }
//     if (_isJson($value)) {
//         return json_decode($value);
//     }
//     return stripslashes($value);
// }
// function _isJson($string)
// {
//     json_decode($string);
//     return (json_last_error() == JSON_ERROR_NONE);
// }

// function clean($string)
// {
//     return preg_replace('/[^A-Za-z0-9\-\%\?\^\s\;\/\=]/', '', $string); // Removes special chars.
// }

// function getSafeProperty($obj, $property, $default = NULL)
// {
//     if (!is_object($obj)) {
//         return $default;
//     }
//     if (property_exists($obj, $property)) {
//         return $obj->{$property};
//     }
//     return $default;
// }

// function formatAmount($amount)
// {
//     $v = floatval($amount);
//     if ($v < 0) {
//         return '-$' . number_format(abs($v), 2);
//     } else {
//         return '$' . number_format($v, 2);
//     }
// }

// function getZohoEBTFAccessToken()
// {
//     global $db;
//     $row = $db->where('id', getenv('ZOHO_EBTF_ACCESS_TOKEN_ID'))->getOne('gp_options');
//     $token = $row['option_value'];
//     $now = new DateTime();
//     $tokenTime = DateTime::createFromFormat('Y-m-d H:i:s', $row['update_time']);
//     $seconds = $now->getTimestamp() - $tokenTime->getTimestamp();
//     if ($seconds > 3000) {
//         // Generate Access Token
//         $curl = curl_init();

//         curl_setopt_array($curl, array(
//             CURLOPT_URL => 'https://accounts.zoho.com/oauth/v2/token?' . http_build_query([
//                 'grant_type' => 'refresh_token',
//                 'client_id' => getenv('ZOHO_EBTF_CLIENT_ID'),
//                 'client_secret' => getenv('ZOHO_EBTF_CLIENT_SECRET'),
//                 'refresh_token' => getenv('ZOHO_EBTF_REFRESH_TOKEN')
//             ]),
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_ENCODING => '',
//             CURLOPT_MAXREDIRS => 10,
//             CURLOPT_TIMEOUT => 0,
//             CURLOPT_FOLLOWLOCATION => true,
//             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//             CURLOPT_CUSTOMREQUEST => 'POST'
//         ));

//         $response = curl_exec($curl);

//         curl_close($curl);
//         $responseObj = json_decode($response);
//         $token = $responseObj->access_token;
//         $db->where('id', getenv('ZOHO_EBTF_ACCESS_TOKEN_ID'))->update('gp_options', [
//             'option_value' => $token,
//             'update_time' => $now->format('Y-m-d H:i:s')
//         ]);
//     }
//     return $token;
// }
// function postZohoEBTFInvoicePaymentNotification($invoiceNumber, $invoiceId, $customerId, $amount, $transactionDetailUrl)
// {
//     global $db;
//     $token = getZohoEBTFAccessToken();
//     $body = json_encode([
//         "customer_id" => $customerId,
//         "payment_mode" => "creditcard",
//         "amount" => $amount,
//         "date" => date('Y-m-d'),
//         "reference_number" => $invoiceNumber,
//         "description" => "Payment has been added to " . $invoiceNumber . " by IPP " . $transactionDetailUrl,
//         "invoices" => [
//             [
//                 "invoice_id" => $invoiceId,
//                 "amount_applied" => $amount
//             ]
//         ],
//         "invoice_id" => $invoiceId,
//         "amount_applied" => $amount,
//     ]);
//     $curl = curl_init();

//     curl_setopt_array($curl, array(
//         CURLOPT_URL => 'https://books.zoho.com/api/v3/customerpayments?organization_id=' . getenv('ZOHO_EBTF_ORG_ID'),
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_ENCODING => '',
//         CURLOPT_MAXREDIRS => 10,
//         CURLOPT_TIMEOUT => 0,
//         CURLOPT_FOLLOWLOCATION => true,
//         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//         CURLOPT_CUSTOMREQUEST => 'POST',
//         CURLOPT_POSTFIELDS => $body,
//         CURLOPT_HTTPHEADER => array(
//             'Authorization: Zoho-oauthtoken ' . $token,
//             'Content-Type: application/json;charset=UTF-8'
//         ),
//     ));

//     $response = curl_exec($curl);

//     curl_close($curl);
//     $db->insert('zoho_api_logs', [
//         'action' => 'Create Customer Payment with Invoice',
//         'action_dt' => date('Y-m-d H:i:s'),
//         'action_body' => $body,
//         'action_response' => $response
//     ]);
// }
