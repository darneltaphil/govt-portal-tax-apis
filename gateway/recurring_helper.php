<?php
class Recurring_Helper
{
    public $portal_id, $portal, $customer_name, $customer_email, $card;
    const MX = 'MX';
    const USAEPAY = 'USA EPay';
    private $last_action, $last_response, $last_log_id;
    private $city_customer_id, $city_card_vault, $city_card_id;
    private $fee_customer_id, $fee_card_vault, $fee_card_id;
    private $is_card;
    public function createCustomerWithCard($portal, $customer_name, $customer_email, $card)
    {
        $result = [
            'result' => 'Error',
            'error' => '',
            'city_card_id' => 0,
            'fee_card_id' => 0
        ];
        $this->portal_id = $portal['Portal_Id'];
        $this->portal = $portal;
        $this->customer_name = $customer_name;
        $this->customer_email = $customer_email;
        $this->card = $card;
        $this->is_card = isset($card['cardNumber']);
        $this->city_customer_id = $this->_createCityCustomer();
        $this->_addLog();
        if ($this->city_customer_id == false) {
            $result['error'] = 'Create City Customer Failed (' . $this->last_log_id . ')';
            return $result;
        }
        $this->city_card_vault = $this->_createCityCardVault();
        $this->_addLog();
        if ($this->city_card_vault == false) {
            $this->_removeCityCustomer();
            $result['error'] = 'Create City Card Vault Failed (' . $this->last_log_id . ')';
            return $result;
        }
        $this->_addCityEntry();
        $result['result'] = 'Success';
        $result['city_card_id'] = $this->city_card_id;
        $gateway = $this->portal['Gateway'];
        if (($gateway == self::MX && $portal['fee_mxid']) || ($gateway == self::USAEPAY && $portal['Service_Fee_Source_Key'])) {
            $this->fee_customer_id = $this->_createFeeCustomer();
            $this->_addLog();
            if ($this->fee_customer_id == false) {
                // $result['error'] = 'Create Fee Customer Failed (' . $this->last_log_id . ')';
                return $result;
            }
            $this->fee_card_vault = $this->_createFeeCardVault();
            $this->_addLog();
            if ($this->fee_card_vault == false) {
                $this->_removeFeeCustomer();
                // $result['error'] = 'Create Fee Card Vault Failed (' . $this->last_log_id . ')';
                return $result;
            }
            $this->_addFeeEntry();
            $result['fee_card_id'] = $this->fee_card_id;
        }
        return $result;
    }

    private function _addLog()
    {
        global $db;
        $this->last_log_id = $db->insert('gp_gateway_autopay_logs', [
            'log_dt' => date('Y-m-d H:i:s'),
            'action' => $this->last_action,
            'response' => $this->last_response
        ]);
    }
    private function _createFeeCustomer()
    {
        $gateway = $this->portal['Gateway'];
        $this->last_action = sprintf('Create Fee Customer %s(%s)', $this->portal_id, $gateway);
        if ($gateway == self::MX) {
            return $this->_createMXCustomer(
                $this->is_card ? $this->portal['fee_mxid'] : $this->portal['ach_fee_merchant_id'],
                $this->is_card ? $this->portal['mx_gp_fee_user'] : $this->portal['ach_fee_merchant_key'],
                $this->is_card ? $this->portal['mx_gp_fee_pass'] : $this->portal['ach_fee_merchant_secret'],
                $this->customer_name,
                $this->customer_email
            );
        } elseif ($gateway == self::USAEPAY) {
            return $this->_createUSAEPayCustomer(
                $this->is_card ? $this->portal['Service_Fee_Source_Key'] : $this->portal['ach_fee_merchant_key'],
                '8888',
                $this->customer_name,
                $this->customer_email
            );
        }
        return false;
    }
    private function _removeFeeCustomer()
    {
        // TODO
    }
    private function _addFeeEntry()
    {
        global $db;
        $gateway = $this->portal['Gateway'];
        if ($gateway == self::MX) {
            $db->insert('gp_gateway_autopay_accounts', [
                'gateway' => $gateway,
                'merchant_key' => $this->is_card ? $this->portal['fee_mxid'] : $this->portal['ach_fee_merchant_id'],
                'customer_id' => $this->fee_customer_id,
                'created_dt' => date('Y-m-d H:i:s')
            ]);
        } elseif ($gateway == self::USAEPAY) {
            $db->insert('gp_gateway_autopay_accounts', [
                'gateway' => $gateway,
                'merchant_key' => $this->is_card ? $this->portal['Service_Fee_Source_Key'] : $this->portal['ach_fee_merchant_key'],
                'customer_id' => $this->fee_customer_id,
                'created_dt' => date('Y-m-d H:i:s')
            ]);
        }
        $this->fee_card_id = $db->insert('gp_gateway_autopay_card_tokens', [
            'account_id' => $this->fee_customer_id,
            'card_id' => $this->fee_card_vault['card_id'],
            'card_last4' => $this->fee_card_vault['last4'],
            'card_type' => $this->fee_card_vault['card_type'],
            'card_token' => $this->fee_card_vault['token'],
            'created_dt' => date('Y-m-d H:i:s'),
            'updated_dt' => date('Y-m-d H:i:s'),
        ]);
    }
    private function _createCityCustomer()
    {
        $gateway = $this->portal['Gateway'];
        $this->last_action = sprintf('Create City Customer %s(%s)', $this->portal_id, $gateway);
        if ($gateway == self::MX) {
            return $this->_createMXCustomer(
                $this->portal['mxid'],
                $this->portal['GatewayUser_Login'],
                $this->portal['Gateway_User_Password'],
                $this->customer_name,
                $this->customer_email
            );
        } elseif ($gateway == self::USAEPAY) {
            return $this->_createUSAEPayCustomer(
                $this->portal['source_olp'],
                '8888',
                $this->customer_name,
                $this->customer_email
            );
        }
        return false;
    }
    private function _removeCityCustomer()
    {
        // TODO
    }
    private function _addCityEntry()
    {
        global $db;
        $gateway = $this->portal['Gateway'];
        if ($gateway == self::MX) {
            $db->insert('gp_gateway_autopay_accounts', [
                'gateway' => $gateway,
                'merchant_key' => $this->portal['mxid'],
                'customer_id' => $this->city_customer_id,
                'created_dt' => date('Y-m-d H:i:s')
            ]);
        } elseif ($gateway == self::USAEPAY) {
            $db->insert('gp_gateway_autopay_accounts', [
                'gateway' => $gateway,
                'merchant_key' => $this->portal['source_olp'],
                'customer_id' => $this->city_customer_id,
                'created_dt' => date('Y-m-d H:i:s')
            ]);
        }
        $this->city_card_id = $db->insert('gp_gateway_autopay_card_tokens', [
            'account_id' => $this->city_customer_id,
            'card_id' => $this->city_card_vault['card_id'],
            'card_last4' => $this->city_card_vault['last4'],
            'card_type' => $this->city_card_vault['card_type'],
            'card_token' => $this->city_card_vault['token'],
            'created_dt' => date('Y-m-d H:i:s'),
            'updated_dt' => date('Y-m-d H:i:s'),
        ]);
    }
    private function _createMXCustomer($merchantId, $merchantKey, $merchantSecret, $name, $email)
    {
        $customerID = false;
        $body = [
            'merchantId' => $merchantId,
            'name' => $name,
            'email' => $email
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
            $this->last_response = $response;
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'id')) {
                    $customerID = $response_obj->id;
                }
            }
        } else {
            $this->last_response = curl_error($curl);
        }
        curl_close($curl);
        return $customerID;
    }
    private function _createUSAEPayCustomer($apiKey, $apiPin, $name, $email)
    {
        $customerID = false;
        $data = [
            'first_name' => 'Recurring',
            'last_name' => $name,
            'email' => $email,
            'description' => 'by GP',
            'country' => 'USA',
            'customerid' => uniqid('g')
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
        if (!curl_errno($curl)) {
            $this->last_response = $response;
            $response_obj = json_decode($response);
            if (property_exists($response_obj, 'key')) {
                $customerID = $response_obj->key;
            }
        } else {
            $this->last_response = curl_error($curl);
        }
        curl_close($curl);
        return $customerID;
    }
    private function _createFeeCardVault()
    {
        $gateway = $this->portal['Gateway'];
        $this->last_action = sprintf('Create Fee Card Vault %s(%s)', $this->portal_id, $gateway);
        if ($gateway == self::MX) {
            return $this->_createMXCardVault(
                $this->fee_customer_id,
                $this->is_card ? $this->portal['mx_gp_fee_user'] : $this->portal['ach_fee_merchant_key'],
                $this->is_card ? $this->portal['mx_gp_fee_pass'] : $this->portal['ach_fee_merchant_secret'],
                $this->card
            );
        } elseif ($gateway == self::USAEPAY) {
            return $this->_createUSAEPayCardVault(
                $this->fee_customer_id,
                $this->is_card ? $this->portal['Service_Fee_Source_Key'] : $this->portal['ach_fee_merchant_key'],
                '8888',
                $this->card
            );
        }
        return false;
    }
    private function _createCityCardVault()
    {
        $gateway = $this->portal['Gateway'];
        $this->last_action = sprintf('Create City Card Vault %s(%s)', $this->portal_id, $gateway);
        if ($gateway == self::MX) {
            return $this->_createMXCardVault(
                $this->city_customer_id,
                $this->portal['GatewayUser_Login'],
                $this->portal['Gateway_User_Password'],
                $this->card
            );
        } elseif ($gateway == self::USAEPAY) {
            return $this->_createUSAEPayCardVault(
                $this->city_customer_id,
                $this->portal['source_olp'],
                '8888',
                $this->card
            );
        }
        return false;
    }
    private function _createUSAEPayCardVault($customerKey, $apiKey, $apiPin, $card)
    {
        $result = false;
        if ($this->is_card) {
            $body = [
                'method_name' => 'Recurring Card',
                'number' => $card['cardNumber'],
                'cardholder' => $card['cardHolder'],
                'expiration' => $card['cardExpire'],
                'pay_type' => 'cc'
            ];
            foreach (['cardStreet' => 'avs_street', 'cardZip' => 'avs_postalcode', 'cardCVV' => 'cvc'] as $entry => $value) {
                if (isset($card[$entry])) {
                    $body[$value] = $card[$entry];
                }
            }
        } else {
            $body = [
                'method_name' => 'Recurring ACH',
                'cardholder' => $card['accountName'],
                'routing' => $card['routingNumber'],
                'account' => $card['accountNumber'],
                'account_type' => $card['accountType'],
                'pay_type' => 'check'
            ];
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
        if (!curl_errno($curl)) {
            $response_obj = json_decode($response);
            if (is_array($response_obj)) {
                $response_obj = $response_obj[0];
                if (property_exists($response_obj, 'key')) {
                    if ($this->is_card) {
                        $result = [
                            'card_id' => $response_obj->key,
                            'token' => $response_obj->key,
                            'last4' => $response_obj->ccnum4last,
                            'card_type' => $response_obj->card_type
                        ];
                    } else {
                        $result = [
                            'card_id' => $response_obj->key,
                            'token' => $response_obj->key,
                            'last4' => substr($card['accountNumber'], -4),
                            'card_type' => 'ACH'
                        ];
                    }
                }
            }
        } else {
            $this->last_response = curl_error($curl);
        }
        curl_close($curl);
        return $result;
    }
    private function _createMXCardVault($customerID, $merchantKey, $merchantSecret, $card)
    {
        $result = false;
        if ($this->is_card) {
            $url = "https://api.mxmerchant.com/checkout/v3/customercardaccount?echo=true&id={$customerID}";
            $body = [
                'number' => $card['cardNumber'],
                'name' => $card['cardHolder'],
                'expiryMonth' => substr($card['cardExpire'], 0, 2),
                'expiryYear' => substr($card['cardExpire'], -2)
            ];
            foreach (['cardStreet' => 'avsStreet', 'cardZip' => 'avsZip', 'cardCVV' => 'cvv'] as $entry => $value) {
                if (isset($card[$entry])) {
                    $body[$value] = $card[$entry];
                }
            }
        } else {
            $url = "https://api.mxmerchant.com/checkout/v3/customerbankaccount?echo=true&id={$customerID}";
            $body = [
                'accountNumber' => $card['accountNumber'],
                'routingNumber' => $card['routingNumber'],
                'name' => $card['accountName'],
                'type' => $card['accountType']
            ];
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
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
            $this->last_response = $response;
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'token')) {
                    if ($this->is_card) {
                        $result = [
                            'card_id' => $response_obj->id,
                            'token' => $response_obj->token,
                            'last4' => $response_obj->last4,
                            'card_type' => $response_obj->cardType
                        ];
                    } else {
                        $result = [
                            'card_id' => $response_obj->id,
                            'token' => $response_obj->token,
                            'last4' => substr($card['accountNumber'], -4),
                            'card_type' => 'ACH'
                        ];
                    }
                }
            }
        } else {
            $this->last_response = curl_error($curl);
        }
        curl_close($curl);
        return $result;
    }
}
