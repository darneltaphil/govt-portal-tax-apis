<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class GPMailer
{

    private $_mail;
    private $_lastError;
    public function __construct()
    {
        $this->_mail = new PHPMailer(true);
        $this->_mail->SMTPDebug = SMTP::DEBUG_OFF;
        $this->_mail->isSMTP();
        $this->_mail->SMTPAuth   = filter_var(getenv('EMAIL_AUTH'), FILTER_VALIDATE_BOOLEAN);
        $this->_mail->Host       = getenv('EMAIL_HOST');
        $this->_mail->Username   = getenv('EMAIL_USERNAME');
        $this->_mail->Password   = getenv('EMAIL_PASS');
        $this->_mail->SMTPSecure = getenv('EMAIL_SECURE');
        $this->_mail->Port       = getenv('EMAIL_PORT');
        $this->_mail->setFrom(getenv('DEFAULT_EMAIL'), getenv('DEFAULT_EMAIL_FROM'));
        $this->_mail->isHTML(true);
    }
    public function sendMail($subject, $body, $to = 'jlin@govtportal.com', $attachFile = '')
    {
        try {
            $this->_mail->Subject = $subject;
            $this->_mail->CharSet = 'UTF-8';
            $this->_mail->Body = $body;
            $this->_mail->clearAddresses();
            if (is_array($to)) {
                // $this->_mail->addAddress($to[0]);
                for ($i = 0; $i < count($to); $i++) {
                    $this->_mail->addAddress($to[$i]);
                }
            } else {
                $this->_mail->addAddress($to);
            }
            if ($attachFile) {
                $this->_mail->addAttachment($attachFile);
            }
            $this->_mail->send();
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$this->_mail->ErrorInfo}";
            $this->_lastError = $e->getMessage();
            return false;
        }
        return true;
    }
    public function getError()
    {
        return $this->_lastError;
    }
    public function notifyPaymentErrors($entity, $portalName, $siteURL, $data)
    {
        $logoURL = $this->getSiteLogo($entity);
        $line_items = '';
        $line_item_template = $this->_file_get_contents_utf8('email_templates/pay_error_item.html');
        foreach ($data as $value) {
            $line_items .= preg_replace(
                [
                    '{{{item_label}}}',
                    '{{{item_value}}}'
                ],
                [
                    addcslashes($value[0], '$'),
                    addcslashes($value[1], '$')
                ],
                $line_item_template
            );
        }
        $body = preg_replace(
            [
                '{images/image-1.png}',
                '{visualthemes.govtportal.com}',
                '{{{line_items}}}',
                '{{{year}}}',
                '{GovtPortal}'
            ],
            [
                $logoURL,
                $siteURL,
                addcslashes($line_items, '$'),
                date('Y'),
                getenv('COMPANY')
            ],
            $this->_file_get_contents_utf8('email_templates/pay_error.html')
        );
        $this->sendMail('Payment Error in the ' . $portalName . ' (' . date('m/d/Y H:i:s') . ' EST)', $body, ['jlin@govtportal.com', 'mliu@govtportal.com', 'tmccreary@govtportal.com', 'frank@govtportal.com']);
    }
    public function notifyPayment($entity, $portalName, $siteURL, $data)
    {
        $logoURL = $this->getSiteLogo($entity);
        $line_items = '';
        $line_item_template = $this->_file_get_contents_utf8('email_templates/pay_error_item.html');
        foreach ($data as $value) {
            $line_items .= preg_replace(
                [
                    '{{{item_label}}}',
                    '{{{item_value}}}'
                ],
                [
                    addcslashes($value[0], '$'),
                    addcslashes($value[1], '$')
                ],
                $line_item_template
            );
        }
        $body = preg_replace(
            [
                '{images/image-1.png}',
                '{visualthemes.govtportal.com}',
                '{{{line_items}}}',
                '{{{year}}}',
                '{GovtPortal}'
            ],
            [
                $logoURL,
                $siteURL,
                addcslashes($line_items, '$'),
                date('Y'),
                getenv('COMPANY')
            ],
            $this->_file_get_contents_utf8('email_templates/pay_notify.html')
        );
        $this->sendMail(getenv('COMPANY') . ' Notification (' . date('m/d/Y H:i:s') . ' EST)', $body);
    }
    private function getSiteLogo($entity)
    {

        return file_exists(getenv('LOGO_PATH') . DIRECTORY_SEPARATOR . $entity . getenv('SITE_LOGO_SUFFIX')) ?
            'https://' . getenv('MAIN_URL') . '/' . getenv('SITE_LOGO_URI') . '/' . $entity . getenv('SITE_LOGO_SUFFIX') :
            'https://' . getenv('MAIN_URL') . '/' . getenv('SITE_LOGO_URI') . '/default-entity'  . getenv('SITE_LOGO_SUFFIX');
    }
    public function sendTransactionMail($entity, $portalName, $rows, $siteURL, $to, $contactPhone, $contactEmail, $documents = NULL)
    {
        try {
            $table_content = '';
            foreach ($rows as $pair) {
                $table_content .=
                    '<tr style="border-bottom: 1px dashed #a09b9b; text-align: left;">
                        <td style="padding-left: 10px; padding-right: 10px;">' . $pair[0] . '</td>
                        <td>' . $pair[1] . '</td>
                    </tr>';
            }
            $body = $this->_file_get_contents_utf8(__DIR__ . '/email_templates/transaction.html');
            $body = preg_replace(
                [
                    '{images/lintest.png}',
                    '{visualthemes.govtportal.com}',
                    '{{{title}}}',
                    '{{{rows}}}',
                    '{images/gp_logo_h128.png}',
                    '{1234567890}',
                    '{team@govtportal.com}',
                    '{govtportal.com/new}'
                ],
                [
                    $this->getSiteLogo($entity),
                    $siteURL,
                    $portalName,
                    addcslashes($table_content, '$'),
                    getenv('MAIN_LOGO'),
                    $contactPhone,
                    $contactEmail,
                    getenv('MAIN_SITE')
                ],
                $body
            );
            $this->_mail->Subject = "Payment made to $portalName";
            $this->_mail->CharSet = 'UTF-8';
            $this->_mail->Body = $body;
            $this->_mail->clearAddresses();
            $this->_mail->addAddress($to);
            if ($documents) {
                foreach ($documents as $index => $document) {
                    $this->_mail->addAttachment($document, "document{$index}.pdf");
                }
            }
            $this->_mail->send();
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$this->_mail->ErrorInfo}";
            echoErrorAndExit(500, $e->getMessage());
        }
    }
    public function sendDocumentMail($entity, $portalName, $siteURL, $to, $contactPhone, $contactEmail, $attachFile, $isClerk = false)
    {
        if ($isClerk) {
            $body = $this->_file_get_contents_utf8(__DIR__ . '/email_templates/clerk-document.html');
        } else {
            $body = $this->_file_get_contents_utf8(__DIR__ . '/email_templates/document.html');
        }

        $body = preg_replace(
            [
                '{images/lintest.png}',
                '{visualthemes.govtportal.com}',
                '{{{portal_name}}}',
                '{images/gp_logo_h128.png}',
                '{1234567890}',
                '{team@govtportal.com}',
                '{govtportal.com/new}'
            ],
            [
                $this->getSiteLogo($entity),
                $siteURL,
                $portalName,
                getenv('MAIN_LOGO'),
                $contactPhone,
                $contactEmail,
                getenv('MAIN_SITE')
            ],
            $body
        );
        if (!$this->sendMail("Document has been submitted to $portalName", $body, $to, $attachFile)) {
            echoErrorAndExit(500, $this->_lastError);
        }
    }
    public function sendPaymentSuccessMail($entity, $fullName, $portalName, $accountNumber, $amount, $site_url, $email)
    {
        try {
            $body = $this->_file_get_contents_utf8('templates/payment.html');
            $body = preg_replace(
                [
                    '{{{site_logo}}}',
                    '{{{username}}}',
                    '{{{portal_name}}}',
                    '{{{account_number}}}',
                    '{{{amount}}}',
                    '{{{site_url}}}',
                    '{{{gp_logo}}}'
                ],
                [
                    $this->getSiteLogo($entity),
                    $fullName,
                    $portalName,
                    $accountNumber,
                    addcslashes(formatAmount($amount), '$'),
                    "https://{$site_url}",
                    getenv('MAIN_LOGO')
                ],
                $body
            );
            $this->_mail->Subject = "Your Auto-Pay to $portalName has been processed";
            $this->_mail->CharSet = 'UTF-8';
            $this->_mail->Body = $body;
            $this->_mail->clearAddresses();
            $this->_mail->addAddress($email, $fullName);
            $this->_mail->send();
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$this->_mail->ErrorInfo}";
        }
    }
    private function _file_get_contents_utf8($fn)
    {
        $content = file_get_contents($fn);
        return mb_convert_encoding(
            $content,
            'UTF-8',
            mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true)
        );
    }
}
