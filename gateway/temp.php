<?php
require_once 'functions.php';
$db = new MysqliDb(getenv('DB_SERVER'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
function doit()
{
    global $db;
    $rows = $db->where('(city_card_id = 0 OR fee_card_id = 0)')->where('custom_data', '')->orderBy('id')->get('recurring_customers', 500, null);
    $portals = [];
    $nowObj = new DateTime();
    foreach ($rows as $row) {
        $portal_id = $row['portal_id'];
        $portal = null;
        $note = 'Complete';
        if (!in_array($portal_id, $portals)) {
            $portal = $db->where('Portal_Id', $portal_id)->getOne('zoho_products');
            if ($portal) {
                $portals[$portal_id] = $portal;
            } else {
                $note = 'No existing portal';
            }
        } else {
            $portal = $portals[$portal_id];
        }
        if ($portal) {
            $gateway = $portal['Gateway'];
            if ($gateway == 'MX') {
                $city_key = $portal['mxid'];
                $fee_key = $portal['fee_mxid'];
            } else if ($gateway == 'USA EPay') {
                $city_key = $portal['source_olp'];
                $fee_key = $portal['Service_Fee_Source_Key'];
            }

            $card = unserialize(GPCrypto::decrypt($row['card_token']));
            // check card exp
            $expDateObj = DateTime::createFromFormat('my', $card['CardExpiration']);
            if ($expDateObj > $nowObj) {
                if ($row['city_card_id'] == 0 && $city_key) {
                    // process city
                    if ($gateway == 'MX') {
                        $customer_id = createMXCustomer($city_key, $portal['GatewayUser_Login'], $portal['Gateway_User_Password'], 'Redline-' . $row['id']);
                    } else {
                        $customer_id = createUSAEPayCustomer('Redline-' . $row['id'], $portal['source_olp'], '8888');
                    }

                    if ($customer_id) {
                        $db->insert('gp_gateway_autopay_accounts', [
                            'gateway' => $gateway,
                            'merchant_key' => $city_key,
                            'customer_id' => $customer_id,
                            'created_dt' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $note = 'Create customer failed';
                    }
                    if ($customer_id) {
                        if ($gateway == 'MX') {
                            $card_obj = createMXCardVault($customer_id, $portal['GatewayUser_Login'], $portal['Gateway_User_Password'], $card);
                        } else {
                            $card_obj = createUSAEPayCardVault($customer_id, $portal['source_olp'], '8888', $card);
                        }

                        if ($card_obj) {
                            $city_card_id = $db->insert('gp_gateway_autopay_card_tokens', [
                                'account_id' => $customer_id,
                                'card_id' => $card_obj['card_id'],
                                'card_last4' => $card_obj['last4'],
                                'card_type' => $card_obj['card_type'],
                                'card_token' => $card_obj['token'],
                                'created_dt' => date('Y-m-d H:i:s'),
                                'updated_dt' => date('Y-m-d H:i:s'),
                            ]);
                            $db->where('id', $row['id'])->update('recurring_customers', ['city_card_id' => $city_card_id]);
                        } else {
                            $note = 'Create card vault failed';
                        }
                    }
                }
                if ($row['fee_card_id'] == 0 && $fee_key) {
                    if ($gateway == 'MX') {
                        $customer_id = createMXCustomer($fee_key, $portal['mx_gp_fee_user'], $portal['mx_gp_fee_pass'], 'Redline-Fee-' . $row['id']);
                    } else {
                        $customer_id = createUSAEPayCustomer('Redline-Fee-' . $row['id'], $portal['Service_Fee_Source_Key'], '8888');
                    }

                    if ($customer_id) {
                        $db->insert('gp_gateway_autopay_accounts', [
                            'gateway' => $gateway,
                            'merchant_key' => $fee_key,
                            'customer_id' => $customer_id,
                            'created_dt' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $note = 'Create fee customer failed';
                    }
                    if ($customer_id) {
                        if ($gateway == 'MX') {
                            $card_obj = createMXCardVault($customer_id, $portal['mx_gp_fee_user'], $portal['mx_gp_fee_pass'], $card);
                        } else {
                            $card_obj = createUSAEPayCardVault($customer_id, $portal['Service_Fee_Source_Key'], '8888', $card);
                        }

                        if ($card_obj) {
                            $fee_card_id = $db->insert('gp_gateway_autopay_card_tokens', [
                                'account_id' => $customer_id,
                                'card_id' => $card_obj['card_id'],
                                'card_last4' => $card_obj['last4'],
                                'card_type' => $card_obj['card_type'],
                                'card_token' => $card_obj['token'],
                                'created_dt' => date('Y-m-d H:i:s'),
                                'updated_dt' => date('Y-m-d H:i:s'),
                            ]);
                            $db->where('id', $row['id'])->update('recurring_customers', ['fee_card_id' => $fee_card_id]);
                        } else {
                            $note = 'Create fee card vault failed';
                        }
                    }
                }
            } else {
                $note = 'Expired Card';
            }
        }
        if ($note) {
            $db->where('id', $row['id'])->update('recurring_customers', ['custom_data' => $note]);
        }
        echo $note, PHP_EOL;
        sleep(6);
    }
}
function createMXCardVault($customerID, $merchantKey, $merchantSecret, $card)
{
    $result = false;
    $body = [
        'number' => $card['CardNumber'],
        'name' => $card['CardHolder'],
        'expiryMonth' => substr($card['CardExpiration'], 0, 2),
        'expiryYear' => substr($card['CardExpiration'], -2)
    ];
    foreach (['AvsStreet' => 'avsStreet', 'AvsZip' => 'avsZip', 'CardCode' => 'cvv'] as $entry => $value) {
        if (isset($card[$entry])) {
            $body[$value] = $card[$entry];
        }
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/customercardaccount?echo=true&id={$customerID}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_DNS_CACHE_TIMEOUT => 0,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Cache-Control: no-cache",
        ),
        CURLOPT_USERPWD => $merchantKey . ':' . $merchantSecret
    ));
    $response = curl_exec($curl);
    if (!curl_errno($curl)) {
        $response_obj = json_decode($response);
        if (is_object($response_obj)) {
            if (property_exists($response_obj, 'token')) {
                $result = [
                    'card_id' => $response_obj->id,
                    'token' => $response_obj->token,
                    'last4' => $response_obj->last4,
                    'card_type' => $response_obj->cardType
                ];
            }
        }
    }
    curl_close($curl);
    return $result;
}
function createMXCustomer($merchantId, $merchantKey, $merchantSecret, $lastName)
{
    $customerID = false;
    $body = [
        'merchantId' => $merchantId,
        'firstName' => 'Recurring',
        'lastName' => $lastName
    ];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/customer?echo=true",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_DNS_CACHE_TIMEOUT => 0,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Cache-Control: no-cache",
        ),
        CURLOPT_USERPWD => $merchantKey . ':' . $merchantSecret
    ));
    $response = curl_exec($curl);
    if (!curl_errno($curl)) {
        $response_obj = json_decode($response);
        if (is_object($response_obj)) {
            if (property_exists($response_obj, 'id')) {
                $customerID = $response_obj->id;
            }
        }
    }
    curl_close($curl);
    return $customerID;
}
function createUSAEPayCustomer($lastName, $apiKey, $apiPin)
{
    $data = [
        'first_name' => 'Recurring',
        'last_name' => $lastName,
        'description' => 'by GP', 'country' => 'USA', 'customerid' => uniqid('g')
    ];
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
        return false;
    }
    $response_obj = json_decode($response);
    if (property_exists($response_obj, 'error')) {
        return false;
    }
    if (!property_exists($response_obj, 'key')) {
        return false;
    }
    return $response_obj->key;
}
function createUSAEPayCardVault($customerKey, $apiKey, $apiPin, $card)
{
    $result = false;
    $body = [
        'method_name' => 'Recurring Card',
        'number' => $card['CardNumber'],
        'cardholder' => $card['CardHolder'],
        'expiration' => $card['CardExpiration'],
        'pay_type' => 'cc'
    ];
    foreach (['AvsStreet' => 'avs_street', 'AvsZip' => 'avs_postalcode', 'CardCode' => 'cvc'] as $entry => $value) {
        if (isset($card[$entry])) {
            $body[$value] = $card[$entry];
        }
    }
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
        CURLOPT_POSTFIELDS => json_encode([$body]),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Cache-Control: no-cache",
        ),
        CURLOPT_USERPWD => $apiKey . ':' . $apiHash
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    if ($response == '') {
        return false;
    }
    $response_obj = json_decode($response);
    if (!is_array($response_obj)) {
        return false;
    }
    $response_obj = $response_obj[0];
    if (property_exists($response_obj, 'error')) {
        return false;
    }
    if (!property_exists($response_obj, 'key')) {
        return false;
    }
    $result = [
        'card_id' => $response_obj->key,
        'token' => $response_obj->key,
        'last4' => $response_obj->ccnum4last,
        'card_type' => $response_obj->card_type
    ];
    return $result;
}
doit();

class GPCrypto
{
    const METHOD = 'aes-256-ctr';
    const KEY = '136d3f73f5ae98b6daf6a53f72840d160d0895b397c266544560c368ba783f2b';

    /**
     * Encrypts (but does not authenticate) a message
     * 
     * @param string $message - plaintext message
     * @param string $key - encryption key (raw binary expected)
     * @param boolean $encode - set to TRUE to return a base64-encoded 
     * @return string (raw binary)
     */
    public static function encrypt($message)
    {
        $nonceSize = openssl_cipher_iv_length(self::METHOD);
        $nonce = openssl_random_pseudo_bytes($nonceSize);

        $ciphertext = openssl_encrypt(
            $message,
            self::METHOD,
            hex2bin(self::KEY),
            OPENSSL_RAW_DATA,
            $nonce
        );

        // Now let's pack the IV and the ciphertext together
        // Naively, we can just concatenate

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypts (but does not verify) a message
     * 
     * @param string $message - ciphertext message
     * @param string $key - encryption key (raw binary expected)
     * @param boolean $encoded - are we expecting an encoded string?
     * @return string
     */
    public static function decrypt($message)
    {
        $message = base64_decode($message, true);
        if ($message === false) {
            throw new Exception('Encryption failure');
        }
        $nonceSize = openssl_cipher_iv_length(self::METHOD);
        $nonce = mb_substr($message, 0, $nonceSize, '8bit');
        $ciphertext = mb_substr($message, $nonceSize, null, '8bit');
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::METHOD,
            hex2bin(self::KEY),
            OPENSSL_RAW_DATA,
            $nonce
        );
        return $plaintext;
    }
}
