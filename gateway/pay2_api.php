<?php
class GP_VRTransaction
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $portal_id;
    /**
     * @var string
     */
    private $gateway;
    /**
     * @var string
     */
    private $usa_key;
    /**
     * @var string
     */
    private $usa_pin;
    /**
     * @var string
     */
    private $usa_fee_key;
    /**
     * @var string
     */
    private $usa_fee_pin;
    /**
     * @var string
     */
    private $usa_flat_fee_key;
    /**
     * @var string
     */
    private $usa_flat_fee_pin;
    /**
     * @var string
     */
    private $mx_merchant;
    /**
     * @var string
     */
    private $mx_key;
    /**
     * @var string
     */
    private $mx_secret;
    /**
     * @var string
     */
    private $pay_token;
    /**
     * @var string
     */
    private $mx_fee_merchant;
    /**
     * @var string
     */
    private $mx_fee_key;
    /**
     * @var string
     */
    private $mx_fee_secret;
    /**
     * @var string
     */
    private $fee_pay_token;
    /**
     * @var string
     */
    private $mx_flat_fee_merchant;
    /**
     * @var string
     */
    private $mx_flat_fee_key;
    /**
     * @var string
     */
    private $mx_flat_fee_secret;
    /**
     * @var string
     */
    private $flat_fee_pay_token;
    /**
     * @var string
     */
    private $tran_ref;
    /**
     * @var string
     */
    private $fee_ref;
    /**
     * @var string
     */
    private $flat_fee_ref;
    /**
     * @var string
     */
    private $flat_fee_auth;
    /**
     * @var string
     */
    private $new_tran_ref;
    /**
     * @var string
     */
    private $new_fee_ref;
    /**
     * @var string
     */
    private $new_flat_fee_ref;
    /**
     * @var string
     */
    private $new_tran_auth;
    /**
     * @var string
     */
    private $new_fee_auth;
    /**
     * @var string
     */
    private $new_flat_fee_auth;
    /**
     * @var string
     */
    private $reason;
    /**
     * @var string
     */
    private $new_amount;
    /**
     * @var string
     */
    private $new_fee;
    /**
     * @var string
     */
    private $new_flat_fee;
    /**
     * @var string
     */
    private $new_tran_time;
    /**
     * @var DateTime
     */
    private $new_tran_time_obj;
    /**
     * @var int
     */
    private $new_id;
    /**
     * @var int
     */
    private $new_integrated_id;
    /**
     * @var string
     */
    private $var_partner;
    /**
     * @var bool
     */
    private $absorb_fee;
    /**
     * @var bool
     */
    private $merge_fee;
    /**
     * @var string
     */
    private $percent_fee;
    /**
     * @var string
     */
    private $min_fee;
    /**
     * @var string
     */
    public $amount;
    /**
     * @var string
     */
    private $fee;
    /**
     * @var string
     */
    private $flat_fee;
    /**
     * @var string
     */
    private $flat_fee_description;
    /**
     * @var bool
     */
    private $has_flat_fee;
    /**
     * @var bool
     */
    private $is_split_flat_fee;

    /**
     * @var string
     */
    private $error;

    const USAEPAY = 'USA EPay';
    const MX = 'MX';
    public function __construct($row)
    {
        global $db;
        $this->id = $row['GP_id'];
        $this->amount = $row['amount_city'];
        $this->fee = $row['amount_fee'] ? $row['amount_fee'] : '';
        $this->portal_id = $row['Portal_Id'];
        $portal = $db->where('Portal_Id', $this->portal_id)->getOne('zoho_products');
        if (!$portal) {
            $portal = [
                'Service_Fee' => '4.5',
                'Minimum' => '3',
                'source_olp' => '',
                'Service_Fee_Source_Key' => '',
                'mxid' => '',
                'GatewayUser_Login' => '',
                'Gateway_User_Password' => '',
                'fee_mxid' => '',
                'mx_gp_fee_user' => '',
                'mx_gp_fee_pass' => '',
                'Var_Partner' => ''
            ];
        }
        $this->var_partner = $portal['Var_Partner'];

        $this->tran_ref = $row['TransIDCity'];
        $this->absorb_fee = $row['absorb_fee'] == 'yes';
        $this->merge_fee = $row['merge_fee'] == 'yes';
        if (!$this->absorb_fee && !$this->merge_fee) {
            $this->fee_ref = $row['TransIDFee'];
            $this->percent_fee = $portal['Service_Fee'];
            $this->min_fee = $portal['Minimum'];
        }
        // $gateway_field = unserialize($row['gateway']);
        $this->gateway = $row['gateway'];
        if ($this->_is_usaepay()) {
            $this->usa_key = $portal['source_olp'];
            $this->usa_pin = '8888';
            if (!$this->absorb_fee && !$this->merge_fee) {
                $this->usa_fee_key = $portal['Service_Fee_Source_Key'];
                $this->usa_fee_pin = '8888';
            }
        }
        if ($this->_is_mx()) {
            $mx_pay_tokens = json_decode($row['source_name'], true);
            if (!$mx_pay_tokens) {
                $mx_pay_tokens = ['', ''];
            }
            $this->mx_merchant = $portal['mxid'];
            $this->mx_key = $portal['GatewayUser_Login'];
            $this->mx_secret = $portal['Gateway_User_Password'];
            $this->pay_token = $mx_pay_tokens[0];
            if (!$this->absorb_fee && !$this->merge_fee) {
                $this->mx_fee_merchant = $portal['fee_mxid'];
                $this->mx_fee_key = $portal['mx_gp_fee_user'];
                $this->mx_fee_secret = $portal['mx_gp_fee_pass'];
                $this->fee_pay_token = $mx_pay_tokens[1];
            }
        }
        if ($row['fromfile']) {
            $flatFeeData = json_decode($row['fromfile'], true);
            if ($flatFeeData) {
                $this->has_flat_fee = true;
                $this->flat_fee = $flatFeeData[0];
                $this->flat_fee_description = $flatFeeData[1];
                if ($flatFeeData[2] == 'flat_fee_split') {
                    $this->is_split_flat_fee = true;
                    // $flatFeeTran=$db->where('tran_id',$this->id)->where('kind','flat_fee')->getOne('gp_trans');
                    $this->flat_fee_ref = $flatFeeData[4];
                    $this->flat_fee_auth = $flatFeeData[5];
                    $this->flat_fee_pay_token = $flatFeeData[6];
                    if ($this->_is_usaepay()) {
                        $this->usa_flat_fee_key = $portal['flatfee_usa_key'];
                        $this->usa_flat_fee_pin = '8888';
                    }
                    if ($this->_is_mx()) {
                        $this->mx_flat_fee_merchant = $portal['flatfee_mx_id'];
                        $this->mx_flat_fee_key = $portal['flatfee_mx_key'];
                        $this->mx_flat_fee_secret = $portal['flatfee_mx_secret'];
                    }
                }
            }
        }
        // Update source key and secrets
        $gp_trans = $db->where('tran_id', $this->id)->get('gp_trans');
        foreach ($gp_trans as $record) {
            if ($record['kind'] == 'city') {
                $this->gateway = $record['gateway'];
                if ($this->gateway == self::USAEPAY) {
                    $this->usa_key = $record['merchant_key'];
                    $this->usa_pin = $record['merchant_secret'];
                } elseif ($this->gateway == self::MX) {
                    $this->mx_merchant = $record['merchant_id'];
                    $this->mx_key = $record['merchant_key'];
                    $this->mx_secret = $record['merchant_secret'];
                }
            } elseif ($record['kind'] == 'fee') {
                if ($this->gateway == self::USAEPAY) {
                    $this->usa_fee_key = $record['merchant_key'];
                    $this->usa_fee_pin = $record['merchant_secret'];
                } elseif ($this->gateway == self::MX) {
                    $this->mx_fee_merchant = $record['merchant_id'];
                    $this->mx_fee_key = $record['merchant_key'];
                    $this->mx_fee_secret = $record['merchant_secret'];
                }
            } elseif ($record['kind'] == 'flat_fee') {
                if ($this->gateway == self::USAEPAY) {
                    $this->usa_flat_fee_key = $record['merchant_key'];
                    $this->usa_flat_fee_pin = $record['merchant_secret'];
                } elseif ($this->gateway == self::MX) {
                    $this->mx_flat_fee_merchant = $record['merchant_id'];
                    $this->mx_flat_fee_key = $record['merchant_key'];
                    $this->mx_flat_fee_secret = $record['merchant_secret'];
                }
            }
        }
    }
    private function _is_mx()
    {
        return $this->gateway == self::MX;
    }
    public function get_transaction_status()
    {
        if ($this->_is_usaepay()) {
            return $this->_get_usaepay_transaction_status($this->usa_key, $this->usa_pin, $this->tran_ref);
        }
        if ($this->_is_mx()) {
            return $this->_get_mx_transaction_status($this->mx_key, $this->mx_secret, $this->tran_ref);
        }
    }

    public function refund_settled_transaction($amount, $reason)
    {
        global $db;

        $this->new_amount = $amount;
        $this->reason = $reason;
        $result = ['result' => 'Error'];
        $past_total_refunded_amount = $db->where('Status', 'Refunded')->where('Reference_Number', $this->id)->getValue('trans_all', '-SUM(amount_city)');
        if (round($amount + $past_total_refunded_amount - $this->amount, 2) > 0.01) {
            $result['trans'] = ['result' => 'error', 'msg' => 'Refund amount over original amount.'];
            return $result;
        }
        if (!$this->absorb_fee) {
            $fee = $amount * $this->percent_fee / 100;
            // if ($fee < $this->min_fee) {
            //     $fee = $this->min_fee;
            // }

            if (round(abs($amount + $past_total_refunded_amount - $this->amount), 2) < 0.01) {
                $past_total_refunded_fee = $db->where('Status', 'Refunded')->where('Reference_Number', $this->id)->getValue('trans_all', '-SUM(amount_fee)');
                $fee = $this->fee - $past_total_refunded_fee;
            }
            $fee = round($fee, 2);
            $this->new_fee = $fee;
            if ($this->merge_fee) {
                $amount += $fee;
            }
        }

        // Check Full Refund to Decide Flat Fee
        if ($this->is_split_flat_fee && $this->has_flat_fee) {
            if (round(abs($amount + $past_total_refunded_amount - $this->amount), 2) < 0.01) {
                $this->new_flat_fee = $this->flat_fee;
            }
        }

        if ($this->_is_usaepay()) {
            if ($this->_refund_usaepay_transaction($this->usa_key, $this->usa_pin, $this->tran_ref, $amount, 'tran')) {
                $result['trans'] = ['result' => 'refunded'];
                $result['result'] = 'Success';
                $this->_notify_trans_refunded();
                if (!$this->absorb_fee && $this->merge_fee && $fee > 0) {
                    $this->_notify_fee_refunded();
                }
                $result['trans']['id'] = $this->new_id;
                $result['trans']['auth'] = $this->new_tran_auth;
                $result['trans']['amount'] = $this->new_amount;
                if (!$this->absorb_fee && !$this->merge_fee && $fee > 0) {
                    // refund fee transaction
                    $fee_status = $this->_get_usaepay_transaction_status($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref);
                    if ($fee_status == 'S' || $fee_status == 'P') {
                        // refund full fee transaction
                        if ($this->_refund_usaepay_transaction($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref, $fee)) {
                            $result['fee'] = ['result' => 'refunded'];
                            $this->_notify_fee_refunded();
                        } else {
                            $result['fee'] = ['result' => 'error', 'msg' => 'Fee transaction refund error'];
                        }
                    }
                }
                if ($this->new_flat_fee > 0) {
                    $flat_fee_status = $this->_get_usaepay_transaction_status($this->usa_flat_fee_key, $this->usa_flat_fee_pin, $this->flat_fee_ref);
                    if ($flat_fee_status == 'S' || $flat_fee_status == 'P') {
                        //refund flat fee
                        if ($this->_refund_usaepay_transaction($this->usa_flat_fee_key, $this->usa_flat_fee_pin, $this->flat_fee_ref, $this->new_flat_fee, 'flat_fee')) {
                            $result['flat_fee'] = ['result' => 'refunded'];
                            $this->_notify_flat_fee_refunded();
                        } else {
                            $result['flat_fee'] = ['result' => 'error', 'msg' => $this->flat_fee_description . ' transaction refund error'];
                        }
                    }
                }
            } else {
                $result['trans'] = ['result' => 'error', 'msg' => $this->error];
            }
        }
        if ($this->_is_mx()) {
            if ($this->_refund_mx_transaction($this->mx_merchant, $this->mx_key, $this->mx_secret, $this->pay_token, $amount, 'tran')) {
                $result['trans'] = ['result' => 'refunded'];
                $result['result'] = 'Success';
                $this->_notify_trans_refunded();
                if (!$this->absorb_fee && $this->merge_fee && $fee > 0) {
                    $this->_notify_fee_refunded();
                }
                $result['trans']['id'] = $this->new_id;
                if (!$this->absorb_fee && !$this->merge_fee && $fee > 0) {
                    // refund fee transaction
                    $fee_status = $this->_get_mx_transaction_status($this->mx_fee_key, $this->mx_fee_secret, $this->fee_ref);

                    if ($fee_status == 'S' || $fee_status == 'P') {
                        // refund full fee transaction
                        if ($this->_refund_mx_transaction($this->mx_fee_merchant, $this->mx_fee_key, $this->mx_fee_secret, $this->fee_pay_token, $fee)) {
                            $result['fee'] = ['result' => 'refunded'];
                            $this->_notify_fee_refunded();
                        } else {
                            $result['fee'] = ['result' => 'error', 'msg' => 'Fee transaction refund error'];
                        }
                    }
                }
                if ($this->new_flat_fee > 0) {
                    $flat_fee_status = $this->_get_mx_transaction_status($this->mx_flat_fee_key, $this->mx_flat_fee_secret, $this->flat_fee_ref);
                    if ($flat_fee_status == 'S' || $flat_fee_status == 'P') {
                        if ($this->_refund_mx_transaction($this->mx_flat_fee_merchant, $this->mx_flat_fee_key, $this->mx_flat_fee_secret, $this->flat_fee_pay_token, $this->new_flat_fee, 'flat_fee')) {
                            $result['flat_fee'] = ['result' => 'refunded'];
                            $this->_notify_flat_fee_refunded();
                        } else {
                            $result['flat_fee'] = ['result' => 'error', 'msg' => $this->flat_fee_description . ' transaction refund error'];
                        }
                    }
                }
            } else {
                $result['trans'] = ['result' => 'error', 'msg' => 'Transaction refund error'];
            }
        }
        return $result;
    }

    private function _notify_trans_refunded()
    {
        global $db;
        $ori_transaction = $db->where('GP_id', $this->id)->getOne('trans_all');
        unset($ori_transaction['GP_id']);
        unset($ori_transaction['fromfile']);
        $ori_transaction['amount_city'] = '-' . $this->new_amount;
        $ori_transaction['TransIDCity'] = $this->new_tran_ref;
        $ori_transaction['authcode_city'] = $this->new_tran_auth;
        $ori_transaction['void_reason'] = $this->reason;
        $ori_transaction['tdate_city'] = $this->new_tran_time;
        $ori_transaction['Status'] = 'Refunded';
        $ori_transaction['amount_fee'] = '';
        $ori_transaction['TransIDFee'] = '';
        $ori_transaction['authcode_fee'] = '';
        $ori_transaction['Reference_Number'] = $this->id;
        $db->insert('trans_all', $ori_transaction);
        $this->new_id = $db->getInsertId();
        $portal_row = $db->where('Portal_Id', $this->portal_id)->where('integrated', 'true')->getOne('zoho_products');
        if ($portal_row) {
            $this->_notify_integrated_refunded();
        }
    }

    private function _notify_integrated_refunded()
    {
        // TODO multi ticket refund
        global $db;
        $pairs = [
            'NewRedLine' => [
                'table' => 'redline_transactions',
                'id' => 'id',
                'updates' => [
                    'amount' => -$this->new_amount,
                    'post_date' => substr($this->new_tran_time, 0, 10),
                    'post_time' => substr($this->new_tran_time, -8),
                    'ref_id' => $this->new_tran_ref,
                    'status' => 'Refunded',
                    'trans_all_id' => $this->new_id
                ],
                'search' => 'trans_all_id'
            ],
            'HCSS' => [
                'table' => 'hcss_transactions',
                'id' => 'id',
                'updates' => [
                    'PaidAmount' => -$this->new_amount,
                    'PaidDate' => $this->new_tran_time_obj->format('m/d/Y'),
                    'PaidTime' => $this->new_tran_time_obj->format('H:i'),
                    'ConfirmationNumber' => $this->new_tran_auth,
                    'status' => 'Refunded',
                    'PaymentGUID' => $this->new_id,
                    'active' => 'yes'
                ],
                'search' => 'PaymentGUID'
            ],
            'LGS' => [
                'table' => 'gp_lgs_transactions',
                'id' => 'id',
                'updates' => [
                    'amount' => -$this->new_amount,
                    'pay_dt' => $this->new_tran_time,
                    'status' => 'Refunded',
                    'auth_code' => $this->new_tran_auth,
                    'trans_all_id' => $this->new_id
                ],
                'search' => 'trans_all_id'
            ],
            'MSG' => [
                'table' => 'transactions',
                'id' => 'id',
                'updates' => [
                    'amount' => -$this->new_amount,
                    'tdate' => $this->new_tran_time,
                    'TransactionID' => $this->new_tran_ref,
                    'authcode' => $this->new_fee_auth,
                    'status' => 'REFUNDED',
                    'session' => $this->new_id
                ],
                'search' => 'session'
            ],
            'IPLOW' => [
                'table' => 'iplow_transactions',
                'id' => 'id',
                'updates' => [
                    'paid_amount' => -$this->new_amount,
                    'paid_time' => $this->new_tran_time,
                    'ref_id' => $this->new_tran_ref,
                    'auth_code' => $this->new_fee_auth,
                    'pay_type' => 'Refund',
                    'trans_all_id' => $this->new_id
                ],
                'search' => 'trans_all_id'
            ]
        ];
        if (array_key_exists($this->var_partner, $pairs)) {
            $pair = $pairs[$this->var_partner];
            // Get Record
            $ori_integrated = $db->where($pair['search'], $this->id)->getOne($pair['table']);
            if ($ori_integrated) {
                unset($ori_integrated[$pair['id']]);
                foreach ($pair['updates'] as $key => $value) {
                    $ori_integrated[$key] = $value;
                }
                $db->insert($pair['table'], $ori_integrated);
            }
        }
    }
    private function _notify_fee_refunded()
    {
        global $db;
        $db->where('GP_id', $this->new_id)->update('trans_all', [
            'amount_fee' => '-' . $this->new_fee,
            'TransIDFee' => $this->new_fee_ref,
            'authcode_fee' => $this->new_fee_auth
        ]);
        $this->_notify_integrated_fee_refunded();
    }
    private function _notify_flat_fee_refunded()
    {
        global $db;
        $db->where('GP_id', $this->new_id)->update('trans_all', [
            'fromfile' => json_encode(['-' . $this->new_fee, $this->flat_fee_description, $this->new_flat_fee_ref, $this->new_flat_fee_auth])
        ]);
        $this->_notify_integrated_fee_refunded();
    }
    private function _notify_integrated_fee_refunded()
    {
        // TODO
    }


    public function void_pending_transaction($reason)
    {
        $result = ['result' => 'Error'];
        if ($this->_is_usaepay()) {
            if ($this->_void_pending_usaepay_transaction($this->usa_key, $this->usa_pin, $this->tran_ref)) {
                $result['trans'] = ['result' => 'voided'];
                $result['result'] = 'Success';
                $this->_notify_trans_voided($reason);
                if (!$this->absorb_fee && $this->merge_fee) {
                    $result['fee'] = ['result' => 'voided'];
                    $this->_notify_fee_voided();
                }
                if (!$this->absorb_fee && !$this->merge_fee) {
                    // void or refund fee transaction
                    $fee_status = $this->_get_usaepay_transaction_status($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref);
                    if ($fee_status == 'P') {
                        // void fee transaction
                        if ($this->_void_pending_usaepay_transaction($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref)) {
                            $result['fee'] = ['result' => 'voided'];
                            $this->_notify_fee_voided();
                        } else {
                            $result['fee'] = ['result' => 'error', 'msg' => 'Fee transaction void error'];
                        }
                    }
                    if ($fee_status == 'S') {
                        // refund full fee transaction
                        if ($this->_refund_usaepay_transaction($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref, 0)) {
                            $result['fee'] = ['result' => 'voided'];
                            $this->_notify_fee_voided();
                        } else {
                            $result['fee'] = ['result' => 'error', 'msg' => 'Fee transaction refund error'];
                        }
                    }
                }
                if ($this->is_split_flat_fee && $this->has_flat_fee) {
                    $flat_fee_status = $this->_get_usaepay_transaction_status($this->usa_flat_fee_key, $this->usa_flat_fee_pin, $this->flat_fee_ref);
                    if ($flat_fee_status == 'P') {
                        if ($this->_void_pending_usaepay_transaction($this->usa_flat_fee_key, $this->usa_flat_fee_pin, $this->flat_fee_ref)) {
                            $result['flat_fee'] = ['result' => 'voided'];
                            $this->_notify_flat_fee_voided();
                        } else {
                            $result['flat_fee'] = ['result' => 'error', 'msg' => $this->flat_fee_description . ' transaction void error'];
                        }
                    }
                    if ($flat_fee_status == 'S') {
                        if ($this->_refund_usaepay_transaction($this->usa_flat_fee_key, $this->usa_flat_fee_pin, $this->flat_fee_ref, 0)) {
                            $result['flat_fee'] = ['result' => 'voided'];
                            $this->_notify_flat_fee_voided();
                        } else {
                            $result['flat_fee'] = ['result' => 'error', 'msg' => $this->flat_fee_description . ' transaction refund error'];
                        }
                    }
                }
            } else {
                $result['trans'] = ['result' => 'error', 'msg' => $this->error];
            }
        }

        if ($this->_is_mx()) {
            if ($this->_void_pending_mx_transaction($this->mx_key, $this->mx_secret, $this->tran_ref)) {
                $result['trans'] = ['result' => 'voided'];
                $result['result'] = 'Success';
                $this->_notify_trans_voided($reason);
                if (!$this->absorb_fee && !$this->merge_fee) {
                    // void or refund fee transaction
                    $fee_status = $this->_get_mx_transaction_status($this->mx_fee_key, $this->mx_fee_secret, $this->fee_ref);
                    if ($fee_status == 'P') {
                        // void fee transaction
                        if ($this->_void_pending_mx_transaction($this->mx_fee_key, $this->mx_fee_secret, $this->fee_ref)) {
                            $result['fee'] = ['result' => 'voided'];
                            $this->_notify_fee_voided();
                        } else {
                            $result['fee'] = ['result' => 'error', 'msg' => 'Fee transaction void error'];
                        }
                    }
                    if ($fee_status == 'S') {
                        // refund full fee transaction
                        if ($this->_refund_mx_transaction($this->mx_fee_merchant, $this->mx_fee_key, $this->mx_fee_secret, $this->fee_pay_token, $this->fee)) {
                            $result['fee'] = ['result' => 'voided'];
                            $this->_notify_fee_voided();
                        } else {
                            $result['fee'] = ['result' => 'error', 'msg' => 'Fee transaction refund error'];
                        }
                    }
                }
                if ($this->is_split_flat_fee && $this->has_flat_fee) {
                    $flat_fee_status = $this->_get_mx_transaction_status($this->mx_flat_fee_key, $this->mx_flat_fee_secret, $this->flat_fee_ref);
                    if ($flat_fee_status == 'P') {
                        // void flat_fee transaction
                        if ($this->_void_pending_mx_transaction($this->mx_flat_fee_key, $this->mx_flat_fee_secret, $this->flat_fee_ref)) {
                            $result['flat_fee'] = ['result' => 'voided'];
                            $this->_notify_flat_fee_voided();
                        } else {
                            $result['flat_fee'] = ['result' => 'error', 'msg' => $this->flat_fee_description . ' transaction void error'];
                        }
                    }
                    if ($flat_fee_status == 'S') {
                        // refund full flat_fee transaction
                        if ($this->_refund_mx_transaction($this->mx_flat_fee_merchant, $this->mx_flat_fee_key, $this->mx_flat_fee_secret, $this->flat_fee_pay_token, $this->flat_fee)) {
                            $result['flat_fee'] = ['result' => 'voided'];
                            $this->_notify_flat_fee_voided();
                        } else {
                            $result['flat_fee'] = ['result' => 'error', 'msg' => $this->flat_fee_description . ' transaction refund error'];
                        }
                    }
                }
            } else {
                $result['trans'] = ['result' => 'error', 'msg' => 'Transaction void error'];
            }
        }
        return $result;
    }
    private function _notify_trans_voided($reason)
    {
        global $db;
        $db->where('GP_id', $this->id)->update('trans_all', [
            'amount_city' => '-' . $this->amount,
            'Status' => 'Voided',
            'amount_fee' => '',
            'void_reason' => $reason
        ]);
        $portal_row = $db->where('Portal_Id', $this->portal_id)->where('integrated', 'true')->getOne('zoho_products');
        if ($portal_row) {
            $this->_notify_integrated_voided();
        }
    }
    private function _notify_integrated_voided()
    {
        // TODO Partial Void
        $this->_remove_integrated_transaction();
    }
    private function _remove_integrated_transaction()
    {
        global $db;
        if ($this->var_partner == 'NewRedLine') {
            $ori = $db->where('trans_all_id', $this->id)->getOne('redline_transactions');
            if ($ori) {
                unset($ori['id']);
                $ori['amount'] = -$ori['amount'];
                $ori['status'] = 'Voided';
                $ori['post_date'] = date('Y-m-d');
                $ori['post_time'] = date('H:i:s');
                $db->insert('redline_transactions', $ori);
            }
        }

        if ($this->var_partner == 'HCSS') {
            $oris = $db->where('PaymentGUID', $this->id)->where('status', 'Approved')->get('hcss_transactions');
            if ($oris) {
                foreach ($oris as $ori) {
                    if ($ori['active'] == 'no') {
                        unset($ori['id']);
                        $ori['PaidAmount'] = -$ori['PaidAmount'];
                        $ori['status'] = 'Voided';
                        $ori['active'] = 'yes';
                        $ori['PaidDate'] = date('m/d/Y');
                        $ori['PaidTime'] = date('H:i');
                        $db->insert('hcss_transactions', $ori);
                    } else {
                        $db->where('id', $ori['id'])->delete('hcss_transactions');
                    }
                }
            }
        }

        if ($this->var_partner == 'LGS') {
            $ori = $db->where('trans_all_id', $this->id)->getOne('gp_lgs_transactions');
            if ($ori) {
                unset($ori['id']);
                $ori['amount'] = -$ori['amount'];
                $ori['status'] = 'Voided';
                $ori['pay_dt'] = date('Y-m-d H:i:s');
                $db->insert('gp_lgs_transactions', $ori);
            }
        }
        if ($this->var_partner == 'MSG') {
            $db->where('session', $this->id)->where('trans_type', 'GOVT')->delete('transactions');
        }
        if ($this->var_partner == 'IPLOW') {
            $db->where('trans_all_id', $this->id)->delete('iplow_transactions');
        }
    }
    private function _notify_fee_voided()
    {
        global $db;
        $db->where('GP_id', $this->id)->update('trans_all', [
            'amount_fee' => '-' . $this->fee
        ]);
    }
    private function _notify_flat_fee_voided()
    {
        global $db;
        $db->where('GP_id', $this->id)->update('trans_all', [
            'fromfile' => json_encode(['-' . $this->flat_fee, $this->flat_fee_description, 'flat_fee_split', 'flat_fee_third', $this->flat_fee_ref, $this->flat_fee_auth, $this->flat_fee_pay_token])
        ]);
    }
    private function _get_mx_transaction_status($key, $secret, $refID)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment/" . $refID,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERPWD => $key . ':' . $secret
        ));

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        if (!$response) {
            return 'E';
        }
        if (isset($response['errorCode'])) {
            return 'E';
        }
        if (isset($response['status']) && isset($response['authMessage'])) {
            $APPROVED_AUTH_MSG = 'Approved or completed successfully';
            $APPROVED_AUTH_MSG1 = 'Approved';
            if ($response['status'] == 'Settled' && (substr($response['authMessage'], 0, strlen($APPROVED_AUTH_MSG)) === $APPROVED_AUTH_MSG || $response['authMessage'] === $APPROVED_AUTH_MSG1)) {
                return 'S';
            }
            if ($response['status'] == 'Approved' && (substr($response['authMessage'], 0, strlen($APPROVED_AUTH_MSG)) === $APPROVED_AUTH_MSG || $response['authMessage'] === $APPROVED_AUTH_MSG1)) {
                return 'P';
            }
            if ($response['status'] == 'Voided' && (substr($response['authMessage'], 0, strlen($APPROVED_AUTH_MSG)) === $APPROVED_AUTH_MSG || $response['authMessage'] === $APPROVED_AUTH_MSG1)) {
                return 'V';
            }
        }
        return 'E';
    }

    private function _get_usaepay_transaction_status($key, $pin, $ref)
    {

        $wsdl = 'https://www.usaepay.com/soap/gate/0AE595C1/usaepay.wsdl';
        $client = new SoapClient($wsdl);
        $seed = time() . rand();
        $clear = $key . $seed . $pin;
        $hash = sha1($clear);
        $token = [
            'SourceKey' => $key,
            'PinHash' => [
                'Type' => 'sha1',
                'Seed' => $seed,
                'HashValue' => $hash
            ],
            'ClientIP' => $_SERVER['REMOTE_ADDR'],
        ];
        try {
            $status = $client->getTransaction($token, $ref);
            // print_r($status);
            if ($status->Status == 'Voided' && $status->TransactionType == 'Voided Sale') {
                return 'V';
            }
            if ($status->Status == 'Authorized (Pending Settlement)' && $status->TransactionType == 'Sale') {
                return 'P';
            }
            if ($status->Status == 'Settled' && $status->TransactionType == 'Sale') {
                return 'S';
            }
        } catch (SoapFault $e) {
            // return 'E'; //$e->getMessage();
        }
        return 'E';
    }
    private function _void_pending_mx_transaction($key, $secret, $ref)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mxmerchant.com/checkout/v3/payment/" . $ref,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_USERPWD => $key . ':' . $secret
        ));

        curl_exec($curl);

        curl_close($curl);
        $status = $this->_get_mx_transaction_status($key, $secret, $ref);
        return $status == 'V';
    }
    private function _void_pending_usaepay_transaction($key, $pin, $ref)
    {
        $wsdl = 'https://www.usaepay.com/soap/gate/0AE595C1/usaepay.wsdl';
        $client = new SoapClient($wsdl);
        $seed = time() . rand();
        $clear = $key . $seed . $pin;
        $hash = sha1($clear);
        $token = [
            'SourceKey' => $key,
            'PinHash' => [
                'Type' => 'sha1',
                'Seed' => $seed,
                'HashValue' => $hash
            ],
            'ClientIP' => $_SERVER['REMOTE_ADDR'],
        ];
        try {
            $result = $client->voidTransaction($token, $ref);
            if ($result) {
                return true;
            }
        } catch (SoapFault $e) {
            $this->error = $e->getMessage();
            // return $e->getMessage();
        }
        return false;
    }
    public function getError()
    {
        return $this->error;
    }
    private function _refund_mx_transaction($mid, $key, $secret, $token, $amount, $type = 'fee')
    {
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
            CURLOPT_POSTFIELDS => json_encode([
                'merchantId' => $mid,
                'amount' => '-' . $amount,
                'tenderType' => 'Card',
                'paymentToken' => $token
            ]),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $key . ':' . $secret
        ));
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        if (!$response) {
            return false;
        }
        if (isset($response['errorCode'])) {
            return false;
        }
        if (isset($response['status'])) {
            if ($response['status'] == 'Approved') {
                if ($type == 'fee') {
                    $this->new_fee_ref = $response['id'];
                    $this->new_fee_auth = $response['authCode'];
                } elseif ($type == 'flat_fee') {
                    $this->new_flat_fee_ref = $response['id'];
                    $this->new_flat_fee_auth = $response['authCode'];
                } else {
                    $this->new_tran_ref = $response['id'];
                    $this->new_tran_auth = $response['authCode'];
                    $this->new_tran_time = date('Y-m-d H:i:s');
                    $this->new_tran_time_obj = new DateTime('now');
                }
                return true;
            }
        }
        return false;

        // print_r($response);
    }
    private function _refund_usaepay_transaction($key, $pin, $ref, $amount, $type = 'fee')
    {
        $wsdl = 'https://www.usaepay.com/soap/gate/0AE595C1/usaepay.wsdl';
        $client = new SoapClient($wsdl);
        $seed = time() . rand();
        $clear = $key . $seed . $pin;
        $hash = sha1($clear);
        $token = [
            'SourceKey' => $key,
            'PinHash' => [
                'Type' => 'sha1',
                'Seed' => $seed,
                'HashValue' => $hash
            ],
            'ClientIP' => $_SERVER['REMOTE_ADDR'],
        ];
        global $db;
        try {
            $result = $client->refundTransaction($token, $ref, $amount);
            if ($result) {
                if ($result->ResultCode == 'A') {
                    if ($type == 'fee') {
                        $this->new_fee_ref = $result->RefNum;
                        $this->new_fee_auth = $result->AuthCode;
                    } elseif ($type == 'flat_fee') {
                        $this->new_flat_fee_ref = $result->RefNum;
                        $this->new_flat_fee_auth = $result->AuthCode;
                    } else {
                        $this->new_tran_ref = $result->RefNum;
                        $this->new_tran_auth = $result->AuthCode;
                        $this->new_tran_time = date('Y-m-d H:i:s');
                        $this->new_tran_time_obj = new DateTime('now');
                    }
                    return true;
                }
            }
        } catch (SoapFault $e) {
            logAPIError($e->getMessage());
            $this->error = $e->getMessage();
        }
        return false;
    }
    private function _is_usaepay()
    {
        return $this->gateway == self::USAEPAY;
    }

    public function capture_authorized_transaction_without_change()
    {
        $result = [];
        if ($this->_is_usaepay()) {
            if ($this->_capture_authorized_usaepay_transaction_without_change($this->usa_key, $this->usa_pin, $this->tran_ref)) {
                $result['trans'] = ['result' => 'captured'];
                $this->_notify_trans_captured_without_change();
                if (!$this->absorb_fee && !$this->merge_fee) {
                    if ($this->_capture_authorized_usaepay_transaction_without_change($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref)) {
                        $result['fee'] = ['result' => 'captured'];
                    } else {
                        $result['fee'] = ['result' => 'error', 'msg' => $this->error];
                    }
                }
            } else {
                $result['trans'] = ['result' => 'error', 'msg' => $this->error];
            }
        }
        if ($this->_is_mx()) {
            $result['trans'] = ['result' => 'error', 'msg' => 'Not supported'];
        }
        return $result;
    }

    public function capture_authorized_transaction($amount)
    {
        $result = [];
        if (!$this->absorb_fee) {
            // Increase amount
            $fee = $amount * $this->percent_fee / 100;
            if ($fee < $this->min_fee) {
                $fee = $this->min_fee;
            }
            $fee = round($fee, 2);
            if ($this->merge_fee) {
                $amount += $fee;
                $amount = round($amount, 2);
            }
        }
        if ($this->_is_usaepay()) {
            if ($this->_capture_authorized_usaepay_transaction($this->usa_key, $this->usa_pin, $this->tran_ref, $amount)) {
                $result['trans'] = ['result' => 'captured'];
                $this->_notify_trans_captured($amount);
                if (!$this->absorb_fee && !$this->merge_fee) {
                    if ($this->_capture_authorized_usaepay_transaction($this->usa_fee_key, $this->usa_fee_pin, $this->fee_ref, $fee)) {
                        $result['fee'] = ['result' => 'captured'];
                        $this->_notify_fee_captured($fee);
                    } else {
                        $result['fee'] = ['result' => 'error', 'msg' => $this->error];
                    }
                }
            } else {
                $result['trans'] = ['result' => 'error', 'msg' => $this->error];
            }
        }
        if ($this->_is_mx()) {
            $result['trans'] = ['result' => 'error', 'msg' => 'Not supported'];
        }
        return $result;
    }

    private function _notify_trans_captured($amount)
    {
        global $db;
        $db->where('GP_id', $this->id)->update('trans_all', [
            'amount_city' => $amount,
            'Status' => 'Approved'
        ]);
    }
    private function _notify_trans_captured_without_change()
    {
        global $db;
        $db->where('GP_id', $this->id)->update('trans_all', [
            'Status' => 'Approved'
        ]);
    }

    private function _notify_fee_captured($fee)
    {
        global $db;
        $db->where('GP_id', $this->id)->update('trans_all', [
            'amount_fee' => $fee
        ]);
    }

    private function _capture_authorized_usaepay_transaction($apiKey, $apiPin, $ref, $amount)
    {
        $result = false;
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
            CURLOPT_POSTFIELDS => json_encode([
                'command' => 'capture',
                'amount' => number_format($amount, 2, '.', ''),
                'refnum' => $ref
            ]),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (!$response_obj) {
                $this->error = $response;
            } else {
                if (getSafeProperty($response_obj, 'result_code') == 'A') {
                    $result = true;
                } else {
                    $this->error = getSafeProperty($response_obj, 'error', '');
                }
            }
        }
        curl_close($curl);
        return $result;
    }
    private function _capture_authorized_usaepay_transaction_without_change($apiKey, $apiPin, $ref)
    {
        $result = false;
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
            CURLOPT_POSTFIELDS => json_encode([
                'command' => 'capture',
                'refnum' => $ref
            ]),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ),
            CURLOPT_USERPWD => $apiKey . ':' . $apiHash
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->error = curl_error($curl);
        } else {
            $response_obj = json_decode($response);
            if (!$response_obj) {
                $this->error = $response;
            } else {
                if (getSafeProperty($response_obj, 'result_code') == 'A') {
                    $result = true;
                } else {
                    $this->error = getSafeProperty($response_obj, 'error', '');
                }
            }
        }
        curl_close($curl);
        return $result;
    }
}
