<?php
require_once 'card_lib.php';
class GPTransaction
{
    public $portalID, $portal, $forceFee, $forceFeeAmount;
    public $user;
    public $amount;
    private $entity, $portalName, $gateway;
    private $achAccountNumber, $achRoutingNumber, $achAccountName, $achAccountType;
    private $cardHolder, $cardNumber, $cardExpire, $cardCVV, $cardZip, $cardStreet, $cardType, $magStripe;
    private $payType, $varPartner, $department, $absorbFee, $mergeFee, $ori, $hasFlatFee, $flatFeeModel, $flatFeeAmount, $flatFeeLabel, $shouldAutoAccept;
    private $feeKey, $feePin, $cityKey, $cityPin, $feePercent, $feeMinimum, $amexFeePercent, $amexFeeMinimum, $feeMerchant, $cityMerchant, $cityTransKey, $feeTransKey;
    private $cpCityCNPSite, $cpCityEMVSite, $cpFeeCNPSite, $cpFeeEMVSite, $cpFlatCNPSite, $cpFlatEMVSite, $cpCardToken;
    private $cityCustomerKey, $cityCustomerMethodKey, $feeCustomerKey, $feeCustomerMethodKey;
    private $customerKey, $customerMethodKey, $transKey;
    private $flatFeeMerchant, $flatFeeKey, $flatFeePin, $flatFeeTransKey, $flatFeeSource;
    private $emvDeviceKey, $ueCardToken;
    private $mxCityCardToken, $mxFeeCardToken, $mxCardToken;
    private $hasFeePayment, $feeAmount, $cityAmount;
    private $result, $feeRef, $feeAuth, $flatFeeRef, $flatFeeAuth, $cityRef, $cityAuth, $proceedDate, $feeToken, $cityToken, $flatFeeToken;
    private $error;
    private $integratedID, $data, $isPartial, $isCityFirst, $paymentLevel;
    private $billingFirstName, $billingLastName, $billingStreet, $billingCity, $billingState, $billingPostalCode, $billingPhone;
    private $emvProcessingID;
    private $transAllID;
    private $integratedPairs, $integratedRecord, $integratedRecords, $formData, $formName, $siteURL;
    private $autoPayEmail, $autoPayAccountName, $autoPayAccountNumber, $autoPayMaxAmount, $setAutoPay;
    private $paymentType, $transactionStatus;
    private $chipID;
    private $cityCardId, $feeCardId;
    private $isAuthCard;
    const NONEPAY = 'None';
    const MANUALPAY = 'Manual';
    const SWIPEPAY = 'Swipe';
    const EMVPAY = 'EMV';
    const CHIPPAY = 'Chip';
    const QUICKSALEPAY = 'QuickSale';
    const CUSTOMERPAY = 'CustomerPay';
    const TOKENPAY = 'Token';
    const ACHPAY = 'ACH';
    const CASHPAY = 'Cash';
    const USAEPAY = 'USA EPay';
    const SMSUSER = '--SMS Payment';
    const MSG_EMV_USER = 'MSG EMV User';
    const MSG_API_USER = 'MSG API';
    const MX = 'MX';
    const CARDPOINT = 'CardPoint';
    const FLAT_FEE_SPLIT = 'flat_fee_split';
    const FLAT_FEE_MERGE = 'flat_fee_merge';
    const FLAT_FEE_SAME_CITY_SOURCE = 'flat_fee_same_city';
    const FLAT_FEE_SAME_FEE_SOURCE = 'flat_fee_same_fee';
    const FLAT_FEE_THIRD_SOURCE = 'flat_fee_third';

    public function __construct($portalID, $user, $amount, $integratedID = NULL, $data = NULL, $forceFee = false, $forceFeeAmount = NULL)
    {
        $this->portalID = $portalID;
        $this->user = $user;
        $this->amount = round(floatval($amount), 2);
        $this->integratedID = $integratedID;
        $this->data = $data;
        $this->isPartial = false;
        $this->forceFee = $forceFee;
        $this->forceFeeAmount = $forceFeeAmount;
        global $db;
        $portal = $db->where('Portal_Id', $portalID)->getOne('zoho_products');
        $this->portal = $portal;
        $this->feePercent = floatval($portal['Service_Fee']);
        $this->feeMinimum = floatval($portal['Minimum']);
        $this->amexFeePercent = floatval($portal['Amex_Service_Fee']);
        $this->amexFeeMinimum = floatval($portal['Amex_Minimum']);
        if ($this->feePercent > $this->amexFeePercent) {
            $this->amexFeePercent = $this->feePercent;
        }
        if ($this->feeMinimum > $this->amexFeeMinimum) {
            $this->amexFeeMinimum = $this->feeMinimum;
        }
        $this->gateway = $portal['Gateway'];
        if ($portal['Gateway'] == 'Authorize.net' && $portal['Var_Partner'] == 'RecDesk') {
            $this->gateway = 'USA EPay';
            // update fee percent
            $this->feePercent = $this->feePercent * 100 / (100 - $this->feePercent);
        }
        $this->entity = $portal['Entity'];
        $this->portalName = $portal['portal_name'];
        $this->formName = $portal['form_name'];
        $this->siteURL = $portal['Location_URL'];
        $this->varPartner = $portal['Var_Partner'];
        $this->department = $portal['department_type'];
        $this->ori = $portal['ORI_Number_MSG_Only'];
        $this->absorbFee = $this->_getPortalOption('absorb_fee') == true;
        $this->mergeFee = $this->_getPortalOption('merge_fee') == true;
        $this->shouldAutoAccept = $this->_getPortalOption('should_auto_accept') == true;
        $this->isCityFirst = $this->_getPortalOption('payment_city_first') == true;
        if ($portal['Gateway'] == 'Authorize.net' && $portal['Var_Partner'] == 'RecDesk') {
            $this->isCityFirst = true;
        }
        $this->paymentLevel = $this->_getPortalOption('payment_level', 'level1');
        // Get Billing Information
        $billingName = $portal['Customer_Service_Contact'];
        if (!$billingName) {
            $billingName = 'John Doe';
        }
        $billingName = explode(' ', $billingName);
        $this->billingFirstName = $billingName[0];
        $this->billingLastName = $billingName[count($billingName) - 1];
        $this->billingStreet = $portal['Address'];
        $this->billingCity = $portal['City'];
        $this->billingState = $portal['State'];
        $this->billingPostalCode = $portal['ZIP'];
        $this->billingPhone = $portal['Customer_Service_Number'];
        if ($this->gateway == self::USAEPAY) {
            $this->feeKey = $portal['Service_Fee_Source_Key'];
            $this->feePin = '8888';
            $this->cityKey = $portal['source_olp'];
            $this->cityPin = '8888';
        } elseif ($this->gateway == self::MX) {
            $this->feeMerchant = $portal['fee_mxid'];
            $this->feeKey = $portal['mx_gp_fee_user'];
            $this->feePin = $portal['mx_gp_fee_pass'];
            $this->cityMerchant = $portal['mxid'];
            $this->cityKey = $portal['GatewayUser_Login'];
            $this->cityPin = $portal['Gateway_User_Password'];
        } elseif ($this->gateway == self::CARDPOINT) {
            $this->feeMerchant = $portal['cp_fee_mid'];
            $this->cpFeeCNPSite = $portal['cp_fee_cnp_site'];
            $this->cpFeeEMVSite = $portal['cp_fee_emv_site'];
            $this->feeKey = $portal['cp_fee_cnp_key'];
            $this->feePin = $portal['cp_fee_emv_key'];
            $this->cityMerchant = $portal['cp_mid'];
            $this->cpCityCNPSite = $portal['cp_cnp_site'];
            $this->cpCityEMVSite = $portal['cp_emv_site'];
            $this->cityKey = $portal['cp_cnp_key'];
            $this->cityPin = $portal['cp_emv_key'];
        }
        $this->hasFlatFee = $this->_getPortalOption('flat_fee') == true;
        if ($this->hasFlatFee) {
            $this->flatFeeModel = $this->_getPortalOption(self::FLAT_FEE_MERGE) ? self::FLAT_FEE_MERGE : self::FLAT_FEE_SPLIT;
            if ($this->gateway == self::USAEPAY) {
                $this->flatFeeKey = $portal['flatfee_usa_key'];
                $this->flatFeePin = '8888';
            } elseif ($this->gateway == self::MX) {
                $this->flatFeeMerchant = $portal['flatfee_mx_id'];
                $this->flatFeeKey = $portal['flatfee_mx_key'];
                $this->flatFeePin = $portal['flatfee_mx_secret'];
            } elseif ($this->gateway == self::CARDPOINT) {
                $this->flatFeeMerchant = $portal['cp_flat_mid'];
                $this->cpFlatCNPSite = $portal['cp_flat_cnp_site'];
                $this->cpFlatEMVSite = $portal['cp_flat_emv_site'];
                $this->flatFeeKey = $portal['cp_flat_cnp_key'];
                $this->flatFeePin = $portal['cp_flat_emv_key'];
            }
            $this->flatFeeAmount = floatval($this->_getPortalOption('flat_fee_amount'));
            if (isset($_POST['flat_fee_amount'])) {
                $this->flatFeeAmount = filter_input(INPUT_POST, 'flat_fee_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }
            $this->flatFeeLabel = $this->_getPortalOption('flat_fee_description');
            if ($this->cityKey == $this->flatFeeKey) {
                // Same flat fee transaction with city
                $this->flatFeeSource = self::FLAT_FEE_SAME_CITY_SOURCE;
            } elseif ($this->feeKey == $this->flatFeeKey) {
                // Same flat fee transaction with fee
                $this->flatFeeSource = self::FLAT_FEE_SAME_FEE_SOURCE;
            } else {
                // Third flat fee source
                $this->flatFeeSource = self::FLAT_FEE_THIRD_SOURCE;
            }
            if ($this->flatFeeModel == self::FLAT_FEE_MERGE && $this->flatFeeSource == self::FLAT_FEE_THIRD_SOURCE) {
                $this->flatFeeModel = self::FLAT_FEE_SPLIT;
            }
        }
        $this->isAuthCard = false;
        $this->_calculateFee();
        $this->emvProcessingID = 0;
        $this->chipID = 0;
        $this->result = GPProcessResult::ERROR;
        $this->error = 'Not Proceed';
        $this->_prepareData();
        $this->setPaymentType('sale');
        $canSplitFee = $this->_getPortalOption('split_fee_master', true);
        if ($canSplitFee == false) {
            if ($this->feeAmount > 0) {
                $this->error = 'Service fee is not supported';
                $this->sendPaymentErrorEmail();
                echoErrorAndExit(400, $this->error);
            }
        }
    }
    public function setAuthCard()
    {
        $this->isAuthCard = true;
        $this->setPaymentType('auth');
        $this->forceFee = true;
        $this->forceFeeAmount = 0;
        $this->_calculateFee();
    }
    private function _calculateFee($isAmex = false)
    {
        if ($this->absorbFee) {
            $this->hasFeePayment = false;
            $this->cityAmount = $this->amount;
        } else {
            if ($this->forceFee) {
                $fee = floatval($this->forceFeeAmount);
            } else {
                if ($isAmex) {
                    $feePercent = $this->amexFeePercent;
                    $feeMinimum = $this->amexFeeMinimum;
                } else {
                    $feePercent = $this->feePercent;
                    $feeMinimum = $this->feeMinimum;
                }
                $fee = $this->amount * $feePercent / 100;
                if ($fee < $feeMinimum) {
                    $fee = $feeMinimum;
                }
            }
            if ($fee == 0) {
                $this->hasFeePayment = false;
                $this->cityAmount = $this->amount;
                $this->absorbFee = true;
                $this->mergeFee = false;
            } else {
                $this->feeAmount = round($fee, 2);
                if ($this->mergeFee) {
                    $this->hasFeePayment = false;
                    $this->cityAmount = round($this->amount + $this->feeAmount, 2);
                } else {
                    $this->hasFeePayment = true;
                    $this->cityAmount = $this->amount;
                }
            }
        }
        if ($this->hasFlatFee) {
            if ($this->flatFeeModel == self::FLAT_FEE_MERGE) {
                if ($this->flatFeeSource == self::FLAT_FEE_SAME_CITY_SOURCE) {
                    $this->cityAmount = round($this->cityAmount + $this->flatFeeAmount, 2);
                }
                if ($this->flatFeeSource == self::FLAT_FEE_SAME_FEE_SOURCE) {
                    if ($this->hasFeePayment) {
                        $this->feeAmount = round($this->feeAmount + $this->flatFeeAmount, 2);
                    } else {
                        $this->flatFeeModel == self::FLAT_FEE_SPLIT;
                    }
                }
            }
        }
    }
    public function setPaymentType($type)
    {
        if ($type == 'auth') {
            if ($this->gateway == self::USAEPAY) {
                $this->paymentType = 'authonly';
            } elseif ($this->gateway == self::MX) {
                $this->paymentType == 'Authorization';
            } elseif ($this->gateway == self::CARDPOINT) {
                $this->paymentType == 'n';
            }
            $this->transactionStatus = GPProcessResult::AUTHORIZED;
        } elseif ($type == 'sale') {
            if ($this->gateway == self::USAEPAY) {
                $this->paymentType = 'sale';
            } elseif ($this->gateway == self::MX) {
                $this->paymentType == 'Sale';
            } elseif ($this->gateway == self::CARDPOINT) {
                $this->paymentType == 'y';
            }
            $this->transactionStatus = GPProcessResult::APPROVED;
        } else {
            $this->setPaymentType('sale');
        }
    }
    public function setEMVProcessingID($id)
    {
        $this->emvProcessingID = $id;
    }
    public function setReferenceNumber($referenceNumber)
    {
        global $db;
        $db->where('GP_id', $this->transAllID)->update('trans_all', ['Reference_Number' => $referenceNumber]);
    }
    private function _getPortalOption($key, $default = false)
    {
        global $db;
        $value = $db->where('entity', $this->entity)->where('option_key', $key . '_' . $this->portalID)->getValue('gp_options', 'option_value');
        if ($value === NULL) {
            return $default;
        }
        if ($this->_isJson($value)) {
            return json_decode($value);
        }
        return stripslashes($value);
    }
    private function _getUniqID()
    {
        global $db;
        $value = $db->where('entity', '_')->where('option_key', 'uniq_id')->getValue('gp_options', 'option_value');
        if (!$value) {
            $value = 1;
            $db->insert('gp_options', ['option_value' => $value, 'option_key' => 'uniq_id', 'entity' => '_']);
        } else {
            $value++;
            $db->where('entity', '_')->where('option_key', 'uniq_id')->update('gp_options', ['option_value' => $value]);
        }
        return sprintf('%010d', "{$value}");
    }
    private function _isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function setAutoPayParam($email, $maxAmount = 0)
    {
        global $db;
        if ($this->integratedRecord) {
            if ($this->varPartner == 'NewRedLine') {
                $this->autoPayAccountName = $this->integratedRecord['account_name'];
                $this->autoPayAccountNumber = $this->integratedRecord['account_number'];
            }
            if ($this->varPartner == 'RVS') {
                $this->autoPayAccountName = $this->integratedRecord['customer_name'];
                $this->autoPayAccountNumber = $this->integratedRecord['customer_number'];
            }
            if ($this->autoPayAccountName && $this->autoPayAccountNumber && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->autoPayEmail = $email;
                $this->autoPayMaxAmount = $maxAmount;
                // Save card vault
                if ($this->_saveCardVault()) {
                    // Remove record if exist
                    $db->where('portal_id', $this->portalID)->where('account_number', $this->autoPayAccountNumber)->delete('recurring_customers');
                    $db->insert('recurring_customers', [
                        'portal_id' => $this->portalID,
                        'email' => $this->autoPayEmail,
                        'account_number' => $this->autoPayAccountNumber,
                        'account_name' => $this->autoPayAccountName,
                        'card_token' => '',
                        'last_paid' => date('Y-m-d H:i:s'),
                        'max_amount' => $this->autoPayMaxAmount,
                        'custom_data' => 'Complete',
                        'city_card_id' => $this->cityCardId,
                        'fee_card_id' => $this->feeCardId
                    ]);
                    $this->setAutoPay = true;
                }
            }
        }
        return $this->setAutoPay === true;
    }
    public function updateAutoPayParam($id, $cityCardId, $feeCardId)
    {
        global $db;
        $db->where('id', $id)->update('recurring_customers', [
            'city_card_id' => $cityCardId,
            'fee_card_id' => $feeCardId
        ]);
    }

    private function _notifyIplowSMSPayment($phone)
    {
        global $mailer;
        $mailer->notifyPayment(
            $this->entity,
            $this->portalName,
            $this->siteURL,
            array_merge(
                $this->formData,
                [
                    ['Paid Amount', $this->amount],
                    ['Entity', $this->entity],
                    ['Portal ID', $this->portalID],
                    ['Phone', $phone]
                ]
            )
        );
    }

    public function sendPaymentErrorEmail()
    {
        // skip errors
        foreach ([
            'Transaction has declined',
            'Declined',
            'Invalid Card Number',
            'Insufficient funds',
            'Card Has Expired',
            'Do not honor',
            'Missing expiration month',
            'Lost card',
            'Incorrect zipcode',
            'Expired card',
            'Invalid issuer',
            'Restricted card',
            'Security violation',
            'Transaction not permitted',
            'Invalid/nonexistent account specified',
            'Cryptographic failure',
            'Merchant does not accept this card due to its country of origin',
            'Suspected fraud',
            'Transaction request has timed out',
            'No checking account',
            'Exceeds withdrawal amount limit',
            'CID value must be 4 digit Parameter name: String',
            'Card Not Enabled',
            'Credit card has expired',
            'Invalid CVV value',
            'Card could not be verified',
            'Account not found',
            'Card is expired',
            'Error executing transaction',
            'An error has occurred. Please contact customer support',
            'Invalid key (B005)',
            'EMV Device is offline',
            'Transaction Requires Voice Authentication',
            'Invalid amount',
            'CID value must be 4 digit Parameter name',
            'Merchant does not accept card type',
            'Processing Error Please Try Again',
            'Stolen card',
            'Response Timedout',
            'Duplicate Request',
            'Invalid merchant',
            'Invalid format for data string'
        ] as $skipError) {
            if (substr(strtolower($this->error), 0, strlen($skipError)) === strtolower($skipError)) {
                return;
            }
        }
        global $mailer;
        $mailer->notifyPaymentErrors(
            $this->entity,
            $this->portalName,
            $this->siteURL,
            array_merge(
                $this->formData,
                [
                    ['Amount', $this->amount],
                    ['Entity', $this->entity],
                    ['Portal ID', $this->portalID],
                    ['User', $this->user],
                    ['Payment Method', $this->payType],
                    ['Card Type', $this->cardType],
                    ['Error Detail', $this->error]
                ]
            )
        );
    }
    public function processEMVPayment($deviceKey)
    {
        $this->emvDeviceKey = $deviceKey;
        $this->payType = self::EMVPAY;
        if ($this->hasFeePayment && $this->gateway == self::MX) {
            $this->error = 'Can\'t process fee (MX Chip)';
            return false;
        }
        // if (substr($this->emvDeviceKey, 0, 3) === 'sa_') {
        //     $this->gateway = self::USAEPAY;
        // } else {
        //     // Check if has fee payment
        //     if ($this->hasFeePayment) {
        //         $this->error = 'Can\'t process fee (MX Chip)';
        //         return false;
        //     }
        //     $this->gateway = self::MX;
        // }
        return $this->_processPayment();
    }
    public function processChipPayment($deviceKey)
    {
        $this->isCityFirst = true;
        $this->emvDeviceKey = $deviceKey;
        $this->payType = self::CHIPPAY;
        if ($this->hasFeePayment && $this->gateway == self::MX) {
            $this->error = 'Can\'t process fee (MX Chip)';
            return false;
        }
        // if (substr($this->emvDeviceKey, 0, 3) === 'sa_') {
        //     $this->gateway = self::USAEPAY;
        // } else {
        //     // Check if has fee payment
        //     if ($this->hasFeePayment) {
        //         $this->error = 'Can\'t process fee (MX Chip)';
        //         return false;
        //     }
        //     $this->gateway = self::MX;
        // }
        return $this->_processPayment();
    }
    public function processQuickSalePayment($transKey)
    {
        // Validate Gateway
        global $db;
        $cityRow = $db->where('tran_id', $transKey)->where('kind', 'city')->getOne('gp_trans');
        if (!$cityRow) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Wrong Customer ID';
            return false;
        }
        if ($this->gateway != $cityRow['gateway']) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Conflict Gateway';
            return false;
        }
        // Validate City Key
        if ($this->cityKey != $cityRow['merchant_key']) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Wrong Source Key (1)';
            return false;
        }
        $this->cityTransKey = $cityRow['merchant_tran_key'];
        // Validate Fee Key
        if ($this->hasFeePayment) {
            $feeRow = $db->where('tran_id', $transKey)->where('kind', 'fee')->getOne('gp_trans');
            if (!$feeRow) {
                $this->result = GPProcessResult::ERROR;
                $this->error = 'Wrong Source Key (2)';
                return false;
            }
            if ($this->feeKey != $feeRow['merchant_key']) {
                $this->result = GPProcessResult::ERROR;
                $this->error = 'Wrong Source Key (3)';
                return false;
            }
            $this->feeTransKey = $feeRow['merchant_tran_key'];
        }
        $this->payType = self::QUICKSALEPAY;
        // Get Card Type & Last4
        $originalTransaction = $db->where('GP_id', $transKey)->getOne('trans_all');
        if ($originalTransaction) {
            $this->cardType = $originalTransaction['cc_type'];
            $this->cardNumber = $originalTransaction['datastore_id'];
        }
        return $this->_processPayment();
    }
    public function processCustomerPayment($customerID, $cardID)
    {
        global $db;
        // Check customer id and card id
        if ($this->gateway != self::USAEPAY) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Unsupported Gateway';
            return false;
        }
        $cityCustomer = $db->where('api_key', $this->cityKey)
            ->where('api_secret', $this->cityPin)
            ->where('portal_id', $this->portalID)
            ->where('customer_id', $customerID)
            ->getOne('gp_customers');
        if (!$cityCustomer) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Wrong Customer ID';
            return false;
        }
        // Get city method
        $cityCustomerMethod = $db->where('customer_id', $cityCustomer['id'])
            ->where('card_key', $cardID)
            ->getOne('gp_customer_methods');
        if (!$cityCustomerMethod) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Wrong Card ID';
            return false;
        }
        if ($this->hasFeePayment) {
            $feeCustomer = $db->where('api_key', $this->feeKey)
                ->where('api_secret', $this->feePin)
                ->where('portal_id', $this->portalID)
                ->where('customer_id', $customerID)
                ->getOne('gp_customers');
            if (!$feeCustomer) {
                $this->result = GPProcessResult::ERROR;
                $this->error = 'Wrong Customer ID(1)';
                return false;
            }
            // Get fee method
            $feeCustomerMethod = $db->where('customer_id', $feeCustomer['id'])
                ->where('card_key', $cardID)
                ->getOne('gp_customer_methods');
            if (!$feeCustomerMethod) {
                $this->result = GPProcessResult::ERROR;
                $this->error = 'Wrong Card ID(1)';
                return false;
            }
            $this->feeCustomerKey = $feeCustomer['customer_key'];
            $this->feeCustomerMethodKey = $feeCustomerMethod['method_key'];
        }
        $this->cityCustomerKey = $cityCustomer['customer_key'];
        $this->cityCustomerMethodKey = $cityCustomerMethod['method_key'];
        $this->cardType = $cityCustomerMethod['card_type'];
        $this->payType = self::CUSTOMERPAY;
        return $this->_processPayment();
    }
    public function processVaultPayment($cityCardId, $feeCardId)
    {
        global $db;
        $cityCardRow = $db->rawQueryOne("select gp_gateway_autopay_card_tokens.*, gp_gateway_autopay_accounts.gateway, gp_gateway_autopay_accounts.merchant_key FROM gp_gateway_autopay_card_tokens INNER JOIN gp_gateway_autopay_accounts ON gp_gateway_autopay_accounts.customer_id = gp_gateway_autopay_card_tokens.account_id WHERE gp_gateway_autopay_card_tokens.id = ?", array($cityCardId));
        if (!$cityCardRow) {
            $this->error = 'Wrong Card Vault (1)';
            return false;
        }
        if ($cityCardRow['gateway'] != $this->gateway) {
            $this->error = 'Conflict Gateway (1)';
            return false;
        }
        if (($this->gateway == self::MX ? $this->cityMerchant : $this->cityKey) != $cityCardRow['merchant_key']) {
            $this->error = 'Conflict Merchant (1)';
            return false;
        }
        $this->cityCustomerKey = $cityCardRow['account_id'];
        $this->cityCustomerMethodKey = $cityCardRow['card_token'];
        $this->cardType = $cityCardRow['card_type'];
        $this->cardNumber = $cityCardRow['card_last4'];
        if ($this->cardType == self::ACHPAY) {
            $this->updateFeeModelForACH();
        }
        if ($this->hasFeePayment) {
            $feeCardRow = $db->rawQueryOne("select gp_gateway_autopay_card_tokens.*, gp_gateway_autopay_accounts.gateway, gp_gateway_autopay_accounts.merchant_key FROM gp_gateway_autopay_card_tokens INNER JOIN gp_gateway_autopay_accounts ON gp_gateway_autopay_accounts.customer_id = gp_gateway_autopay_card_tokens.account_id WHERE gp_gateway_autopay_card_tokens.id = ?", array($feeCardId));
            if (!$feeCardRow) {
                $this->error = 'Wrong Card Vault (2)';
                return false;
            }
            if ($feeCardRow['gateway'] != $this->gateway) {
                $this->error = 'Conflict Gateway (2)';
                return false;
            }
            if (($this->gateway == self::MX ? $this->feeMerchant : $this->feeKey) != $feeCardRow['merchant_key']) {
                $this->error = 'Conflict Merchant (2)';
                return false;
            }
            $this->feeCustomerKey = $feeCardRow['account_id'];
            $this->feeCustomerMethodKey = $feeCardRow['card_token'];
        }
        $this->payType = self::CUSTOMERPAY;
        return $this->_processPayment();
    }
    public function processTokenPayment($gateway, $token_city, $token_fee = '')
    {
        $this->payType = self::TOKENPAY;
        if ($gateway != $this->gateway) {
            $this->result = GPProcessResult::ERROR;
            $this->error = 'Conflict Gateway';
            return false;
        }
        if ($gateway == self::MX) {
            if ($this->hasFeePayment && !$token_fee) {
                $this->result = GPProcessResult::ERROR;
                $this->error = 'Missing Fee Token';
                return false;
            }
        }
        if ($gateway == self::USAEPAY) {
            $this->ueCardToken = $token_city;
        } else {
            $this->mxCityCardToken = $token_city;
            $this->mxFeeCardToken = $token_fee;
        }
        return $this->_processPayment();
    }
    public function processACHPayment($accountNumber, $routingNumber, $accountType, $accountName)
    {
        if ($this->gateway == self::USAEPAY) {
            $this->paymentType = 'check:sale';
        }
        // $this->gateway = self::MX;
        // $this->cityMerchant = $this->portal['mxid'];
        // $this->cityKey = $this->portal['GatewayUser_Login'];
        // $this->cityPin = $this->portal['Gateway_User_Password'];
        $this->cardHolder = $accountName;
        $this->cardNumber = $accountNumber;
        $this->achAccountNumber = $accountNumber;
        $this->achRoutingNumber = $routingNumber;
        $this->achAccountName = $accountName;
        $this->achAccountType = $accountType;
        $this->cardType = self::ACHPAY;
        $this->payType = self::ACHPAY;
        $this->updateFeeModelForACH();
        return $this->_processPayment();
    }
    private function updateFeeModelForACH()
    {
        // Change Fee Model
        if ($this->portal['ach_fee_model'] == 'Absorb Fee') {
            $this->hasFeePayment = false;
            $this->absorbFee = true;
            $this->mergeFee = false;
        } else {
            // Calculate Fee
            if (!$this->forceFee) {
                if ($this->portal['ach_fee_type'] == 'Flat Fee') {
                    $fee = floatval($this->portal['ach_flat_fee_amount']);
                } else {
                    $fee = $this->amount * $this->portal['ach_fee_percent'] / 100;
                }
                if ($fee < floatval($this->portal['ach_flat_fee_amount'])) {
                    $fee = floatval($this->portal['ach_flat_fee_amount']);
                }
            } else {
                $fee = $this->feeAmount;
            }
            if ($this->portal['ach_fee_model'] == 'Split Fee') {
                $this->mergeFee = false;
                if ($this->gateway == self::MX) {
                    $this->feeMerchant = $this->portal['ach_fee_merchant_id'];
                    $this->feeKey = $this->portal['ach_fee_merchant_key'];
                    $this->feePin = $this->portal['ach_fee_merchant_secret'];
                } else {
                    $this->feeKey = $this->portal['ach_fee_merchant_key'];
                    $this->feePin = '8888';
                }
            } else {
                $this->mergeFee = true;
            }
            if ($fee == 0) {
                $this->hasFeePayment = false;
                $this->cityAmount = $this->amount;
                $this->absorbFee = true;
                $this->mergeFee = false;
            } else {
                $this->feeAmount = round($fee, 2);
                if ($this->mergeFee) {
                    $this->hasFeePayment = false;
                    $this->cityAmount = round($this->amount + $this->feeAmount, 2);
                } else {
                    $this->hasFeePayment = true;
                    $this->cityAmount = $this->amount;
                }
            }
        }
    }
    public function processManualPayment($cardHolder, $cardNumber, $cardExpire, $cardCVV = '', $cardZip = '', $cardStreet = '')
    {
        $this->cardHolder = $cardHolder;
        $this->cardNumber = $cardNumber;
        $this->cardExpire = $cardExpire;
        $this->cardCVV = $cardCVV;
        $this->cardZip = $cardZip;
        $this->cardStreet = $cardStreet;
        $this->cardType = CreditCard::getCardBrand($cardNumber);
        if ($this->cardType == 'AMEX') {
            $this->_calculateFee(true);
        }
        $this->payType = self::MANUALPAY;
        return $this->_processPayment();
    }
    public function processSwipePayment($magStripe)
    {
        try {
            $stripe = new MagStripe($magStripe);
            $this->cardHolder = $stripe->getName();
            $this->cardNumber = $stripe->getAccount();
            $this->cardType = CreditCard::getCardBrand($this->cardNumber);
            if ($this->cardType == 'AMEX') {
                $this->_calculateFee(true);
            }
            $this->magStripe = $magStripe;
            $this->payType = self::SWIPEPAY;
            return $this->_processPayment();
        } catch (Exception $th) {
            $this->result = GPProcessResult::ERROR;
            $this->error = $th->getMessage();
        }
        return false;
    }
    public function processCashPayment()
    {
        $this->gateway = self::CASHPAY;
        $this->payType = self::CASHPAY;
        $this->cardType = self::CASHPAY;
        $this->absorbFee = true;
        $this->_calculateFee();
        return $this->_processPayment();
    }
    private function _processPayment()
    {
        if (!$this->isCityFirst) {
            if ($this->hasFeePayment) {
                if (!$this->_processFeePayment()) {
                    return false;
                }
            }
            if (!$this->_processCityPayment()) {
                if ($this->hasFeePayment) {
                    $this->_voidFeePayment();
                }
                return false;
            }
        } else {
            if (!$this->_processCityPayment()) {
                return false;
            }
            if ($this->hasFeePayment) {
                if (!$this->_processFeePayment()) {
                    $this->_voidCityPayment();
                    return false;
                }
            }
        }
        if ($this->hasFlatFee && $this->flatFeeModel == self::FLAT_FEE_SPLIT) {
            if (!$this->_processFlatFeePayment()) {
                $this->result = GPProcessResult::ERROR;
                $this->_voidCityPayment();
                if ($this->hasFeePayment) {
                    $this->_voidFeePayment();
                }
                return false;
            }
        }
        $this->proceedDate = new DateTime();
        if (!$this->isAuthCard) {
            $this->_processData();
        }
        return true;
    }
    private function _voidFeePayment()
    {
        if ($this->gateway == self::USAEPAY) {
            $this->_voidUSAEPayPayment($this->feeKey, $this->feePin, $this->feeRef);
        } elseif ($this->gateway == self::MX) {
            $this->_voidMXPayment($this->feeKey, $this->feePin, $this->feeRef);
        } elseif ($this->gateway == self::CARDPOINT) {
            $this->_voidCardPointPayment($this->cpFeeCNPSite, $this->feeMerchant, $this->feeKey, $this->feeRef);
        }
    }
    public function voidAuth()
    {
        if ($this->gateway == self::USAEPAY) {
            $this->_voidUSAEPayPayment($this->cityKey, $this->cityPin, $this->cityRef);
        } elseif ($this->gateway == self::MX) {
            $this->_voidMXPayment($this->cityKey, $this->cityPin, $this->cityRef);
        } elseif ($this->gateway == self::CARDPOINT) {
            $this->_voidCardPointPayment($this->cpCityCNPSite, $this->cityMerchant, $this->cityKey, $this->cityRef);
        }
    }
    private function _voidCityPayment()
    {
        if ($this->gateway == self::USAEPAY) {
            $this->_voidUSAEPayPayment($this->cityKey, $this->cityPin, $this->cityRef);
        } elseif ($this->gateway == self::MX) {
            $this->_voidMXPayment($this->cityKey, $this->cityPin, $this->cityRef);
        } elseif ($this->gateway == self::CARDPOINT) {
            $this->_voidCardPointPayment($this->cpCityCNPSite, $this->cityMerchant, $this->cityKey, $this->cityRef);
        }
        $this->result = GPProcessResult::ERROR;
    }
    private function _voidUSAEPayPayment($apiKey, $apiPin, $refID)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'command' => 'void',
                'refnum' => $refID
            ]),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        curl_exec($curl);
        curl_close($curl);
    }
    private function _voidMXPayment($apiKey, $apiPin, $refID)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment/" . $refID . "?force=true",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));

        curl_exec($curl);

        curl_close($curl);
    }
    private function _voidCardPointPayment($site, $merchantId, $apiKey, $refID)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://" . $site . ".cardconnect.com/cardconnect/rest/void",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => json_encode([
                'merchid' => $merchantId,
                'retref' => $refID
            ]),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: " . $apiKey,
            )
        ));
        curl_exec($curl);
        curl_close($curl);
    }
    private function _processData()
    {
        global $db;
        $insertTransAllData = [
            'portal_name' => $this->portalName . ($this->isPartial ? ' (Partial Payment)' : (is_array($this->integratedID) ? ' (Multiple Payment)' : '')),
            'Portal_Id' => $this->portalID,
            'CardHolder' => $this->cardHolder,
            'TransIDCity' => $this->cityRef,
            'Status' => $this->transactionStatus,
            'entity' => $this->entity,
            'cc_type' => $this->cardType,
            'amount_city' => $this->cityAmount,
            'tdate_city' => $this->proceedDate->format('Y-m-d H:i:s'),
            'authcode_city' => $this->cityAuth,
            'var' => $this->varPartner,
            'username' => $this->user,
            'absorb_fee' => $this->absorbFee ? 'yes' : 'no',
            'merge_fee' => $this->mergeFee ? 'yes' : 'no',
            'gateway' => $this->gateway,
            'source_name' => json_encode([$this->cityToken, $this->feeToken]),
            'datastore_id' => substr($this->cardNumber, -4)
        ];
        if ($this->hasFeePayment) {
            $insertTransAllData = array_merge($insertTransAllData, [
                'TransIDFee' => $this->feeRef,
                'amount_fee' => $this->feeAmount,
                'tdate_fee' => $this->proceedDate->format('Y-m-d H:i:s'),
                'authcode_fee' => $this->feeAuth
            ]);
        }
        if ($this->mergeFee) {
            $insertTransAllData['amount_city'] = $this->cityAmount - $this->feeAmount;
            $insertTransAllData['amount_fee'] = $this->feeAmount;
        }
        if ($this->hasFlatFee) {
            $insertTransAllData['fromfile'] = json_encode([$this->flatFeeAmount, $this->flatFeeLabel, $this->flatFeeModel, $this->flatFeeSource, $this->flatFeeRef, $this->flatFeeAuth, $this->flatFeeToken]);
            if (!$this->mergeFee && $this->flatFeeModel == self::FLAT_FEE_MERGE && $this->flatFeeSource == self::FLAT_FEE_SAME_CITY_SOURCE) {
                $insertTransAllData['amount_city'] = round($this->cityAmount - $this->flatFeeAmount, 2);
            }
            if ($this->hasFlatFee && $this->flatFeeModel == self::FLAT_FEE_MERGE && $this->flatFeeSource == self::FLAT_FEE_SAME_FEE_SOURCE) {
                $insertTransAllData['amount_fee'] = $this->feeAmount - $this->flatFeeAmount;
            }
        }
        if ($this->varPartner == 'RecDesk' && $this->data['PersonName'] == 'NONE') {
            $this->data['PersonName'] = $this->cardHolder;
        }
        $insertTransAllData = array_merge($insertTransAllData, $this->data);
        $this->transAllID = $db->insert('trans_all', $insertTransAllData);
        // Save transAllID if chip payment
        if ($this->chipID > 0) {
            $db->where('id', $this->chipID)->update('gp_chip_logs', ['status' => 'transaction complete', 'transaction_id' => $this->transAllID]);
        }

        // Save Gateway, Key, Secret, Payment Id, Amount, Status
        $db->insert('gp_trans', [
            'tran_id' => $this->transAllID,
            'kind' => 'city',
            'gateway' => $this->gateway,
            'merchant_id' => $this->cityMerchant,
            'merchant_key' => $this->cityKey,
            'merchant_secret' => $this->cityPin,
            'merchant_tran_key' => $this->cityToken,
            'amount' => $this->cityAmount,
            'status' => $this->transactionStatus
        ]);
        if ($this->hasFeePayment) {
            $db->insert('gp_trans', [
                'tran_id' => $this->transAllID,
                'kind' => 'fee',
                'gateway' => $this->gateway,
                'merchant_id' => $this->feeMerchant,
                'merchant_key' => $this->feeKey,
                'merchant_secret' => $this->feePin,
                'merchant_tran_key' => $this->feeToken,
                'amount' => $this->feeAmount,
                'status' => $this->transactionStatus
            ]);
        }
        if ($this->hasFlatFee && $this->flatFeeModel == self::FLAT_FEE_SPLIT) {
            $db->insert('gp_trans', [
                'tran_id' => $this->transAllID,
                'kind' => 'flat_fee',
                'gateway' => $this->gateway,
                'merchant_id' => $this->flatFeeMerchant,
                'merchant_key' => $this->flatFeeKey,
                'merchant_secret' => $this->flatFeePin,
                'merchant_tran_key' => $this->flatFeeToken,
                'amount' => $this->flatFeeAmount,
                'status' => $this->transactionStatus
            ]);
        }
        if ($this->integratedID) {
            if (!is_array($this->integratedID)) {
                $this->_postIntegratedModule($this->integratedRecord);
            } elseif (is_array($this->integratedID)) {
                foreach ($this->integratedRecords as $integratedRow) {
                    $this->_postIntegratedModule($integratedRow);
                }
            }
        } else {
            if (in_array($this->varPartner, ['NewRedLine', 'MSG', 'RBA', 'BluePrince', 'IPLOW', 'EBTF'])) {
                $this->_postNonIntegratedModule();
            }
        }
    }
    private function _formatPhone($number)
    {
        return preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $number);
    }
    private function _beautifyETADate($v, $us_format = true)
    {
        $v = trim($v);
        if (strlen($v) != 8 || $v == '00000000') {
            return '';
            // return $v;
        }
        $date = DateTime::createFromFormat('Ymd', $v);
        if ($us_format) {
            return $date->format('m/d/Y');
        }
        return $date->format('Y-m-d');
    }
    private function _beautifyMSGDate($v, $us_format = true)
    {
        $v = trim($v);
        if (strlen($v) != 8 || $v == '00000000') {
            return '';
            // return $v;
        }
        $date = DateTime::createFromFormat('mdY', $v);
        if ($us_format) {
            return $date->format('m/d/Y');
        }
        return $date->format('Y-m-d');
    }
    private function _postNonIntegratedModule()
    {
        global $db;
        if ($this->varPartner == 'NewRedLine') {
            $db->insert('redline_transactions', [
                'ref_id' => $this->cityRef,
                'entity' => $this->entity,
                'invoice' => trim($this->data['standard1']),
                'cc_type' => $this->cardType,
                'amount' => $this->amount,
                'post_date' => $this->proceedDate->format('Y-m-d'),
                'post_time' => $this->proceedDate->format('H:i:s'),
                'trans_all_id' => $this->transAllID,
                'username' => $this->user
            ]);
        }
        if ($this->varPartner == 'MSG' && $this->user != self::MSG_EMV_USER && $this->user != self::MSG_API_USER) {
            $db->insert('transactions', [
                'TransactionID' => $this->cityRef,
                'status' => $this->isPartial ? 'PARTIAL PAID' : 'PAID',
                'ORI_number' => trim($this->ori),
                'CaseNumber' => trim($this->data['standard1']),
                'CitationNumber' => trim($this->data['standard2']),
                'trans_type' => 'GOVT',
                'authcode' => $this->cityAuth,
                'amount' => $this->amount,
                'session' => $this->transAllID,
                'tdate' => $this->proceedDate->format('Y-m-d H:i:s')
            ]);
        }
        if ($this->varPartner == 'BluePrince') {
            // status,authcode.permitnum,ordertoken,refnum
            $this->_bluePrincePostData([
                'status' => $this->result,
                'authcode' => $this->cityAuth,
                'permitnum' => $this->data['standard1'],
                'ordertoken' => $this->data['standard2'],
                'refnum' => $this->cityRef
            ]);
        }
        if ($this->varPartner == 'IPLOW') {
            $db->insert('iplow_transactions', [
                'portal_id' => $this->portalID,
                'iplowid' => $this->portalID . '_' . $this->data['standard2'],
                'pay_type' => 'Sale',
                'paid_amount' => $this->amount,
                'paid_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                'card_number' => trim(CreditCard::getLast4($this->cardNumber)),
                'card_type' => $this->cardType,
                'ref_id' => $this->cityRef,
                'auth_code' => $this->cityAuth,
                'case_number' => $this->data['standard2'],
                'trans_all_id' => $this->transAllID
            ]);
        }
        if ($this->varPartner == 'EBTF' && isset($_POST['zoho_invoice_key'])) {
            $this->_ebtfPostAmount($_POST['zoho_invoice_key'], $this->amount);
        }
    }
    private function _postIntegratedModule($row)
    {
        global $db;
        if ($this->varPartner == 'MSG') {
            if (!$this->isPartial) {
                $db->where('id', $row['id'])->update(
                    'incoming',
                    [
                        'IneligibleFlag' => 'True',
                        'Amount' => 0,
                    ]
                );
            } else {
                $db->where('id', $row['id'])->update(
                    'incoming',
                    [
                        'Amount' => $db->dec($this->amount)
                    ]
                );
            }

            $db->insert(
                'transactions',
                [
                    'TransactionID' => $this->cityRef,
                    'status' => $this->isPartial ? 'PARTIAL PAID' : 'PAID',
                    'ORI_number' => trim($this->ori),
                    'CaseNumber' => $row['CaseNumber'],
                    'CitationNumber' => trim($row['CitationNumber']),
                    'trans_type' => 'GOVT',
                    'authcode' => $this->cityAuth,
                    'amount' => is_array($this->integratedID) ? $row['Amount'] : $this->amount,
                    'session' =>  $this->transAllID,
                    'tdate' => $this->proceedDate->format('Y-m-d H:i:s')
                ]
            );
        }
        if ($this->varPartner == 'HCSS') {
            if (!$this->isPartial) {
                $db->where('id', $row['id'])->update(
                    'hcss_incoming',
                    [
                        'TotalFine' => 0,
                    ]
                );
            } else {
                $db->where('id', $row['id'])->update(
                    'hcss_incoming',
                    [
                        'TotalFine' => $db->dec($this->amount)
                    ]
                );
            }
            $db->insert(
                'hcss_transactions',
                [
                    'entity' => $row['entity'],
                    'CauseNumber' => $row['CauseNumber'],
                    'TicketNumber' => $row['TicketNumber'],
                    'PaidDate' => $this->proceedDate->format('m/d/Y'),
                    'PaidTime' => $this->proceedDate->format('H:i'),
                    'PaidBy' => $this->user,
                    'PaidAmount' => is_array($this->integratedID) ? $row['TotalFine'] : $this->amount,
                    'ConfirmationNumber' => $this->cityAuth,
                    'CauseTypeCode' => $row['CauseTypeCode'],
                    'PaymentGUID' => $this->transAllID,
                    'status' => 'Approved',
                    'active' => 'yes'
                ]
            );
        }
        if ($this->varPartner == 'IPLOW') {
            if (is_array($this->integratedID)) {
                $db->where('ID', $row['ID'])->update(
                    'iplow_incoming',
                    [
                        'TotalBalanceDue' => 0,
                        'TotalPaymentAmount' => $db->inc($row['TotalBalanceDue']),
                    ]
                );
            } else {
                $db->where('ID', $row['ID'])->update(
                    'iplow_incoming',
                    [
                        'TotalBalanceDue' => $db->dec($this->amount),
                        'TotalPaymentAmount' => $db->inc($this->amount),
                    ]
                );
            }

            $db->insert('iplow_transactions', [
                'portal_id' => $this->portalID,
                'iplowid' => $row['IplowID'],
                'pay_type' => 'Sale',
                'paid_amount' => is_array($this->integratedID) ? $row['TotalBalanceDue'] : $this->amount,
                'paid_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                'card_number' => trim(CreditCard::getLast4($this->cardNumber)),
                'card_type' => $this->cardType,
                'ref_id' => $this->cityRef,
                'auth_code' => $this->cityAuth,
                'case_number' => $row['CaseNumber'],
                'trans_all_id' => $this->transAllID
            ]);
            if ($this->user == self::SMSUSER) {
                $db->insert('iplow_sms_logs', [
                    'portal_id' => $this->portalID,
                    'post_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                    'phone' => $row['PhoneHome'],
                    'status' => 'paid'
                ]);
                $this->_notifyIplowSMSPayment($row['PhoneHome']);
            }
        }
        if ($this->varPartner == 'NewRedLine') {
            $db->where('id', $row['id'])->update('redline_integrations', ['balance' => $db->dec($this->amount)]);
            $db->insert('redline_transactions', [
                'ref_id' => $this->cityRef,
                'entity' => $this->entity,
                'invoice' => $row['invoice'] ? $row['invoice'] : $row['account_number'],
                'cc_type' => trim($this->cardType),
                'amount' => $this->amount,
                'post_date' => $this->proceedDate->format('Y-m-d'),
                'post_time' => $this->proceedDate->format('H:i:s'),
                'trans_all_id' => $this->transAllID,
                'username' => $this->user
            ]);
        }
        if ($this->varPartner == 'BBI') {
            $db->where('id', $row['id'])->update('bbi_incoming', ['balance' => $db->dec($this->amount)]);
            $db->insert('bbi_transactions', [
                'pay_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                'entity' => $this->entity,
                'portal_id' => $this->portalID,
                'account_name' => trim($row['account_name']),
                'account_number' => trim($row['account_number']),
                'card_type' => $this->cardType,
                'amount' => $this->amount,
                'trans_all_id' => $this->transAllID
            ]);
        }
        if ($this->varPartner == 'GAS') {
            $db->where('id', $row['id'])->update('gas_incoming', ['balance' => $db->dec($this->amount)]);
            $db->insert('gas_transactions', [
                'pay_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                'entity' => $this->entity,
                'portal_id' => $this->portalID,
                'account_name' => trim($row['account_name']),
                'account_number' => trim($row['account_number']),
                'amount' => $this->amount,
                'auth_code' => $this->cityAuth,
                'trans_all_id' => $this->transAllID
            ]);
        }
        if ($this->varPartner == 'RVS') {
            $db->where('id', $row['id'])->update('rvs_integrations', ['balance' => $db->dec($this->amount)]);
            $db->insert('rvs_transactions', [
                'pay_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                'entity' => $this->entity,
                'portal_id' => $this->portalID,
                'customer_name' => trim($row['customer_name']),
                'customer_number' => trim($row['customer_number']),
                'amount' => $this->amount,
                'auth_code' => $this->cityAuth,
                'trans_all_id' => $this->transAllID
            ]);
        }
        if ($this->varPartner == 'LGS') {
            if (is_array($this->integratedID)) {
                $db->where('id', $row['id'])->update('gp_lgs_incoming', ['balance' => 0]);
            } else {
                $db->where('id', $row['id'])->update('gp_lgs_incoming', ['balance' => $db->dec($this->amount)]);
            }

            $db->insert('gp_lgs_transactions', [
                'pay_dt' => $this->proceedDate->format('Y-m-d H:i:s'),
                'portal_id' => $this->portalID,
                'violator_name' => trim($row['violator_name']),
                'case_number' => trim($row['case_number']),
                'ticket_number' => trim($row['ticket_number']),
                'amount' => is_array($this->integratedID) ? $row['balance'] : $this->amount,
                'auth_code' => $this->cityAuth,
                'trans_all_id' => $this->transAllID,
                'status' => 'Approved'
            ]);
        }
        if ($this->varPartner == 'PGIS') {
            $db->where('id', $row['id'])->update('pgis_integrations', ['balance' => $db->dec($this->amount)]);
            $db->insert('pgis_transactions', [
                'paid_dt' => $this->proceedDate->format('Y-m-d H:i:s'),
                'entity' => $this->entity,
                'portal_id' => $this->portalID,
                'record_id' => trim($row['id']),
                'amount' => $this->amount,
                'auth_code' => $this->cityAuth,
                'trans_all_id' => $this->transAllID
            ]);
        }
        if ($this->varPartner == 'RBA') {
            if ($this->department == 'Sewer') {
                $db->where('id', $row['id'])->update('rba_incoming', ['balance' => $db->dec($this->amount)]);
            }
            if ($this->department == 'General') {
                $db->where('id', $row['id'])->update('rba_incoming_general', ['balance' => $db->dec($this->amount)]);
            }

            $db->insert('rba_transactions', [
                'ref_id' => $this->cityRef,
                'entity' => $this->entity,
                'portal_id' => $this->portalID,
                'account_name' => trim($row['account_name']),
                'account_number' => trim($row['account_number']),
                'cc_type' => $this->cardType,
                'amount' => $this->amount,
                'post_time' => $this->proceedDate->format('Y-m-d H:i:s'),
                'trans_all_id' => $this->transAllID
            ]);
        }
        if ($this->varPartner == 'WRS') {
            if ($this->isPartial) {
                $db->where('id', $row['id'])->update('gp_int_autos_incoming', ['balance' => $db->dec($this->amount)]);
            } else {
                $db->where('id', $row['id'])->update('gp_int_autos_incoming', ['balance' => 0]);
            }
            // Prepare Post Data
            $this->_wrsPostData([
                'dmsCustomerId' => $this->_concatStr('-', $row['database_id'], $row['dealer_no'], $row['sale_no']),
                'dmsDealerId' => $this->_concatStr('-', $row['database_id'], $row['dealer_no']),
                'carpayPaymentId' => $this->transAllID,
                'actualTransactionUnixTime' => $this->proceedDate->getTimestamp(),
                'paymentMethod' => 'Credit',
                'referenceNumber' => $this->cityRef,
                'amountTotalWithConvenienceFee' => round($this->amount + $this->feeAmount + (($this->hasFlatFee && $this->flatFeeModel == self::FLAT_FEE_SPLIT) ? $this->flatFeeAmount : 0), 2),
                'amountTotalWithoutConvenienceFee' => $this->amount,
                'dmsShouldAutoAcceptPayment' => $this->shouldAutoAccept
            ]);
        }
        if ($this->varPartner == 'WYQ') {
            if ($this->isPartial) {
                $db->where('id', $row['id'])->update('gp_int_wyq_incoming', ['balance' => $db->dec($this->amount)]);
            } else {
                $db->where('id', $row['id'])->update('gp_int_wyq_incoming', ['balance' => 0]);
            }
        }
        if ($this->varPartner == 'UOP') {
            if ($this->isPartial) {
                $db->where('id', $row['id'])->update('gp_int_uop_incoming', ['balance' => $db->dec($this->amount)]);
            } else {
                $db->where('id', $row['id'])->update('gp_int_uop_incoming', ['balance' => 0]);
            }
            // Prepare Post Data
            $this->_uopPostData([
                'entity' => $this->entity,
                'account_number' => $row['account_number'],
                'reference_id' => $this->transAllID,
                'card_last4' => substr(trim(CreditCard::getLast4($this->cardNumber)), -4),
                'amount' => is_array($this->integratedID) ? $row['balance'] : $this->amount
            ]);
        }
        if ($this->varPartner == 'ETA' && $this->department == 'utility') {
            $db->where('ID', $row['ID'])->update('etanew_integrations', ['BALAMT' => $db->dec($this->amount)]);
            $this->_etaPostData([
                "strAppl" => $row['AAPP'],
                "strCust" => $row['AUETA'],
                "strCase" => $row['AUACC'],
                "strCost" => $this->_formatSeven($this->amount),
                "decRest" => $this->_formatSeven('0'),
                "decFine" => $this->_formatSeven($this->amount),
                "decCost" => $this->_formatSeven('0'),
                "strID" => $row['ID'],
                "strAuth" => $this->cityAuth
            ]);
        }
        if ($this->varPartner == 'ETA' && $this->department == 'court') {
            if ($this->isPartial) {
                $sql = "UPDATE `etanew_integrations` SET BALAMT = ROUND(BALAMT - {$this->amount}, 2), ";
                $resAmount = floatval($row['ARAMT']);
                $fineAmount = floatval($row['AFAMT']);
                $courtAmount = floatval($row['ACAMT']);
                $paidAmount = $this->amount;
                $updateAmt = [];
                if ($resAmount > 0) {
                    if ($paidAmount >= $resAmount) {
                        $updateAmt[] = 'ARAMT=0';
                        $paidAmount -= $resAmount;
                    } else {
                        $updateAmt[] = 'ARAMT=' . round($resAmount - $paidAmount, 2);
                        $resAmount = $paidAmount;
                        $paidAmount = 0;
                    }
                }
                if ($fineAmount > 0) {
                    if ($paidAmount >= $fineAmount) {
                        $updateAmt[] = 'AFAMT=0';
                        $paidAmount -= $fineAmount;
                    } else {
                        $updateAmt[] = 'AFAMT=' . round($fineAmount - $paidAmount, 2);
                        $fineAmount = $paidAmount;
                        $paidAmount = 0;
                    }
                }
                if ($courtAmount > 0) {
                    if ($paidAmount >= $courtAmount) {
                        $updateAmt[] = 'ACAMT=0';
                        $paidAmount -= $courtAmount;
                    } else {
                        $updateAmt[] = 'ACAMT=' . round($courtAmount - $paidAmount, 2);
                        $courtAmount = $paidAmount;
                        $paidAmount = 0;
                    }
                }
                $postData = [
                    "strAppl" => $row['AAPP'],
                    "strCust" => $row['ACETA'],
                    "strCase" => $row['ACASE'],
                    "decRest" => $this->_formatSeven($resAmount),
                    "decFine" => $this->_formatSeven($fineAmount),
                    "decCost" => $this->_formatSeven($courtAmount),
                    "strID" => $row['ID'],
                    "strAuth" => $this->cityAuth
                ];
                $sql .= implode(',', $updateAmt) . " WHERE `ID` = " . $row['ID'];
            } else {
                $postData = [
                    "strAppl" => $row['AAPP'],
                    "strCust" => $row['ACETA'],
                    "strCase" => $row['ACASE'],
                    "decRest" => $this->_formatSeven($row['ARAMT']),
                    "decFine" => $this->_formatSeven($row['AFAMT']),
                    "decCost" => $this->_formatSeven($row['ACAMT']),
                    "strID" => $row['ID'],
                    "strAuth" => $this->cityAuth
                ];
                $sql = "UPDATE `etanew_integrations` SET BALAMT = 0 WHERE `ID` = " . $row['ID'];
            }
            $this->_etaPostData($postData);

            $db->query($sql);
        }
    }

    /**
     * @param DateTime $dt
     */
    private function _getUTCTime($dt)
    {
        $utc = clone $dt;
        $utc->setTimezone(new DateTimeZone("UTC"));
        return $utc->format('Y-m-d H:i:s');
    }

    /**
     * @param array $postData
     */

    private function _wrsPostData($postData)
    {
        global $db;
        $postUrl = getenv('WRS_POST_URL');
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $postUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'API-Key: D05D2C81-1DBF-49D3-AC35-899BB9D6DFB8',
                'Content-Type: application/json'
            ]
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($curl);
        $actualResponse = (isset($info["header_size"])) ? substr($response, $info["header_size"]) : "";
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            $response = $err;
        }
        $db->insert('gp_int_autos_post_logs', [
            'post_time' => date('Y-m-d H:i:s'),
            'post_data' => json_encode($postData),
            'response' => $actualResponse,
            'response_code' => $httpcode
        ]);
    }

    private function _uopPostData($postData)
    {
        global $db;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://admin.utilityonlinepay.com/api/payment/customer',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            $response = $err;
        }
        $db->insert('gp_int_uop_post_logs', [
            'post_time' => date('Y-m-d H:i:s'),
            'post_data' => json_encode($postData),
            'response' => $response
        ]);
    }

    private function _bluePrincePostData($postData)
    {
        global $db;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://www.building-department.com/govtportalreplywl.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            $response = $err;
        }
        $db->insert('gp_int_blueprince_post_logs', [
            'post_time' => date('Y-m-d H:i:s'),
            'post_data' => json_encode($postData),
            'response' => $response
        ]);
    }

    private function _ebtfPostAmount($invoiceKey, $amount)
    {
        global $db, $mailer;
        // Get invoice id from invoice number
        $invoice = $db->where('id', $invoiceKey)->orderBy('id')->getOne('zoho_invoices');
        $transactionDetailUrl = sprintf("https://%s/transaction-details/?tran_id=%s", $this->portal['Location_URL'], $this->transAllID);
        if ($invoice) {
            $db->where('id', $invoice['id'])->update('zoho_invoices', ['balance' => $db->dec($amount)]);
            postZohoEBTFInvoicePaymentNotification($invoice['invoice_number'], $invoice['invoice_id'], $invoice['customer_id'], $amount, $transactionDetailUrl);
        } else {
            $mailer->sendMail(
                'Not found matched invoice',
                sprintf('<p>Invoice Key</p><p>%s</p><p><a href="%s">Transaction Detail</a></p>', $invoiceKey, $transactionDetailUrl)
            );
        }
    }

    private function _etaPostData($postData)
    {
        global $db;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.auburnopelika.com/api/post_pay.php",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            $record = [
                'url' => 'https://www.auburnopelika.com/api/post_pay.php',
                'request' => json_encode($postData),
                'success' => 0,
                'response' => $err,
            ];
            $this->send_error_email_to_eddie($record);
        } else {
            if (strpos($response, 'Successful') !== false) {
                $record = [
                    'url' => "https://www.auburnopelika.com/api/post_pay.php",
                    'request' => json_encode($postData),
                    'success' => 1,
                    'response' => $response,
                ];
            } else {
                $record = [
                    'url' => "https://www.auburnopelika.com/api/post_pay.php",
                    'request' => json_encode($postData),
                    'success' => 0,
                    'response' => $response,
                ];
                $this->send_error_email_to_eddie($record);
            }
        }
        $db->insert('etanew_post_log', $record);
    }

    private function send_error_email_to_eddie($record)
    {
        global $mailer;
        $body = stripslashes('<h2>Server Error:</h2><h3>Server Response: ' . $record['response'] . '</h3><h4>Data: ' . $record['request'] . '</h4>');
        $mailer->sendMail(getenv('COMPANY') . ' can not post to ETA', $body, ['Steve.Spates@ETADataDirect.Com', 'jlin@govtportal.com']);
    }

    private function _formatSeven($v)
    {
        $v = $v * 100;
        return sprintf('%07d', "{$v}");
    }

    private function _concatStr($sep = ', ')
    {
        if (count(func_get_args()) < 2) {
            return '';
        }
        $result = '';
        $first = true;
        foreach (func_get_args() as $param) {
            if ($first) {
                $first = false;
                continue;
            }
            $param = trim($param);
            $result .= ($result == '' || $param == '' ? '' : $sep) . $param;
        }
        return $result;
    }
    private function _processCityPayment()
    {
        if ($this->payType == self::CUSTOMERPAY) {
            $this->customerKey = $this->cityCustomerKey;
            $this->customerMethodKey = $this->cityCustomerMethodKey;
        }
        if ($this->gateway == self::USAEPAY) {
            if ($this->payType == self::QUICKSALEPAY) {
                $this->transKey = $this->cityTransKey;
            }
            $tran = $this->_processUSAEPayPayment($this->cityKey, $this->cityPin, $this->cityAmount);
        } elseif ($this->gateway == self::MX) {
            $this->mxCardToken = $this->mxCityCardToken;
            $tran = $this->_processMXPayment($this->cityMerchant, $this->cityKey, $this->cityPin, $this->cityTransKey, $this->cityAmount);
        } elseif ($this->gateway == self::CARDPOINT) {
            $tran = $this->_processCardPointPayment($this->cityMerchant, $this->cpCityCNPSite, $this->cpCityEMVSite, $this->cityKey, $this->cityPin, $this->cityAmount);
        } elseif ($this->gateway == self::CASHPAY) {
            $tran = $this->_processCashPayment($this->cityAmount);
        } else {
            return false;
        }
        if ($tran) {
            $this->cityRef = $tran->refID;
            $this->cityAuth = $tran->authCode;
            $this->result = GPProcessResult::APPROVED;
            $this->cityToken = $tran->payToken;
            $this->error = '';
            return true;
        } else {
            return false;
        }
    }
    private function _processFeePayment()
    {
        if ($this->payType == self::CUSTOMERPAY) {
            $this->customerKey = $this->feeCustomerKey;
            $this->customerMethodKey = $this->feeCustomerMethodKey;
        }
        if ($this->gateway == self::USAEPAY) {
            if ($this->payType == self::QUICKSALEPAY) {
                $this->transKey = $this->feeTransKey;
            }
            $tran = $this->_processUSAEPayPayment($this->feeKey, $this->feePin, $this->feeAmount);
        } elseif ($this->gateway == self::MX) {
            $this->mxCardToken = $this->mxFeeCardToken;
            $tran = $this->_processMXPayment($this->feeMerchant, $this->feeKey, $this->feePin, $this->feeTransKey, $this->feeAmount);
        } elseif ($this->gateway == self::CARDPOINT) {
            $tran = $this->_processCardPointPayment($this->feeMerchant, $this->cpFeeCNPSite, $this->cpFeeEMVSite, $this->feeKey, $this->feePin, $this->feeAmount);
        } elseif ($this->gateway == self::CASHPAY) {
            $tran = $this->_processCashPayment($this->feeAmount);
        } else {
            return false;
        }
        if ($tran) {
            $this->feeRef = $tran->refID;
            $this->feeAuth = $tran->authCode;
            $this->feeToken = $tran->payToken;
            return true;
        } else {
            $this->error .= ' (Fee)';
            return false;
        }
    }
    private function _processFlatFeePayment()
    {
        if ($this->gateway == self::USAEPAY) {
            $tran = $this->_processUSAEPayPayment($this->flatFeeKey, $this->flatFeePin,   $this->flatFeeAmount);
        } elseif ($this->gateway == self::MX) {
            $this->mxCardToken = $this->mxCityCardToken;
            $tran = $this->_processMXPayment($this->flatFeeMerchant, $this->flatFeeKey, $this->flatFeePin, $this->flatFeeTransKey, $this->flatFeeAmount);
        } elseif ($this->gateway == self::CARDPOINT) {
            $tran = $this->_processCardPointPayment($this->flatFeeMerchant, $this->cpFlatCNPSite, $this->cpFlatEMVSite, $this->flatFeeKey, $this->flatFeePin, $this->flatFeeAmount);
        } elseif ($this->gateway == self::CASHPAY) {
            $tran = $this->_processCashPayment($this->flatFeeAmount);
        } else {
            return false;
        }
        if ($tran) {
            $this->flatFeeRef = $tran->refID;
            $this->flatFeeAuth = $tran->authCode;
            $this->flatFeeToken = $tran->payToken;
            return true;
        } else {
            $this->error .= ' (Flat Fee)';
            return false;
        }
    }

    private function _processUSAEPayPayment($apiKey, $apiPin, $amount)
    {
        if ($this->payType == self::MANUALPAY) {
            return $this->_processUSAEPayManualPayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::EMVPAY) {
            return $this->_processUSAEPayEMVPayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::TOKENPAY) {
            return $this->_processUSAEPayTokenPayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::SWIPEPAY) {
            return $this->_processUSAEPaySwipePayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::CHIPPAY) {
            return $this->_processUSAEPayChipPayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::QUICKSALEPAY) {
            return $this->_processUSAEPayQuickSalePayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::CUSTOMERPAY) {
            return $this->_processUSAEPayCustomerPayment($apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::ACHPAY) {
            return $this->_processUSAEPayACHPayment($apiKey, $apiPin, $amount);
        }
        $this->error = $this->portalID . ' Transaction Error: No Payment Method';
        return false;
    }

    /**
     * @return array
     */
    private function _levelData($amount, $isQuickSale = false)
    {
        $result = [];
        $uniqID = $this->_getUniqID();
        if ($this->paymentLevel != 'level1') {
            $result = [
                'invoice' => $uniqID,
                'ponum' => $uniqID,
                'orderid' => $uniqID,
                'amount_detail' => [
                    'subtotal' => $amount,
                    'tax' => '0.00',
                    'nontaxable' => 'Y',
                    'tip' => '0.00',
                    'discount' => '0.00',
                    'shipping' => '0.00',
                    'duty' => '0.00'
                ],
                'billing_address' => [
                    'firstname' => $this->billingFirstName,
                    'lastname' => $this->billingLastName,
                    'street' => $this->billingStreet,
                    'city' => $this->billingCity,
                    'state' => $this->billingState,
                    'postalcode' => $this->billingPostalCode,
                    'country' => 'USA',
                    'phone' => $this->billingPhone
                ],
                'shipping_address' => [
                    'firstname' => $this->billingFirstName,
                    'lastname' => $this->billingLastName,
                    'street' => $this->billingStreet,
                    'city' => $this->billingCity,
                    'state' => $this->billingState,
                    'postalcode' => $this->billingPostalCode,
                    'country' => 'USA',
                    'phone' => $this->billingPhone
                ],
                'lineitems' => [
                    [
                        'sku' => $uniqID,
                        'name' => $this->portalName,
                        'cost' => $amount,
                        'qty' => '1',
                        'taxable' => false,
                        'tax_amount' => '0.00',
                        'tax_rate' => '0.00',
                        'discount_rate' => '0.00',
                        'discount_amount' => '0.00',
                        'commodity_code' => 'C2583'
                    ]
                ],
            ];
            if ($isQuickSale) {
                unset($result['billing_address'], $result['shipping_address']);
            }
        }

        return $result;
    }

    private function _processUSAEPayCustomerPayment($apiKey, $apiPin, $amount)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'command' => 'customer:sale',
                'amount' => number_format($amount, 2, '.', ''),
                'custkey' => $this->customerKey,
                'paymethod_key' => $this->customerMethodKey
            ], $this->_levelData($amount, true))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response == '') {
            $this->error = 'CURL Error';
            return false;
        }
        $response_obj = json_decode($response);
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        }
        if (!property_exists($response_obj, 'result_code')) {
            $this->error = $response;
            return false;
        }
        if ($response_obj->result_code != 'A') {
            $this->error = $response;
            return false;
        }
        if (property_exists($response_obj, 'creditcard')) {
            $this->cardNumber = $response_obj->creditcard->number;
        }
        return new GPProcessResult(GPProcessResult::APPROVED, $response_obj->refnum, $response_obj->authcode, '', $response_obj->key);
    }

    private function _processUSAEPayQuickSalePayment($apiKey, $apiPin, $amount)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'command' => 'quicksale',
                'amount' => number_format($amount, 2, '.', ''),
                'trankey' => $this->transKey,
            ], $this->_levelData($amount, true))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response == '') {
            $this->error = 'CURL Error';
            return false;
        }
        $response_obj = json_decode($response);
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        }
        if (!property_exists($response_obj, 'result_code')) {
            $this->error = $response;
            return false;
        }
        if ($response_obj->result_code != 'A') {
            $this->error = $response;
            return false;
        }
        return new GPProcessResult(GPProcessResult::APPROVED, $response_obj->refnum, $response_obj->authcode, '', $response_obj->key);
    }

    private function _processUSAEPayTokenPayment($apiKey, $apiPin, $amount)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'command' => $this->paymentType,
                'amount' => number_format($amount, 2, '.', ''),
                'creditcard' => [
                    'number' => $this->ueCardToken
                ]
            ], $this->_levelData($amount))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response == '') {
            $this->error = 'CURL Error';
            return false;
        }
        $response_obj = json_decode($response);
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        }
        if (!property_exists($response_obj, 'result_code')) {
            $this->error = $response;
            return false;
        }
        if ($response_obj->result_code != 'A') {
            $this->error = $response;
            return false;
        }
        return new GPProcessResult(GPProcessResult::APPROVED, $response_obj->refnum, $response_obj->authcode, '', $response_obj->key);
    }

    private function _processUSAEPayEMVPayment($apiKey, $apiPin, $amount)
    {
        global $db;
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        if (!$this->_getUSAEPayEMVTerminalStatus($apiKey, $apiHash)) {
            return false;
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/paymentengine/payrequests",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'devicekey' => $this->emvDeviceKey,
                'command' => $this->paymentType,
                'amount' => number_format($amount, 2, '.', ''),
                'save_card' => true,
                'ignore_duplicate' => true,
                'timeout' => 60
            ], $this->_levelData($amount))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response_obj = json_decode($response);
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        } elseif (!property_exists($response_obj, 'key')) {
            $this->error = $response;
            return false;
        }
        $requestKey = $response_obj->key;
        $db->insert('gp_emv_logs', [
            'emv_id' => $this->emvProcessingID,
            'portal_id' => $this->portalID,
            'post_time' => date('Y-m-d H:i:s'),
            'request_key' => $requestKey,
            'response' => $response
        ]);
        $old_response = '';
        while (true) {
            sleep(1);
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://www-stage.usaepay.com/api/v2/paymentengine/payrequests/{$requestKey}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_USERPWD => $apiKey . ':' . $apiHash
            ));

            $response = curl_exec($curl);

            if ($old_response != $response) {
                $db->insert('gp_emv_logs', [
                    'emv_id' => $this->emvProcessingID,
                    'portal_id' => $this->portalID,
                    'post_time' => date('Y-m-d H:i:s'),
                    'request_key' => $requestKey,
                    'response' => $response
                ]);
                $old_response = $response;
            }

            curl_close($curl);
            $response_obj = json_decode($response);
            if (!$response_obj) {
                $this->error = $response;
                return false;
            }
            if (!property_exists($response_obj, 'status')) {
                $this->error = $response;
                break;
            }
            if (property_exists($response_obj, 'transaction')) {
                $transaction = $response_obj->transaction;
                if ($transaction->result_code == 'A') {
                    $cardBrands = [
                        'V' => 'VISA',
                        'M' => 'MASTERCARD',
                        'A' => 'AMEX',
                        'DS' => 'DISCOVER'
                    ];
                    $this->ueCardToken = $transaction->savedcard->key;
                    $this->cardType = strtoupper($transaction->savedcard->type);
                    if (array_key_exists($this->cardType, $cardBrands)) {
                        $this->cardType = $cardBrands[$this->cardType];
                    }
                    $this->cardNumber = strtoupper($transaction->savedcard->cardnumber);
                    $this->cardHolder = strtoupper($transaction->creditcard->cardholder);
                    $this->payType = self::TOKENPAY;
                    return new GPProcessResult(GPProcessResult::APPROVED, $transaction->refnum, $transaction->authcode, '', $response_obj->key);
                } else {
                    $this->error = $transaction->error;
                }
                break;
            }
        }
        return false;
    }

    private function _processUSAEPayChipPayment($apiKey, $apiPin, $amount)
    {
        global $db;
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        if (!$this->_getUSAEPayEMVTerminalStatus($apiKey, $apiHash)) {
            return false;
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/paymentengine/payrequests",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'devicekey' => $this->emvDeviceKey,
                'command' => $this->paymentType,
                'amount' => number_format($amount, 2, '.', ''),
                'save_card' => true,
                'ignore_duplicate' => true,
                'timeout' => 60
            ], $this->_levelData($amount))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        $db->insert('simple_usaepay_post_logs', ['post_time' => date('Y-m-d H:i:s'), 'request' => json_encode($_POST), 'response' => $response]);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
            curl_close($curl);
            return false;
        }
        curl_close($curl);
        $response_obj = json_decode($response);
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        } elseif (!property_exists($response_obj, 'key')) {
            $this->error = $response;
            return false;
        }
        $requestKey = $response_obj->key;
        $status = property_exists($response_obj, 'status') ? $response_obj->status : 'sending to device';
        // change pdt to est
        $expiration_obj = DateTime::createFromFormat('Y-m-d H:i:s', $response_obj->expiration, new DateTimeZone('America/Los_Angeles'));
        $expiration_obj->setTimezone(new DateTimeZone('America/New_York'));
        $chipID = $db->insert('gp_chip_logs', [
            'portal_id' => $this->portalID,
            'post_time' => date('Y-m-d H:i:s'),
            'request_key' => $requestKey,
            'api_key' => $apiKey,
            'api_pin' => $apiPin,
            'api_hash' => $apiHash,
            'transaction_id' => 0,
            'response' => $response,
            'expiration' => $expiration_obj->format('Y-m-d H:i:s'),
            'status' => $status
        ]);
        // print_r($response_obj);
        // exit;
        ob_end_clean();
        header("Connection: close");
        ignore_user_abort(true); // just to be safe
        ob_start();
        echo json_encode(['result' => 'Success', 'key' => $chipID, 'expiration' => $expiration_obj->format('Y-m-d H:i:s')]);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush(); // Strange behaviour, will not work
        flush();
        $old_response = $response;
        $old_status = $status;
        while (true) {
            sleep(1);
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://www-stage.usaepay.com/api/v2/paymentengine/payrequests/{$requestKey}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_USERPWD => $apiKey . ':' . $apiHash
            ));

            $response = curl_exec($curl);

            if ($old_response != $response) {
                $db->where('id', $chipID)->update('gp_chip_logs', [
                    'response' => $response
                ]);
                $db->insert('gp_chip_logs_dump', [
                    'chip_id' => $chipID,
                    'log_dt' => date('Y-m-d H:i:s'),
                    'response' => $response
                ]);
                $old_response = $response;
            }

            curl_close($curl);
            $response_obj = json_decode($response);
            if (!$response_obj) {
                $this->error = $response;
                $db->where('id', $chipID)->update('gp_chip_logs', [
                    'status' => 'curl error'
                ]);
                return false;
            }
            if (!property_exists($response_obj, 'status')) {
                $this->error = $response;
                $db->where('id', $chipID)->update('gp_chip_logs', [
                    'status' => 'unknown error'
                ]);
                break;
            } else {
                if ($old_status != $response_obj->status) {
                    $db->where('id', $chipID)->update('gp_chip_logs', [
                        'status' => $response_obj->status
                    ]);
                    $old_status = $response_obj->status;
                }
            }
            if (property_exists($response_obj, 'transaction')) {
                $transaction = $response_obj->transaction;
                if ($transaction->result_code == 'A') {
                    $cardBrands = [
                        'V' => 'VISA',
                        'M' => 'MASTERCARD',
                        'A' => 'AMEX',
                        'DS' => 'DISCOVER'
                    ];
                    $this->ueCardToken = $transaction->savedcard->key;
                    $this->cardType = strtoupper($transaction->savedcard->type);
                    if (array_key_exists($this->cardType, $cardBrands)) {
                        $this->cardType = $cardBrands[$this->cardType];
                    }
                    $this->cardNumber = strtoupper($transaction->savedcard->cardnumber);
                    $this->cardHolder = strtoupper($transaction->creditcard->cardholder);
                    $this->payType = self::TOKENPAY;
                    $db->where('id', $chipID)->update('gp_chip_logs', [
                        'status' => 'completing payment'
                    ]);
                    $this->chipID = $chipID;
                    return new GPProcessResult(GPProcessResult::APPROVED, $transaction->refnum, $transaction->authcode, '', $response_obj->key);
                } else {
                    $this->error = $transaction->error;
                    $db->where('id', $chipID)->update('gp_chip_logs', [
                        'status' => $response_obj->status
                    ]);
                }
                break;
            }
        }
        return false;
    }

    private function _getUSAEPayEMVTerminalStatus($apiKey, $apiHash)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/paymentengine/devices/{$this->emvDeviceKey}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response_obj = json_decode($response);
        if (!$response_obj) {
            $this->error = $response;
            return false;
        }
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
        } elseif (property_exists($response_obj, 'status')) {
            if ($response_obj->status == 'online' || $response_obj->status == 'connected') {
                return true;
            } elseif ($response_obj->status == 'offline') {
                $this->error = 'EMV Device is offline';
            } elseif ($response_obj->status == 'processing transaction') {
                $this->error = 'EMV Device is busy';
            } else {
                $this->error = 'EMV Device is unknown status - ' . $response_obj->status;
            }
        } else {
            $this->error = 'Unknown EMV Device Error';
        }
        return false;
    }

    private function _processUSAEPayManualPayment($apiKey, $apiPin, $amount)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'command' => $this->paymentType,
                'amount' => number_format($amount, 2, '.', ''),
                'creditcard' => [
                    'cardholder' => $this->cardHolder,
                    'number' => $this->cardNumber,
                    'expiration' => $this->cardExpire,
                    'cvc' => $this->cardCVV,
                    'avs_street' => $this->cardStreet,
                    'avs_zip' => $this->cardZip
                ],
                'save_card' => true,
                'ignore_duplicate' => true
            ], $this->_levelData($amount))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response == '') {
            $this->error = 'CURL Error';
            return false;
        }
        $response_obj = json_decode($response);
        if (!$response_obj) {
            $this->error = $response;
            return false;
        }
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        }
        if (!property_exists($response_obj, 'result_code')) {
            $this->error = $response;
            return false;
        }
        if ($response_obj->result_code != 'A') {
            $this->error = $response;
            return false;
        }
        if (!$this->ueCardToken) {
            $this->ueCardToken = $response_obj->savedcard->key;
        }
        return new GPProcessResult(GPProcessResult::APPROVED, $response_obj->refnum, $response_obj->authcode, '', $response_obj->key);
    }
    private function _processUSAEPayACHPayment($apiKey, $apiPin, $amount)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'command' => $this->paymentType,
                'amount' => number_format($amount, 2, '.', ''),
                'check' => [
                    'accountholder' => $this->achAccountName,
                    'routing' => $this->achRoutingNumber,
                    'account' => $this->achAccountNumber,
                    'account_type' => $this->achAccountType
                ],
                'ignore_duplicate' => true
            ], $this->_levelData($amount))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response == '') {
            $this->error = 'CURL Error';
            return false;
        }
        $response_obj = json_decode($response);
        if (!$response_obj) {
            $this->error = $response;
            return false;
        }
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        }
        if (!property_exists($response_obj, 'result_code')) {
            $this->error = $response;
            return false;
        }
        if ($response_obj->result_code != 'A') {
            $this->error = $response;
            return false;
        }
        return new GPProcessResult(GPProcessResult::APPROVED, $response_obj->refnum, $response_obj->authcode, '', $response_obj->key);
    }
    private function _processUSAEPaySwipePayment($apiKey, $apiPin, $amount)
    {
        $seed = rand() . time();
        $preHash = $apiKey . $seed . $apiPin;
        $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://usaepay.com/api/v2/transactions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array_merge([
                'command' => $this->paymentType,
                'amount' => number_format($amount, 2, '.', ''),
                'creditcard' => [
                    'magstripe' => $this->magStripe
                ],
                'save_card' => true,
                'ignore_duplicate' => true
            ], $this->_levelData($amount))),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response == '') {
            $this->error = 'CURL Error';
            return false;
        }
        $response_obj = json_decode($response);
        if (!$response_obj) {
            $this->error = $response;
            return false;
        }
        if (property_exists($response_obj, 'error')) {
            $this->error = $response_obj->error;
            return false;
        }
        if (!property_exists($response_obj, 'result_code')) {
            $this->error = $response;
            return false;
        }
        if ($response_obj->result_code != 'A') {
            $this->error = $response;
            return false;
        }
        if (!$this->ueCardToken) {
            $this->ueCardToken = $response_obj->savedcard->key;
        }
        return new GPProcessResult(GPProcessResult::APPROVED, $response_obj->refnum, $response_obj->authcode, '', $response_obj->key);
    }
    private function _processMXPayment($merchantID, $apiKey, $apiPin, $transKey, $amount)
    {
        if ($this->payType == self::MANUALPAY) {
            return $this->_processMXManualPayment($merchantID, $apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::SWIPEPAY) {
            return $this->_processMXSwipePayment($merchantID, $apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::TOKENPAY) {
            return $this->_processMXTokenPayment($merchantID, $apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::ACHPAY) {
            return $this->_processMXACHPayment($merchantID, $apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::CHIPPAY) {
            return $this->_processMXChipPayment($merchantID, $apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::EMVPAY) {
            return $this->_processMXEMVPayment($merchantID, $apiKey, $apiPin, $amount);
        } elseif ($this->payType == self::CUSTOMERPAY) {
            return $this->_processMXCustomerPayment($merchantID, $apiKey, $apiPin, $amount);
        }
        $this->error = $this->portalID . ' Transaction Error: No Payment Method';
        return false;
    }
    private function _processMXManualPayment($merchantID, $apiKey, $apiPin, $amount)
    {
        $p_result = false;
        $body = [];
        $body = [
            'merchantId' => $merchantID,
            'amount' => $amount,
            'paymentType' => $this->paymentType,
            'tenderType' => 'Card',
            'cardAccount' => [
                'number' => $this->cardNumber,
                'expiryMonth' => substr($this->cardExpire, 0, 2),
                'expiryYear' => substr($this->cardExpire, -2),
                'avsStreet' => $this->cardStreet,
                'avsZip' => $this->cardZip,
                'cvv' => $this->cardCVV
            ],
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment?echo=true",
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
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->id, $response_obj->authCode, '', $response_obj->paymentToken);
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }
    private function _processMXACHPayment($merchantID, $apiKey, $apiPin, $amount)
    {
        $p_result = false;
        $body = [];
        $body = [
            'merchantId' => $merchantID,
            'amount' => $amount,
            'paymentType' => $this->paymentType,
            'tenderType' => 'ACH',
            'bankAccount' => [
                'accountNumber' => $this->achAccountNumber,
                'routingNumber' => $this->achRoutingNumber,
                'type' => $this->achAccountType,
                'name' => $this->achAccountName
            ],
            'entryClass' => 'WEB',
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment?echo=true",
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
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->id, '', '', $response_obj->paymentToken);
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }
    private function _processMXSwipePayment($merchantID, $apiKey, $apiPin, $amount)
    {
        $p_result = false;
        $body = [];
        $body = [
            'merchantId' => $merchantID,
            'amount' => $amount,
            'paymentType' => $this->paymentType,
            'tenderType' => 'Card',
            'cardAccount' => [
                'magstripe' => $this->magStripe
            ],
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment?echo=true",
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
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->id, $response_obj->authCode, '', $response_obj->paymentToken);
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }
    private function _processMXEMVPayment($merchantID, $apiKey, $apiPin, $amount)
    {
        // Get token
        $token = $this->_getMXToken($merchantID, $apiKey, $apiPin);
        $replayID = getenv('GP_MX_EMV_REPLAY_ID_PREFIX') . $this->_getUniqID();
        global $db;
        $p_result = false;
        $body = [
            'amount' => $amount,
            'type' => 'Sale',
            'replayId' => $replayID
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api2.mxmerchant.com/terminal/v1/transaction/merchantid/' . $merchantID . '/terminalid/' . $this->emvDeviceKey,
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
                'Authorization: Bearer ' . $token
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errors')) {
                    $this->error = $response_obj->message;
                } else {
                    if (property_exists($response_obj, 'status')) {
                        if ($response_obj->status == 'SENTTOTERMINAL') {
                            $p_result = true;
                        } else {
                            $this->error = $response_obj->message;
                        }
                    } else {
                        $this->error = $response_obj->message;
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        if (!$p_result) {
            return false;
        }
        $db->insert('gp_emv_logs', [
            'emv_id' => $this->emvProcessingID,
            'portal_id' => $this->portalID,
            'post_time' => date('Y-m-d H:i:s'),
            'request_key' => $replayID,
            'response' => $response
        ]);
        // Check status for a min
        $p_result = false;
        $this->error = 'Timeout';
        $old_response = '';
        for ($step = 0; $step < 100; $step++) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.mxmerchant.com/checkout/v3/payment?merchantId=' . $merchantID . '&replayId=' . $replayID,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $apiKey . ':' . $apiPin
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            if ($old_response != $response && $response != '') {
                $db->insert('gp_emv_logs', [
                    'emv_id' => $this->emvProcessingID,
                    'portal_id' => $this->portalID,
                    'post_time' => date('Y-m-d H:i:s'),
                    'request_key' => $replayID,
                    'response' => $response
                ]);
                $old_response = $response;
            }
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                    break;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                        break;
                    } else {
                        if (property_exists($response_obj, 'cardAccount')) {
                            if (property_exists($response_obj->cardAccount, 'cardType')) {
                                $this->cardType = $response_obj->cardAccount->cardType;
                            }
                            if (property_exists($response_obj->cardAccount, 'last4')) {
                                $this->cardNumber = $response_obj->cardAccount->last4;
                            }
                        }
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->id, $response_obj->authCode, '', $response_obj->paymentToken);
                        break;
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                    break;
                }
            } elseif ($response != '') {
                $this->error = $response;
                break;
            }
            sleep(3);
        }
        if ($p_result === false) {
            $this->_cancelMXChipPayment($merchantID, $apiKey, $apiPin);
        }
        return $p_result;
    }
    private function _processMXChipPayment($merchantID, $apiKey, $apiPin, $amount)
    {
        // Get token
        $token = $this->_getMXToken($merchantID, $apiKey, $apiPin);
        $replayID = getenv('GP_MX_EMV_REPLAY_ID_PREFIX') . $this->_getUniqID();
        global $db;
        $p_result = false;
        $body = [
            'amount' => $amount,
            'type' => 'Sale',
            'replayId' => $replayID
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api2.mxmerchant.com/terminal/v1/transaction/merchantid/' . $merchantID . '/terminalid/' . $this->emvDeviceKey,
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
                'Authorization: Bearer ' . $token
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errors')) {
                    $this->error = $response_obj->message;
                } else {
                    if (property_exists($response_obj, 'status')) {
                        if ($response_obj->status == 'SENTTOTERMINAL') {
                            $p_result = true;
                        } else {
                            $this->error = $response_obj->message;
                        }
                    } else {
                        $this->error = $response_obj->message;
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        if (!$p_result) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $expiration = date("Y-m-d H:i:s", strtotime("+1 minutes"));
        $status = property_exists($response_obj, 'status') ? $response_obj->status : 'unknown status';
        $chipID = $db->insert('gp_chip_logs', [
            'portal_id' => $this->portalID,
            'post_time' => $now,
            'request_key' => $replayID,
            'api_key' => $apiKey,
            'api_pin' => $apiPin,
            'api_hash' => '',
            'transaction_id' => 0,
            'response' => $response,
            'expiration' => $expiration,
            'status' => $status
        ]);
        ob_end_clean();
        header("Connection: close");
        ignore_user_abort(true); // just to be safe
        ob_start();
        echo json_encode(['result' => 'Success', 'key' => $chipID, 'expiration' => $expiration]);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush(); // Strange behavior, will not work
        flush();

        // Check status for a min
        $p_result = false;
        $old_response = '';
        for ($step = 0; $step < 20; $step++) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.mxmerchant.com/checkout/v3/payment?merchantId=' . $merchantID . '&replayId=' . $replayID,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $apiKey . ':' . $apiPin
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            if ($old_response != $response && $response != '') {
                $db->where('id', $chipID)->update('gp_chip_logs', [
                    'response' => $response
                ]);
                $old_response = $response;
            }
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                    break;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                    } else {
                        if (property_exists($response_obj, 'cardAccount')) {
                            if (property_exists($response_obj->cardAccount, 'cardType')) {
                                $this->cardType = $response_obj->cardAccount->cardType;
                            }
                            if (property_exists($response_obj->cardAccount, 'last4')) {
                                $this->cardNumber = $response_obj->cardAccount->last4;
                            }
                        }
                        $this->chipID = $chipID;
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->id, $response_obj->authCode, '', $response_obj->paymentToken);
                        break;
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                    break;
                }
            } elseif ($response != '') {
                $this->error = $response;
                break;
            }
            sleep(3);
        }
        if ($p_result === false) {
            $this->_cancelMXChipPayment($merchantID, $apiKey, $apiPin);
            $this->error = $this->error || 'Timeout';
            $db->where('id', $chipID)->update('gp_chip_logs', [
                'status' => $this->error
            ]);
        }
        return $p_result;
    }
    private function _cancelMXChipPayment($merchantID, $apiKey, $apiPin)
    {
        $token = $this->_getMXToken($merchantID, $apiKey, $apiPin);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api2.mxmerchant.com/terminal/v1/transaction/merchantid/' . $merchantID . '/terminalid/' . $this->emvDeviceKey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                'Authorization: Bearer ' . $token
            )
        ));
        curl_exec($curl);
        curl_close($curl);
    }
    private function _getMXToken($merchantID, $apiKey, $apiPin)
    {
        global $db;
        $row = $db->where('mx_id', $merchantID)->where('mx_key', $apiKey)->getOne('mx_jwt_tokens');
        $now = new DateTime();
        if ($row) {
            $tokenTime = DateTime::createFromFormat('Y-m-d H:i:s', $row['created_dt']);
            $seconds = $now->getTimestamp() - $tokenTime->getTimestamp();
            if ($seconds < 82800) {
                return $row['token'];
            }
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api2.mxmerchant.com/security/v1/application/merchantId/' . $merchantID . '/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $responseObj = json_decode($response);
        if (!is_object($responseObj)) {
            return false;
        }
        if (property_exists($responseObj, 'jwtToken')) {
            $token = $responseObj->jwtToken;
            if ($row) {
                $db->where('id', $row['id'])->update('mx_jwt_tokens', ['token' => $token, 'created_dt' => $now->format('Y-m-d H:i:s')]);
            } else {
                $db->insert('mx_jwt_tokens', ['mx_id' => $merchantID, 'mx_key' => $apiKey, 'token' => $token, 'created_dt' => $now->format('Y-m-d H:i:s')]);
            }
            return $token;
        }
        return false;
    }
    private function _processMXTokenPayment($merchantID, $apiKey, $apiPin, $amount)
    {
        $p_result = false;
        $body = [];
        $body = [
            'merchantId' => $merchantID,
            'amount' => $amount,
            'paymentType' => $this->paymentType,
            'tenderType' => 'Card',
            'cardAccount' => [
                'token' => $this->mxCardToken
            ],
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment?echo=true",
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
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->id, $response_obj->authCode, '', $response_obj->paymentToken);
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }
    private function _processMXCustomerPayment($merchantID, $apiKey, $apiPin, $amount)
    {
        $p_result = false;
        $body = [];
        if ($this->cardType == self::ACHPAY) {
            $body = [
                'merchantId' => $merchantID,
                'amount' => $amount,
                'paymentType' => $this->paymentType,
                'tenderType' => 'ACH',
                'bankAccount' => [
                    'token' => $this->customerMethodKey
                ],
                'entryClass' => 'WEB',
            ];
        } else {
            $body = [
                'merchantId' => $merchantID,
                'amount' => $amount,
                'paymentType' => $this->paymentType,
                'tenderType' => 'Card',
                'cardAccount' => [
                    'token' => $this->customerMethodKey
                ],
            ];
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment?echo=true",
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
            CURLOPT_USERPWD => $apiKey . ':' . $apiPin
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'errorCode')) {
                    $this->error = $response_obj->message;
                } else {
                    if ($response_obj->status != 'Approved') {
                        $this->error = $response_obj->authMessage;
                    } else {
                        $p_result = new GPProcessResult(
                            GPProcessResult::APPROVED,
                            $response_obj->id,
                            property_exists($response_obj, 'authCode') ? $response_obj->authCode : '',
                            '',
                            $response_obj->paymentToken
                        );
                    }
                }
                if (property_exists($response_obj, 'details')) {
                    $this->error = $response_obj->details[0];
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCardPointPayment($merchantID, $cnpSite, $emvSite, $cnpKey, $emvKey, $amount)
    {
        if ($this->payType == self::MANUALPAY) {
            return $this->_processCardPointManualPayment($cnpSite, $merchantID, $cnpKey, $amount);
        } elseif ($this->payType == self::SWIPEPAY) {
            return $this->_processCardPointSwipePayment($cnpSite, $merchantID, $cnpKey, $amount);
        } elseif ($this->payType == self::TOKENPAY) {
            return $this->_processCardPointTokenPayment($cnpSite, $merchantID, $cnpKey, $amount);
        } elseif ($this->payType == self::ACHPAY) {
            return $this->_processCardPointACHPayment($cnpSite, $merchantID, $cnpKey, $amount);
        } elseif ($this->payType == self::CHIPPAY) {
            return $this->_processCardPointChipPayment($emvSite, $merchantID, $emvKey, $amount);
        } elseif ($this->payType == self::EMVPAY) {
            return $this->_processCardPointEMVPayment($emvSite, $merchantID, $emvKey, $amount);
        } elseif ($this->payType == self::CUSTOMERPAY) {
            // return $this->_processMXCustomerPayment($merchantID, $apiKey, $apiPin, $amount);
        }
        $this->error = $this->portalID . ' Transaction Error: No Payment Method';
        return false;
    }

    private function _processCardPointTokenPayment($site, $merchantID, $apiKey, $amount)
    {
        $p_result = false;
        $body = [
            "merchid" => $merchantID,
            "account" => $this->cpCardToken,
            "expiry" => $this->cardExpire,
            "amount" => round($amount * 100),
            "name" => $this->cardHolder,
            "ecomind" => "E",
            "capture" => $this->paymentType,
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardconnect/rest/auth",
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
                "Authorization: {$apiKey}"
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (!property_exists($response_obj, 'respstat')) {
                    $this->error = $response;
                } else {
                    if ($response_obj->respstat != 'A') {
                        $this->error = $response_obj->resptext;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->retref, $response_obj->authcode, '', '');
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCardPointManualPayment($site, $merchantID, $apiKey, $amount)
    {
        $p_result = false;
        // secure card
        $account = $this->_tokenizeCardPoint($site, ['account' => $this->cardNumber]);
        if ($account === false) {
            return false;
        }
        $body = [
            "merchid" => $merchantID,
            "account" => $account,
            "expiry" => $this->cardExpire,
            "cvv2" => $this->cardCVV,
            "postal" => $this->cardZip,
            "amount" => round($amount * 100),
            "name" => $this->cardHolder,
            "ecomind" => "E",
            "capture" => $this->paymentType,

        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardconnect/rest/auth",
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
                "Authorization: {$apiKey}"
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (!property_exists($response_obj, 'respstat')) {
                    $this->error = $response;
                } else {
                    if ($response_obj->respstat != 'A') {
                        $this->error = $response_obj->resptext;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->retref, $response_obj->authcode, '', '');
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCardPointSwipePayment($site, $merchantID, $apiKey, $amount)
    {
        $p_result = false;
        $body = [
            "merchid" => $merchantID,
            "track" => $this->magStripe,
            "amount" => round($amount * 100),
            "ecomind" => "E",
            "capture" => $this->paymentType,

        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardconnect/rest/auth",
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
                "Authorization: {$apiKey}"
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (!property_exists($response_obj, 'respstat')) {
                    $this->error = $response;
                } else {
                    if ($response_obj->respstat != 'A') {
                        $this->error = $response_obj->resptext;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->retref, $response_obj->authcode, '', '');
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCardPointACHPayment($site, $merchantID, $apiKey, $amount)
    {
        $p_result = false;
        // secure card
        $account = $this->_tokenizeCardPoint($site, ['account' => $this->achRoutingNumber . '/' . $this->achAccountNumber]);
        if ($account === false) {
            return false;
        }
        $body = [
            "merchid" => $merchantID,
            "account" => $account,
            "accttype" => $this->achAccountType == 'Savings' ? 'ESAV' : 'ECHK',
            "amount" => round($amount * 100),
            "name" => $this->achAccountName,
            "ecomind" => "E",
            "capture" => $this->paymentType,

        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardconnect/rest/auth",
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
                "Authorization: {$apiKey}"
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (!property_exists($response_obj, 'respstat')) {
                    $this->error = $response;
                } else {
                    if ($response_obj->respstat != 'A') {
                        $this->error = $response_obj->resptext;
                    } else {
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->retref, $response_obj->authcode, '', '');
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCardPointEMVPayment($site, $merchantID, $apiKey, $amount)
    {
        $p_result = false;
        // connect machine
        $session_key = false;
        $body = [
            "merchantId" => $merchantID,
            "hsn" => $this->emvDeviceKey,
            "force" => true
        ];
        $headers = [];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardpointe.com/api/v2/connect",
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
                "Authorization: {$apiKey}"
            )
        ));
        curl_setopt(
            $curl,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))] = trim($header[1]);

                return $len;
            }
        );
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($response_code == 200) {
                // get session key
                $session_key = substr($headers['x-cardconnect-sessionkey'], 0, 32);
            } else {
                if ($response != '') {
                    $response_obj = json_decode($response);
                    if (is_object($response_obj)) {
                        if (!property_exists($response_obj, 'errorMessage')) {
                            $this->error = $response;
                        } else {
                            $this->error = $response_obj->errorMessage;
                        }
                    } else {
                        $this->error = $response;
                    }
                } else {
                    $this->error = sprintf('Error response(%s)', $response_code);
                }
            }
        }
        curl_close($curl);
        if ($session_key == false) {
            return false;
        }
        // process payment
        $body = [
            "merchid" => $merchantID,
            "amount" => round($amount * 100),
            "hsn" => $this->emvDeviceKey,
            "includeSignature" => false,
            "includeAmountDisplay" => true,
            "beep" => true,
            "aid" => "credit",
            "confirmAmount" => false,
            "includeAVS" => false,
            "clearDisplayDelay" => 500,
            "capture" => $this->paymentType
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardconnect/rest/auth",
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
                "Authorization: {$apiKey}",
                "X-CardConnect-SessionKey: {$session_key}"
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (!property_exists($response_obj, 'respstat')) {
                    $this->error = $response;
                } else {
                    if ($response_obj->respstat != 'A') {
                        $this->error = $response_obj->resptext;
                    } else {
                        $this->cpCardToken = $response_obj->token;
                        $this->cardExpire = $response_obj->expiry;
                        $this->cardHolder = $response_obj->name;
                        $this->cardNumber = $this->cpCardToken;
                        $this->cardType = 'EMV';
                        $this->payType = self::TOKENPAY;
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->retref, $response_obj->authcode, '', '');
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCardPointChipPayment($site, $merchantID, $apiKey, $amount)
    {
        $p_result = false;
        // connect machine
        $session_key = false;
        $body = [
            "merchantId" => $merchantID,
            "hsn" => $this->emvDeviceKey,
            "force" => true
        ];
        $headers = [];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardpointe.com/api/v2/connect",
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
                "Authorization: {$apiKey}"
            )
        ));
        curl_setopt(
            $curl,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))] = trim($header[1]);

                return $len;
            }
        );
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($response_code == 200) {
                // get session key
                $session_key = substr($headers['x-cardconnect-sessionkey'], 0, 32);
            } else {
                if ($response != '') {
                    $response_obj = json_decode($response);
                    if (is_object($response_obj)) {
                        if (!property_exists($response_obj, 'errorMessage')) {
                            $this->error = $response;
                        } else {
                            $this->error = $response_obj->errorMessage;
                        }
                    } else {
                        $this->error = $response;
                    }
                } else {
                    $this->error = sprintf('Error response(%s)', $response_code);
                }
            }
        }
        curl_close($curl);
        if ($session_key == false) {
            return false;
        }
        global $db;
        $now = date('Y-m-d H:i:s');
        $expiration = date("Y-m-d H:i:s", strtotime("+1 minutes"));
        $status = 'Waiting chip card';
        $replayID = getenv('GP_CP_EMV_REPLAY_ID_PREFIX') . $this->_getUniqID();
        $chipID = $db->insert('gp_chip_logs', [
            'portal_id' => $this->portalID,
            'post_time' => $now,
            'request_key' => $replayID,
            'api_key' => $apiKey,
            'api_pin' => '',
            'api_hash' => '',
            'transaction_id' => 0,
            'response' => $response,
            'expiration' => $expiration,
            'status' => $status
        ]);
        ob_end_clean();
        header("Connection: close");
        ignore_user_abort(true); // just to be safe
        ob_start();
        echo json_encode(['result' => 'Success', 'key' => $chipID, 'expiration' => $expiration]);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush(); // Strange behavior, will not work
        flush();
        // process payment
        $body = [
            "merchid" => $merchantID,
            "amount" => round($amount * 100),
            "hsn" => $this->emvDeviceKey,
            "includeSignature" => false,
            "includeAmountDisplay" => true,
            "beep" => true,
            "aid" => "credit",
            "confirmAmount" => false,
            "includeAVS" => false,
            "clearDisplayDelay" => 500,
            "capture" => $this->paymentType
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardconnect/rest/auth",
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
                "Authorization: {$apiKey}",
                "X-CardConnect-SessionKey: {$session_key}"
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (!property_exists($response_obj, 'respstat')) {
                    $this->error = $response;
                } else {
                    if ($response_obj->respstat != 'A') {
                        $this->error = $response_obj->resptext;
                    } else {
                        $this->cpCardToken = $response_obj->token;
                        $this->cardExpire = $response_obj->expiry;
                        $this->cardHolder = $response_obj->name;
                        $this->cardNumber = $this->cpCardToken;
                        $this->cardType = 'EMV';
                        $this->payType = self::TOKENPAY;
                        $this->chipID = $chipID;
                        $p_result = new GPProcessResult(GPProcessResult::APPROVED, $response_obj->retref, $response_obj->authcode, '', '');
                    }
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        $db->where('id', $chipID)->update('gp_chip_logs', [
            'response' => $response
        ]);
        if ($p_result === false) {
            $db->where('id', $chipID)->update('gp_chip_logs', [
                'status' => $this->error
            ]);
        }

        return $p_result;
    }

    private function _tokenizeCardPoint($site, $account)
    {
        $p_result = false;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$site}.cardconnect.com/cardsecure/api/v1/ccn/tokenize",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_DNS_CACHE_TIMEOUT => 0,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($account),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if ($response_obj->errorcode == 0) {
                    $p_result = $response_obj->token;
                } else {
                    $this->error = $response_obj->message;
                }
            } else {
                $this->error = $response;
            }
        }
        curl_close($curl);
        return $p_result;
    }

    private function _processCashPayment($amount)
    {
        $ref = strtoupper(dechex(time()));
        return new GPProcessResult(GPProcessResult::APPROVED, $ref, '', '', '');
    }

    public function getResponse()
    {
        $result = [];
        if ($this->result == GPProcessResult::APPROVED) {
            $result = [
                'result' => $this->result,
                'error' => $this->error,
                'refID' => $this->cityRef,
                'authCode' => $this->cityAuth,
                'transactionID' => $this->transAllID,
                'cardLast4' => substr($this->cardNumber, -4),
                'amount' => $this->cityAmount
            ];
            if ($this->hasFeePayment) {
                $result['fee'] = $this->feeAmount;
            }
        } else {
            $result = [
                'result' => $this->result,
                'error' => $this->error
            ];
        }
        return $result;
    }
    private function _parseField($row, $field, $isArray = false)
    {
        switch ($field) {
            case 'func_gp_redline_address':
                $value = $this->_concatStr(', ', $row['account_address1'], $row['account_address2'], $row['account_city'], $row['account_state'], $row['account_zip']);
                break;
            case 'func_gp_rba_address':
                $value = $this->_concatStr(', ', $row['location'], $row['route'], $row['district']);
                break;
            case 'func_gp_redline_due_date':
                $value = date('m/d/Y', strtotime(trim($row['due_date'])));
                break;
            case 'func_gp_gas_bill_date':
                $value = date('m/d/Y', strtotime(trim($row['bill_date'])));
                break;
            case 'func_gp_gas_due_date':
                $value = date('m/d/Y', strtotime(trim($row['due_date'])));
                break;
            case 'gp_lgs_issue_date':
                $value = date('m/d/Y', strtotime(trim($row['issue_date'])));
                break;
            case 'gp_lgs_court_date':
                $value = date('m/d/Y', strtotime(trim($row['court_date'])));
                break;
            case 'gp_lgs_address':
                $value = $this->_concatStr(', ', $row['address'], $row['city'], $row['state'], $row['zip']);
                break;
            case 'lgs_case_number':
                if ($isArray) {
                    $value = $row['case_number'] . ' ($' . number_format($row['balance'], 2) . ')';
                } else {
                    $value = $row['case_number'];
                }
                break;
            case 'AUTC':
                if ($isArray) {
                    $value = $row['AUTC'] . ' ($' . number_format($row['BALAMT'], 2) . ')';
                } else {
                    $value = $row['AUTC'];
                }
                break;
            case 'func_gp_eta_court_name':
                $value = $this->_concatStr(' ', $row['AFNAME'], $row['AMNAME'], $row['ALNAME']);
                break;
            case 'func_gp_eta_court_od':
                $value = $this->_beautifyETADate(trim($row['AODATE']));
                break;
            case 'func_gp_eta_court_cd':
                $value = $this->_beautifyETADate(trim($row['ACDATE']));
                break;
            case 'func_gp_eta_court_address':
                $value = $this->_concatStr(', ', $row['AADDR1'], $row['AADDR2'], $row['AADDR3']);
                break;
            case 'func_gp_eta_utility_address':
                $value = $this->_concatStr(', ', $row['AUADD1'], $row['AUADD2'], $row['AUADD3']);
                break;
            case 'func_gp_eta_businesslicense_address':
                $value = $this->_concatStr(', ', $row['ABADD1'], $row['ABADD2'], $row['ABADD3']);
                break;
            case 'func_gp_iplow_name':
                $value = $this->_concatStr(', ', $row['LastName'], $row['FirstName']);
                break;
            case 'func_gp_iplow_address':
                $value = $this->_concatStr(', ', $row['Street'], $row['City'], $row['State'], $row['ZIP']);
                break;
            case 'func_gp_iplow_source':
                $value = $this->user == self::SMSUSER ? 'SMS' : '';
                break;
            case 'CaseNumber':
                if ($isArray && $this->varPartner == 'MSG') {
                    $value = $row['CaseNumber'] . ' ($' . number_format($row['Amount'], 2) . ')';
                } else {
                    $value = $row['CaseNumber'];
                }
                break;
            case 'func_gp_msg_cd':
                $value = $this->_beautifyMSGDate(trim($row['CourtDate']));
                break;
            case 'func_gp_msg_vd':
                $value = $this->_beautifyMSGDate(trim($row['ViolationDate']));
                break;
            case 'wrs_account_name':
                $value = $this->_concatStr(', ', $row['last_name'], $row['first_name']);
                break;
            case 'wrs_customer_id':
                $value = $this->_concatStr('-', $row['database_id'], $row['dealer_no'], $row['sale_no']);
                break;
            case 'wrs_phone':
                $value = $this->_formatPhone($row['cell_phone']);
                break;
            case 'wrs_auto_info':
                $value = $this->_concatStr(' ', $row['autos_year'], $row['autos_make'], $row['autos_model'], $row['autos_trim'], $row['autos_vin']);
                break;
            default:
                $value = trim($row[$field]);
                break;
        }
        return trim($value);
    }
    private function _prepareData()
    {
        global $db;
        $this->integratedPairs = [
            [
                'MSG', '', [
                    'Table' => 'incoming',
                    'Search' => 'id',
                    'Amount' => 'Amount',
                    'Fields' => [
                        'PersonName' => 'VName',
                        'standard1' => 'CaseNumber',
                        'standard2' => 'CitationNumber',
                        'custom1' => 'func_gp_msg_cd',
                        'custom2' => 'func_gp_msg_vd',
                        'custom3' => 'Description'
                    ]
                ]
            ],
            [
                'HCSS', '', [
                    'Table' => 'hcss_incoming',
                    'Search' => 'id',
                    'Amount' => 'TotalFine',
                    'Fields' => [
                        'PersonName' => 'ViolatorName',
                        'standard1' => 'CauseNumber',
                        'standard2' => 'TicketNumber',
                        'custom1' => 'CourtDate',
                        'custom2' => 'IssuedDate',
                        'custom3' => 'OffenseDescription'
                    ]
                ]
            ],
            [
                'IPLOW', '', [
                    'Table' => 'iplow_incoming',
                    'Search' => 'ID',
                    'Amount' => 'TotalBalanceDue',
                    'Fields' => [
                        'PersonName' => 'func_gp_iplow_name',
                        'standard1' => 'DOB',
                        'standard2' => 'CaseNumber',
                        'custom1' => 'func_gp_iplow_address',
                        'custom2' => 'DLStateNumber',
                        'custom4' => 'CourtCodeDescription',
                        'custom5' => 'TotalAssessmentAmount',
                        'custom6' => 'TotalBalanceDue',
                        'list1' => 'func_gp_iplow_source',
                    ]
                ]
            ],
            [
                'NewRedLine', '', [
                    'Table' => 'redline_integrations',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'account_name',
                        'standard1' => 'account_number',
                        'standard2' => 'func_gp_redline_address',
                        'custom1' => 'invoice',
                        'custom2' => 'func_gp_redline_due_date'
                    ]
                ]
            ],
            [
                'BBI', '', [
                    'Table' => 'bbi_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'account_name',
                        'standard1' => 'account_number'
                    ]
                ]
            ],
            [
                'GAS', '', [
                    'Table' => 'gas_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'account_name',
                        'standard1' => 'account_number',
                        'standard2' => 'address',
                        'custom1' => 'func_gp_gas_bill_date',
                        'custom2' => 'func_gp_gas_due_date'
                    ]
                ]
            ],
            [
                'RVS', '', [
                    'Table' => 'rvs_integrations',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'customer_name',
                        'standard1' => 'customer_number',
                        'standard2' => 'service_address',
                        'custom1' => 'customer_phone',
                        'custom2' => 'customer_email'
                    ]
                ]
            ],
            [
                'LGS', '', [
                    'Table' => 'gp_lgs_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'violator_name',
                        'standard1' => 'lgs_case_number',
                        'standard2' => 'ticket_number',
                        'custom1' => 'gp_lgs_issue_date',
                        'custom2' => 'gp_lgs_court_date',
                        'custom3' => 'offense_description',
                        'custom4' => 'gp_lgs_address'
                    ]
                ]
            ],
            [
                'PGIS', '', [
                    'Table' => 'pgis_integrations',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'var_key',
                        'standard1' => 'biller_guid',
                        'standard2' => 'invoice_id'
                    ]
                ]
            ],
            [
                'ETA', 'court', [
                    'Table' => 'etanew_integrations',
                    'Search' => 'ID',
                    'Amount' => 'BALAMT',
                    'Fields' => [
                        'PersonName' => 'func_gp_eta_court_name',
                        'standard1' => 'AUTC',
                        'standard2' => 'ACASE',
                        'custom1' => 'func_gp_eta_court_od',
                        'custom2' => 'func_gp_eta_court_cd',
                        'custom3' => 'ADESC',
                        'custom4' => 'func_gp_eta_court_address'
                    ]
                ]
            ],
            [
                'ETA', 'utility', [
                    'Table' => 'etanew_integrations',
                    'Search' => 'ID',
                    'Amount' => 'BALAMT',
                    'Fields' => [
                        'PersonName' => 'AUNAME',
                        'standard1' => 'AUACC',
                        'standard2' => 'func_gp_eta_utility_address'
                    ]
                ]
            ],
            [
                'ETA', 'businesslicense', [
                    'Table' => 'etanew_integrations',
                    'Search' => 'ID',
                    'Amount' => 'BALAMT',
                    'Fields' => [
                        'PersonName' => 'ABNAME',
                        'standard1' => 'ABACC',
                        'standard2' => 'func_gp_eta_businesslicense_address',
                        'custom1' => 'ADESC1',
                        'custom2' => 'ADESC2',
                        'custom3' => 'ADESC3',
                        'custom4' => 'ADESC4',
                        'custom5' => 'ADESC5',
                        'custom6' => 'ADESC6'
                    ]
                ]
            ],
            [
                'RBA', 'Sewer', [
                    'Table' => 'rba_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'account_name',
                        'standard1' => 'account_number',
                        'standard2' => 'func_gp_rba_address'
                    ]
                ]
            ],
            [
                'RBA', 'General', [
                    'Table' => 'rba_incoming_general',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'account_name',
                        'standard1' => 'account_number',
                        'standard2' => 'account_phone'
                    ]
                ]
            ],
            [
                'WRS', '', [
                    'Table' => 'gp_int_autos_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'wrs_account_name',
                        'standard1' => 'cust_account_no',
                        'standard2' => 'wrs_customer_id',
                        'custom1' => 'wrs_phone',
                        'custom2' => 'email',
                        'custom3' => 'wrs_auto_info'
                    ]
                ]
            ],
            [
                'WYQ', '', [
                    'Table' => 'gp_int_wyq_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'party',
                        'standard1' => 'receipt_number',
                        'standard2' => 'location',
                        'custom1' => 'case_number'
                    ]
                ]
            ],
            [
                'UOP', 'waterbill', [
                    'Table' => 'gp_int_uop_incoming',
                    'Search' => 'id',
                    'Amount' => 'balance',
                    'Fields' => [
                        'PersonName' => 'account_name',
                        'standard1' => 'account_number',
                        'standard2' => 'address',
                        'custom1' => 'phone',
                        'custom2' => 'email'
                    ]
                ]
            ]
        ];
        if ($this->integratedID) {
            foreach ($this->integratedPairs as $pair) {
                if ($pair[0] == $this->varPartner && ($pair[1] == '' || $pair[1] == $this->department)) {
                    $this->data = [];
                    $integrated = $pair[2];
                    if (!is_array($this->integratedID)) {
                        $this->integratedRecord = $db->where($integrated['Search'], $this->integratedID)->getOne($integrated['Table']);
                        if (!$this->integratedRecord) {
                            echoErrorAndExit(500, 'The record specified could not be found. Please try again.');
                        }
                        if ($this->integratedRecord[$integrated['Amount']] > $this->amount) {
                            $this->isPartial = true;
                        }
                        foreach ($integrated['Fields'] as $key => $field) {
                            $this->data[$key] = $this->_parseField($this->integratedRecord, $field);
                        }
                    } elseif (is_array($this->integratedID)) {
                        $this->integratedRecords = [];
                        foreach ($this->integratedID as $entryID) {
                            $integratedRow = $db->where($integrated['Search'], $entryID)->getOne($integrated['Table']);
                            if (!$integratedRow) {
                                echoErrorAndExit(500, 'The record specified could not be found. Please try again.');
                            }
                            $this->integratedRecords[] = $integratedRow;
                            foreach ($integrated['Fields'] as $key => $field) {
                                $value = $this->_parseField($integratedRow, $field, true);
                                if (isset($this->data[$key])) {
                                    $this->data[$key][] = $value;
                                } else {
                                    $this->data[$key] = [$value];
                                }
                            }
                        }
                        foreach ($this->data as $key => $value) {
                            $this->data[$key] = implode(PHP_EOL, array_unique($value));
                        }
                    }
                    break;
                }
            }
        }
        if (!$this->data) {
            echoErrorAndExit(500, 'The record specified could not be found. Please try again.');
        }
        //prepare payment details with form
        $this->formData = [];
        $saForm = $db->where('form_name', $this->formName)->getOne('sa_fields');
        if ($saForm) {
            foreach ([
                'default_name' => 'PersonName',
                'standard1_label' => 'standard1',
                'standard2_label' => 'standard2',
                'custom1_label' => 'custom1',
                'custom2_label' => 'custom2',
                'custom3_label' => 'custom3',
                'custom4_label' => 'custom4',
                'custom5_label' => 'custom5',
                'custom6_label' => 'custom6',
                'custom7_label' => 'custom7',
                'custom8_label' => 'custom8',
                'list1_label' => 'list1',
                'list2_label' => 'list2'
            ] as $label_key => $data_key) {
                if ($saForm[$label_key] && isset($this->data[$data_key])) {
                    $this->formData[] = [$saForm[$label_key], $this->data[$data_key]];
                }
            }
        }
    }
    private function _saveCardVault()
    {
        $cityCustomerID = false;
        global $db;
        $this->cityCardId = $this->feeCardId = 0;
        if ($this->gateway == self::MX) {
            $cityCustomerID = $this->_createMXCustomer($this->cardHolder, $this->cityMerchant, $this->cityKey, $this->cityPin);
        } elseif ($this->gateway == self::USAEPAY) {
            $cityCustomerID = $this->_createUSAEPayCustomer($this->cardHolder, $this->cityKey, $this->cityPin);
        } else {
            return 'No supported gateway';
        }
        if (!$cityCustomerID) {
            return 'Card vault error (1)';
        }
        $db->insert('gp_gateway_autopay_accounts', [
            'gateway' => $this->gateway,
            'merchant_key' => $this->gateway == self::MX ? $this->cityMerchant : $this->cityKey,
            'customer_id' => $cityCustomerID,
            'created_dt' => date('Y-m-d H:i:s')
        ]);
        if ($this->gateway == self::MX) {
            $cardObj = $this->_createMXCardVault($cityCustomerID, $this->cityKey, $this->cityPin);
        } else {
            $cardObj = $this->_createUSAEPayCardVault($cityCustomerID, $this->cityKey, $this->cityPin);
        }
        if ($cardObj) {
            $this->cityCardId = $db->insert('gp_gateway_autopay_card_tokens', [
                'account_id' => $cityCustomerID,
                'card_id' => $cardObj['card_id'],
                'card_last4' => $cardObj['last4'],
                'card_type' => $cardObj['card_type'],
                'card_token' => $cardObj['token'],
                'created_dt' => date('Y-m-d H:i:s'),
                'updated_dt' => date('Y-m-d H:i:s'),
            ]);
        } else {
            return 'Card vault error (2)';
        }
        if ($this->hasFeePayment) {
            $feeCustomerID = false;
            if ($this->gateway == self::MX) {
                $feeCustomerID = $this->_createMXCustomer($this->cardHolder, $this->feeMerchant, $this->feeKey, $this->feePin);
            } else {
                $feeCustomerID = $this->_createUSAEPayCustomer($this->cardHolder, $this->feeKey, $this->feePin);
            }
            if (!$feeCustomerID) {
                return 'Card vault error (3)';
            }
            $db->insert('gp_gateway_autopay_accounts', [
                'gateway' => $this->gateway,
                'merchant_key' => $this->gateway == self::MX ? $this->feeMerchant : $this->feeKey,
                'customer_id' => $feeCustomerID,
                'created_dt' => date('Y-m-d H:i:s')
            ]);
            if ($this->gateway == self::MX) {
                $cardObj = $this->_createMXCardVault($feeCustomerID, $this->feeKey, $this->feePin);
            } else {
                $cardObj = $this->_createUSAEPayCardVault($feeCustomerID, $this->feeKey, $this->feePin);
            }
            if ($cardObj) {
                $this->feeCardId = $db->insert('gp_gateway_autopay_card_tokens', [
                    'account_id' => $feeCustomerID,
                    'card_id' => $cardObj['card_id'],
                    'card_last4' => $cardObj['last4'],
                    'card_type' => $cardObj['card_type'],
                    'card_token' => $cardObj['token'],
                    'created_dt' => date('Y-m-d H:i:s'),
                    'updated_dt' => date('Y-m-d H:i:s'),
                ]);
            } else {
                return 'Card vault error (4)';
            }
        }
        return true;
    }

    private function _createMXCardVault($customerID, $merchantKey, $merchantSecret)
    {
        $result = false;
        if ($this->payType == self::ACHPAY) {
            $url = "https://api.mxmerchant.com/checkout/v3/customerbankaccount?echo=true&id={$customerID}";
            $body = [
                'accountNumber' => $this->achAccountNumber,
                'routingNumber' => $this->achRoutingNumber,
                'name' => $this->achAccountName,
                'type' => $this->achAccountType
            ];
        } else {
            $url = "https://api.mxmerchant.com/checkout/v3/customercardaccount?echo=true&id={$customerID}";
            $body = [
                'number' => $this->cardNumber,
                'name' => $this->cardHolder,
                'expiryMonth' => substr($this->cardExpire, 0, 2),
                'expiryYear' => substr($this->cardExpire, -2),
                'avsZip' => $this->cardZip,
                'cvv' => $this->cardCVV,
                'avsStreet' => $this->cardStreet
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
            $response_obj = json_decode($response);
            if (is_object($response_obj)) {
                if (property_exists($response_obj, 'token')) {
                    if ($this->payType == self::ACHPAY) {
                        $result = [
                            'card_id' => $response_obj->id,
                            'token' => $response_obj->token,
                            'last4' => substr($this->achAccountNumber, -4),
                            'card_type' => 'ACH'
                        ];
                    } else {
                        $result = [
                            'card_id' => $response_obj->id,
                            'token' => $response_obj->token,
                            'last4' => $response_obj->last4,
                            'card_type' => $response_obj->cardType
                        ];
                    }
                }
            }
        }
        curl_close($curl);
        return $result;
    }
    private function _createMXCustomer($lastName, $merchantId, $merchantKey, $merchantSecret)
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
    private function _createUSAEPayCustomer($lastName, $apiKey, $apiPin)
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
    private function _createUSAEPayCardVault($customerKey, $apiKey, $apiPin)
    {
        $result = false;
        if ($this->payType == self::ACHPAY) {
            $body = [
                'method_name' => 'Recurring ACH',
                'cardholder' => $this->achAccountName,
                'routing' => $this->achRoutingNumber,
                'account' => $this->achAccountNumber,
                'account_type' => $this->achAccountType,
                'pay_type' => 'check'
            ];
        } else {
            $body = [
                'method_name' => 'Recurring Card',
                'number' => $this->cardNumber,
                'cardholder' => $this->cardHolder,
                'expiration' => $this->cardExpire,
                'pay_type' => 'cc',
                'avs_street' => $this->cardStreet,
                'avs_postalcode' => $this->cardZip,
                'cvc' => $this->cardCVV
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
        if ($this->payType == self::ACHPAY) {
            $result = [
                'card_id' => $response_obj->key,
                'token' => $response_obj->key,
                'last4' => substr($this->achAccountNumber, -4),
                'card_type' => 'ACH'
            ];
        } else {
            $result = [
                'card_id' => $response_obj->key,
                'token' => $response_obj->key,
                'last4' => $response_obj->ccnum4last,
                'card_type' => $response_obj->card_type
            ];
        }
        return $result;
    }
}

class GPProcessResult
{
    public $result;
    public $error;
    public $refID;
    public $authCode;
    public $payToken;
    const APPROVED = 'Approved';
    const AUTHORIZED = 'Authorized';
    const ERROR = 'Error';
    public function __construct($result, $refID, $authCode, $error = '', $payToken = '')
    {
        $this->result = $result;
        $this->refID = $refID;
        $this->authCode = $authCode;
        $this->error = $error;
        $this->payToken = $payToken;
    }
}
