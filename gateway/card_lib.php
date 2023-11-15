<?php
class MagStripe
{

    /** @var  string */
    private $dataString;

    /** @var string */
    private $account;

    /** @var  int */
    private $expYear;

    /** @var  int */
    private $expMonth;

    /** @var  string */
    private $name;

    /** @var  string[] */
    private $tracks;

    /**
     * MagStripe constructor. Receives the raw data string and parses it.
     *
     * @param $dataString
     */
    public function __construct($dataString)
    {
        $this->dataString = $dataString;
        $this->parseTracks();
        $this->extractTrackInfo();
    }

    /**
     * Parse the data string into the tracks following the ISO7811 format. For more information
     * about the data string, check the link below.
     * @link http://www.card-device.com/files/201603/20160309030103777.pdf
     * @throws Exception
     */
    private function parseTracks()
    {
        preg_match_all('/%(.+?)\?;(.+?)\?(\+(.+?)\?)?/', $this->dataString, $this->tracks, PREG_SET_ORDER);
        if (empty($this->tracks)) {
            throw new Exception('Invalid format for data string');
        }
        $this->tracks = $this->tracks[0];
        if (!empty($this->tracks[3])) {
            $this->tracks[3] = $this->tracks[4];
            unset($this->tracks[4]);
        }
        array_splice($this->tracks, 0, 1);
    }

    /**
     * Parse each track individually to extract the info from the card. Do validation
     * on the credit card number in each track,  expiry dates and check for inconsistencies.
     * @throws Exception
     */
    private function extractTrackInfo()
    {
        $track1 = $this->tracks[0];
        if ($track1[0] != 'B' && $track1[0] != 'b') {
            throw new Exception('Wrong format for first track');
        }
        $track1 = explode('^', substr($track1, 1));
        if (count($track1) != 3) {
            throw new Exception('Wrong format for first track');
        }
        $this->account = $track1[0];
        $name = explode('/', $track1[1]);
        $data = $track1[2];
        $this->expYear = substr($data, 0, 2);
        $this->expMonth = substr($data, 2, 2);

        $this->name = count($name) > 1 ? sprintf('%s %s', trim($name[1]), trim($name[0])) : trim($name[0]);

        $track2 = explode('=', $this->tracks[1]);
        if (count($track2) != 2) {
            throw new Exception('Wrong format for third track');
        }
        $data = $track2[1];
        if ($this->expYear != substr($data, 0, 2) || $this->expMonth != substr($data, 2, 2)) {
            throw new Exception('Expiration dates from both tracks do not match');
        }

        // validate the card numbers
        if ($this->account != $track2[0]) {
            throw new Exception('Credit card number mismatch in tracks.');
        }
        $this->validateCardNumber($this->account);
        $this->validateCardNumber($track2[0]);
    }

    /**
     * Delegate the card number validation
     * @param $number
     * @throws Exception
     */
    private function validateCardNumber($number)
    {
        $number = preg_replace('[^0-9]', '', $number);
        $validation = CreditCard::validCreditCard($number);
        if (empty($validation['valid'])) {
            throw new Exception('Invalid credit card number.');
        }
    }

    /**
     * @return string
     */
    public function getDataString()
    {
        return $this->dataString;
    }

    /**
     * @return string
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return int
     */
    public function getExpYear()
    {
        return $this->expYear;
    }

    /**
     * @return int
     */
    public function getExpMonth()
    {
        return $this->expMonth;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \string[]
     */
    public function getTracks()
    {
        return $this->tracks;
    }
}

class CreditCard
{
    protected static $cards = array(
        // Debit cards must come first, since they have more specific patterns than their credit-card equivalents.

        'visaelectron' => array(
            'type' => 'visaelectron',
            'pattern' => '/^4(026|17500|405|508|844|91[37])/',
            'length' => array(16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'maestro' => array(
            'type' => 'maestro',
            'pattern' => '/^(5(018|0[23]|[68])|6(39|7))/',
            'length' => array(12, 13, 14, 15, 16, 17, 18, 19),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'forbrugsforeningen' => array(
            'type' => 'forbrugsforeningen',
            'pattern' => '/^600/',
            'length' => array(16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'dankort' => array(
            'type' => 'dankort',
            'pattern' => '/^5019/',
            'length' => array(16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        // Credit cards
        'visa' => array(
            'type' => 'visa',
            'pattern' => '/^4/',
            'length' => array(13, 16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'mastercard' => array(
            'type' => 'mastercard',
            'pattern' => '/^(5[0-5]|2[2-7])/',
            'length' => array(16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'amex' => array(
            'type' => 'amex',
            'pattern' => '/^3[47]/',
            'format' => '/(\d{1,4})(\d{1,6})?(\d{1,5})?/',
            'length' => array(15),
            'cvcLength' => array(3, 4),
            'luhn' => true,
        ),
        'dinersclub' => array(
            'type' => 'dinersclub',
            'pattern' => '/^3[0689]/',
            'length' => array(14),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'discover' => array(
            'type' => 'discover',
            'pattern' => '/^6([045]|22)/',
            'length' => array(16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
        'unionpay' => array(
            'type' => 'unionpay',
            'pattern' => '/^(62|88)/',
            'length' => array(16, 17, 18, 19),
            'cvcLength' => array(3),
            'luhn' => false,
        ),
        'jcb' => array(
            'type' => 'jcb',
            'pattern' => '/^35/',
            'length' => array(16),
            'cvcLength' => array(3),
            'luhn' => true,
        ),
    );

    public static function validCreditCard($number, $type = null)
    {
        $ret = array(
            'valid' => false,
            'number' => '',
            'type' => '',
        );

        // Strip non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        if (empty($type)) {
            $type = self::creditCardType($number);
        }

        if (array_key_exists($type, self::$cards) && self::validCard($number, $type)) {
            return array(
                'valid' => true,
                'number' => $number,
                'type' => $type,
            );
        }

        return $ret;
    }

    public static function validCvc($cvc, $type)
    {
        return (ctype_digit($cvc) && array_key_exists($type, self::$cards) && self::validCvcLength($cvc, $type));
    }

    public static function validDate($expire)
    {
        if (preg_match("/^[0-9]{4}$/", $expire) === 0) {
            return false;
        }
        $month = substr($expire, 0, 2);
        $year = substr($expire, -2);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);

        if (!preg_match('/^\d\d$/', $year)) {
            return false;
        }

        if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            return false;
        }

        // past date
        if ($year < date('y') || $year == date('y') && $month < date('m')) {
            return false;
        }

        return true;
    }

    // PROTECTED
    // ---------------------------------------------------------

    protected static function creditCardType($number)
    {
        foreach (self::$cards as $type => $card) {
            if (preg_match($card['pattern'], $number)) {
                return $type;
            }
        }

        return '';
    }

    protected static function validCard($number, $type)
    {
        return (self::validPattern($number, $type) && self::validLength($number, $type) && self::validLuhn($number, $type));
    }

    protected static function validPattern($number, $type)
    {
        return preg_match(self::$cards[$type]['pattern'], $number);
    }

    protected static function validLength($number, $type)
    {
        foreach (self::$cards[$type]['length'] as $length) {
            if (strlen($number) == $length) {
                return true;
            }
        }

        return false;
    }

    protected static function validCvcLength($cvc, $type)
    {
        foreach (self::$cards[$type]['cvcLength'] as $length) {
            if (strlen($cvc) == $length) {
                return true;
            }
        }

        return false;
    }

    protected static function validLuhn($number, $type)
    {
        if (!self::$cards[$type]['luhn']) {
            return true;
        } else {
            return self::luhnCheck($number);
        }
    }

    protected static function luhnCheck($number)
    {
        $checksum = 0;
        for ($i = (2 - (strlen($number) % 2)); $i <= strlen($number); $i += 2) {
            $checksum += (int) ($number[$i - 1]);
        }

        // Analyze odd digits in even length strings or even digits in odd length strings.
        for ($i = (strlen($number) % 2) + 1; $i < strlen($number); $i += 2) {
            $digit = (int) ($number[$i - 1]) * 2;
            if ($digit < 10) {
                $checksum += $digit;
            } else {
                $checksum += ($digit - 9);
            }
        }

        if (($checksum % 10) == 0) {
            return true;
        } else {
            return false;
        }
    }
    public static function getLast4($number)
    {
        return 'xxxx xxxx xxxx ' . substr($number, -4);
    }
    public static function getCardBrand($pan, $include_sub_types = false)
    {
        $pan = preg_replace('/[^0-9]/', '', $pan);
        //maximum length is not fixed now, there are growing number of CCs has more numbers in length, limiting can give false negatives atm

        //these regexps accept not whole cc numbers too
        //visa
        $visa_regex = "/^4[0-9]{0,}$/";
        $vpreca_regex = "/^428485[0-9]{0,}$/";
        $postepay_regex = "/^(402360|402361|403035|417631|529948){0,}$/";
        $cartasi_regex = "/^(432917|432930|453998)[0-9]{0,}$/";
        $entropay_regex = "/^(406742|410162|431380|459061|533844|522093)[0-9]{0,}$/";
        $o2money_regex = "/^(422793|475743)[0-9]{0,}$/";

        // MasterCard
        $mastercard_regex = "/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[01]|2720)[0-9]{0,}$/";
        $maestro_regex = "/^(5[06789]|6)[0-9]{0,}$/";
        $kukuruza_regex = "/^525477[0-9]{0,}$/";
        $yunacard_regex = "/^541275[0-9]{0,}$/";

        // American Express
        $amex_regex = "/^3[47][0-9]{0,}$/";

        // Diners Club
        $diners_regex = "/^3(?:0[0-59]{1}|[689])[0-9]{0,}$/";

        //Discover
        $discover_regex = "/^(6011|65|64[4-9]|62212[6-9]|6221[3-9]|622[2-8]|6229[01]|62292[0-5])[0-9]{0,}$/";

        //JCB
        $jcb_regex = "/^(?:2131|1800|35)[0-9]{0,}$/";

        //ordering matter in detection, otherwise can give false results in rare cases
        if (preg_match($jcb_regex, $pan)) {
            return "JCB";
        }

        if (preg_match($amex_regex, $pan)) {
            return "AMEX";
        }

        if (preg_match($diners_regex, $pan)) {
            return "DINNERS CLUB";
        }

        //sub visa/mastercard cards
        if ($include_sub_types) {
            if (preg_match($vpreca_regex, $pan)) {
                return "V-PRECA";
            }
            if (preg_match($postepay_regex, $pan)) {
                return "POSTEPAY";
            }
            if (preg_match($cartasi_regex, $pan)) {
                return "CARTASI";
            }
            if (preg_match($entropay_regex, $pan)) {
                return "ENTROPAY";
            }
            if (preg_match($o2money_regex, $pan)) {
                return "O2MONEY";
            }
            if (preg_match($kukuruza_regex, $pan)) {
                return "KUKURUZA";
            }
            if (preg_match($yunacard_regex, $pan)) {
                return "YUNACARD";
            }
        }

        if (preg_match($visa_regex, $pan)) {
            return "VISA";
        }

        if (preg_match($mastercard_regex, $pan)) {
            return "MASTERCARD";
        }

        if (preg_match($discover_regex, $pan)) {
            return "DISCOVER";
        }

        if (preg_match($maestro_regex, $pan)) {
            if ($pan[0] == '5') { //started 5 must be mastercard
                return "MASTERCARD";
            }
            return "MAESTRO"; //maestro is all 60-69 which is not something else, thats why this condition in the end

        }

        return "UNKNOWN"; //unknown for this system
    }
}


class GPCrypto
{
    const METHOD = 'aes-256-ctr';
    const KEY = '136d3f73f5ae98b6daf6a53f72840d160d0895b397c266544560c368ba783f2b';

    /**
     * Encrypts (but does not authenticate) a message
     * 
     * @param string $message - plaintext message
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
}
