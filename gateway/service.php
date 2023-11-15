<?php
header('Content-Type: application/json');
date_default_timezone_set('America/New_York');
set_time_limit(3600);
require_once 'functions.php';

use Twilio\Rest\Client;

require_once 'mailer.php';
require_once 'pdfmaker.php';
//$db = new MysqliDb(getenv('DB_SERVER'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
//$mailer = new GPMailer();
function processServiceRequest()
{
    global $mailer;
    if (!isset($_POST['cmd'])) {
        echoErrorAndExit(400, 'Invalid Service Request');
    }
    $cmd = $_POST['cmd'];
    // Get USAEPay EMV Terminal Status
    if ($cmd == 'gts') {
        if (!isset($_POST['deviceKey'])) {
            echoErrorAndExit(400, 'Invalid Service Request');
        }
        $terminalStatus = getUSAEPayEMVTerminalStatus($_POST['deviceKey']);
        echo json_encode([
            'result' => 'Success',
            'status' => $terminalStatus ? 'Online' : 'Offline'
        ]);
    }
    // Send Transaction Email
    if ($cmd == 'ste') {
        $tran_id = filter_input(INPUT_POST, 'tranID', FILTER_SANITIZE_NUMBER_INT);
        $to = filter_input(INPUT_POST, 'to', FILTER_SANITIZE_EMAIL);
        if ($tran_id < 1 || !$to) {
            echoErrorAndExit(400, 'Invalid Service Request');
        }
        sendTransactionEmail($tran_id, $to);
    }
    // Send Transaction SMS
    if ($cmd == 'sts') {
        $tran_id = filter_input(INPUT_POST, 'tranID', FILTER_SANITIZE_NUMBER_INT);
        $to = preg_replace('/[^0-9]/', '', filter_input(INPUT_POST, 'to'));
        if ($tran_id < 1 || strlen($to) < 10 || strlen($to) > 11) {
            echoErrorAndExit(400, 'Invalid Service Request');
        }
        sendTransactionSMS($tran_id, $to);
    }
    // Send Transactions Table Email
    if ($cmd == 'stte') {
        $tran_ids = explode(',', filter_input(INPUT_POST, 'tranID'));
        $to = filter_input(INPUT_POST, 'to', FILTER_SANITIZE_EMAIL);
        if (count($tran_ids) < 1 || !$to) {
            echoErrorAndExit(400, 'Invalid Service Request');
        }
        sendTransactionsTableEmail($tran_ids, $to);
    }
    // Send SMS
    if ($cmd == 'ss') {
        $body = filter_input(INPUT_POST, 'body');
        $to = preg_replace('/[^0-9]/', '', filter_input(INPUT_POST, 'to'));
        if (!$body || strlen($to) < 10 || strlen($to) > 11) {
            echoErrorAndExit(400, 'Invalid Service Request');
        }
        $from = filter_input(INPUT_POST, 'from');
        if ($from) {
            sendSMS($body, $to, $from);
        } else {
            sendSMS($body, $to);
        }

        echo json_encode(['result' => 'Success']);
    }
    // Send Email
    if ($cmd == 'se') {
        $subject = filter_input(INPUT_POST, 'subject');
        $body = filter_input(INPUT_POST, 'body');
        $to = filter_input(INPUT_POST, 'to');
        $to = array_filter(array_map('trim', explode(',', $to)), function ($value) {
            return !is_null($value) && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL);
        });
        if (!$subject || !$body || !$to) {
            echoErrorAndExit(400, 'Invalid Service Request');
        }
        $to = array_unique($to);
        if (!$mailer->sendMail($subject, $body, $to)) {
            echo json_encode(['result' => 'Error', 'error' => $mailer->getError()]);
        } else {
            echo json_encode(['result' => 'Success']);
        }
    }
    // Make PDF and send to clerk emails
    if ($cmd == 'pdf') {
        sendDocumentAndNotify();
        return;
    }
    if ($cmd == 'new_pdf') {
        makePDFAndNotify();
        return;
    }
    if ($cmd == 'utc_pdf') {
        makeUTCTransmittalAndSendEmail();
        return;
    }
    // Send Document Email
    if ($cmd == 'sde') {
        sendDocumentEmail();
    }
    if ($cmd == 'application') {
        $formId = filter_input(INPUT_POST, 'form_id');
        global $db;
        $saForm = $db->where('sa_field_id', $formId)->getOne('sa_fields');
        if (empty($saForm['app_doc_id'])) {
            echo json_encode(['result' => 'Error', 'error' => 'Form not found']);
            return;
        }
        $template = $db->where('id', $saForm['app_doc_id'])->getOne('gp_documents', 'content');
        if (empty($template['content'])) {
            echo json_encode(['result' => 'Error', 'error' => 'Document not found']);
            return;
        }
        $data = [];
        foreach ($saForm as $key => $value) {
            $data[$key] = filter_input(INPUT_POST, $key);
        }
        $pdfMaker = new PDFMaker();
        $filePath = $pdfMaker->makeApplicationDocument($template['content'], $data, 'application' . filter_input(INPUT_POST, 'code'));
        $receipt_emails = explode(",", filter_input(INPUT_POST, 'receipt_emails'));

        $email_subject = empty($_POST['email_subject']) ? 'Application Document' : $_POST['email_subject'];
        $email_body = empty($_POST['email_body']) ? 'Application Document' : $_POST['email_body'];

        if ($mailer->sendMail($email_subject, $email_body, $receipt_emails, $filePath)) {
            echo json_encode(['result' => 'Success']);
        } else {
            echo json_encode(['result' => 'Error', 'error' => $mailer->getError()]);
        }
        return;
    }
    if ($cmd == 'prepare_pdf') {
        prepareDocumentHtml();
    }
}
// processServiceRequest();
function prepareDocumentHtml()
{
    $portal_name = filter_input(INPUT_POST, 'portal_name');
    $case_number = filter_input(INPUT_POST, 'case_number');
    $defendant = filter_input(INPUT_POST, 'last_name');
    $description = filter_input(INPUT_POST, 'description');
    $signature = '';
    $format = filter_input(INPUT_POST, 'format');
    if (!file_exists(__DIR__ . '/pdf_templates/' . $format . '.html')) {
        echo json_encode(['result' => 'Error', 'content' => 'Not exist format file']);
        return;
    }
    $content = file_get_contents(__DIR__ . '/pdf_templates/' . $format . '.html');
    $content = mb_convert_encoding(
        $content,
        'UTF-8',
        mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true)
    );
    $data = [
        'portal_name' => $portal_name,
        'case_number' => $case_number,
        'date' => date('m/d/Y'),
        'defendant' => $defendant,
        'description' => $description,
        'time' => date('H:i:s'),
        'today' => date('m/d/Y'),
        'signature' => $signature
    ];
    $content = preg_replace(
        array_map(function ($value) {
            return '{{{' . $value . '}}}';
        }, array_keys($data)),
        array_values($data),
        $content
    );
    echo json_encode(['result' => 'Success', 'content' => $content]);
}
function sendDocumentEmail()
{
    global $db;
    $portal = $db->where('Portal_Id', filter_input(INPUT_POST, 'portal_id'))->getOne('zoho_products');
    $to = filter_input(INPUT_POST, 'to');
    $to = array_filter(array_map('trim', explode(',', $to)), function ($value) {
        return !is_null($value) && $value !== '';
    });
    $pdfPath = filter_input(INPUT_POST, 'pdf_path');
    global $mailer;
    if (file_exists($pdfPath) && count($to) > 0) {
        $mailer->sendDocumentMail(
            $portal['Entity'],
            $portal['portal_name'],
            $portal['Location_URL'],
            $to,
            $portal['Customer_Service_Number'],
            $portal['Reporting_Email'],
            $pdfPath
        );
        echo json_encode(['result' => 'Success']);
    } else {
        echoErrorAndExit(400, 'Error');
    }
}
function sendDocumentAndNotify()
{
    $pdfMaker = new PDFMaker;
    global $db;
    $portal = $db->where('Portal_Id', filter_input(INPUT_POST, 'portal_id'))->getOne('zoho_products');
    $signature = filter_input(INPUT_POST, 'signature');
    $to = filter_input(INPUT_POST, 'to');
    $to = array_filter(array_map('trim', explode(',', $to)), function ($value) {
        return !is_null($value) && $value !== '';
    });
    $file_name = uniqid('document');
    $pdfPath = $pdfMaker->make(
        filter_input(INPUT_POST, 'template'),
        array_merge(
            array_combine(
                array_map(function ($v) {
                    return "field_{$v}";
                }, range(0, 99)),
                array_map(function ($v) {
                    return [filter_input(INPUT_POST, "field{$v}"), filter_input(INPUT_POST, "field{$v}_type")];
                }, range(0, 99))
            ),
            [
                'portal_name' => $portal['portal_name'],
                'date' => date('m/d/Y'),
                'time' => date('H:i:s'),
                'today' => date('m/d/Y'),
                'signature' => $signature
            ]
        ),
        $file_name
    );
    global $mailer;
    if (count($to) > 0) {
        $mailer->sendDocumentMail(
            $portal['Entity'],
            $portal['portal_name'],
            $portal['Location_URL'],
            $to,
            $portal['Customer_Service_Number'],
            $portal['Reporting_Email'],
            $pdfPath
        );
    }
    $receipt = filter_input(INPUT_POST, 'receipt');
    $receipt = array_filter(array_map('trim', explode(',', $receipt)), function ($value) {
        return !is_null($value) && $value !== '';
    });
    if (count($receipt) > 0) {
        $mailer->sendDocumentMail(
            $portal['Entity'],
            $portal['portal_name'],
            $portal['Location_URL'],
            $receipt,
            $portal['Customer_Service_Number'],
            $portal['Reporting_Email'],
            $pdfPath,
            true
        );
    }
    echo json_encode(['result' => 'Success', 'file' => $file_name . '.pdf', 'path' => $pdfPath]);
}
function makeUTCTransmittalAndSendEmail()
{
    $fn = __DIR__ . '/../documents/utc_' . uniqid() . '.pdf';
    $fp = fopen($fn, 'w');
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://utc-transmittal.ippayware.com',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FILE => $fp,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $_POST['data'],
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));

    curl_exec($curl);

    curl_close($curl);
    fclose($fp);
    global $db;
    $portal = $db->where('Portal_Id', filter_input(INPUT_POST, 'portal_id'))->getOne('zoho_products');
    $to = filter_input(INPUT_POST, 'to');
    $to = array_filter(array_map('trim', explode(',', $to)), function ($value) {
        return !is_null($value) && $value !== '';
    });
    global $mailer;
    if (count($to) > 0) {
        $mailer->sendDocumentMail(
            $portal['Entity'],
            $portal['portal_name'],
            $portal['Location_URL'],
            $to,
            $portal['Customer_Service_Number'],
            $portal['Reporting_Email'],
            $fn,
            true
        );
    }
}
function makePDFAndNotify()
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => getenv('GP_ESIGN_URL') . "/direct_print.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_DNS_CACHE_TIMEOUT => 0,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $_POST
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    if ($response == '') {
        echo json_encode(['result' => 'Error']);
        return;
    }
    $subPath = date('Y-m/');
    $file_name = uniqid('document');

    $fullPath = __DIR__ . '/../documents/' . $subPath;
    if (!file_exists($fullPath)) {
        mkdir($fullPath);
    }
    $fullPath .= $file_name . '.pdf';
    file_put_contents($fullPath, $response);
    global $db;
    $portal = $db->where('Portal_Id', filter_input(INPUT_POST, 'portal_id'))->getOne('zoho_products');
    $to = filter_input(INPUT_POST, 'to');
    $to = array_filter(array_map('trim', explode(',', $to)), function ($value) {
        return !is_null($value) && $value !== '';
    });
    global $mailer;
    if (count($to) > 0) {
        $mailer->sendDocumentMail(
            $portal['Entity'],
            $portal['portal_name'],
            $portal['Location_URL'],
            $to,
            $portal['Customer_Service_Number'],
            $portal['Reporting_Email'],
            $fullPath
        );
    }
    $receipt = filter_input(INPUT_POST, 'receipt');
    $receipt = array_filter(array_map('trim', explode(',', $receipt)), function ($value) {
        return !is_null($value) && $value !== '';
    });
    if (count($receipt) > 0) {
        $mailer->sendDocumentMail(
            $portal['Entity'],
            $portal['portal_name'],
            $portal['Location_URL'],
            $receipt,
            $portal['Customer_Service_Number'],
            $portal['Reporting_Email'],
            $fullPath,
            true
        );
    }
    echo json_encode(['result' => 'Success', 'file' => $subPath . $file_name . '.pdf', 'path' => $fullPath]);
}

function getUSAEPayEMVTerminalStatus($deviceKey)
{
    $apiKey = getenv('USAEPAY_EMV_CHECK_KEY');
    $apiPin = '8888';
    $seed = rand() . time();
    $preHash = $apiKey . $seed . $apiPin;
    $apiHash = 's2/' . $seed . '/' . hash('sha256', $preHash);
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://usaepay.com/api/v2/paymentengine/devices/{$deviceKey}",
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
    if (curl_error($curl)) {
        return false;
    }

    curl_close($curl);
    $response_obj = json_decode($response);
    if (!$response_obj) {
        return false;
    }
    if (property_exists($response_obj, 'status')) {
        if ($response_obj->status == 'online' || $response_obj->status == 'connected') {
            return true;
        }
    }
    return false;
}

function sendTransactionEmail($tran_id, $to)
{
    global $db, $mailer;
    $record = $db->where('GP_id', $tran_id)->getOne('trans_all');
    if (!$record) {
        echoErrorAndExit(400, 'Transaction Record Not Found');
    }
    // Get Portal
    $portal = $db->where('Portal_Id', $record['Portal_Id'])->getOne('zoho_products');
    if (!$portal) {
        echoErrorAndExit(400, 'Portal Record Not Found');
    }
    // Get Form
    $form = $db->where('form_name', $portal['form_name'])->getOne('sa_fields');
    if (!$form) {
        echoErrorAndExit(400, 'Form Record Not Found');
    }
    // Get Entity
    $entity = $portal['Entity'];
    // Get Timezone
    $timezone = getOption($entity, 'timezone', 'America/New_York');
    // Get Site Url
    $siteUrl = $portal['Location_URL'];

    $transTime = DateTime::createFromFormat('Y-m-d H:i:s', $record['tdate_city']);
    $transTime->setTimezone(new DateTimeZone($timezone));
    $rows = [];
    $rows[] = ['Date', $transTime->format('M j, Y, g:i A')];
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
    ] as $form_key => $value_key) {
        if ($form[$form_key] && $record[$value_key]) {
            $rows[] = [$form[$form_key], $record[$value_key]];
        }
    }
    if ($record['cc_type']) {
        $rows[] = ['Card Type', $record['cc_type']];
    }
    $rows[] = ['Amount', formatAmount($record['amount_city'])];
    $flatFeeAmount = 0;
    if ($record['fromfile']) {
        $flatFee = json_decode($record['fromfile'], true);
        if ($flatFee) {
            $rows[] = [$flatFee[1], formatAmount($flatFee[0])];
            $flatFeeAmount = $flatFee[0];
        }
    }
    if ($record['absorb_fee'] != 'yes') {
        $rows[] = ['Service Fee', formatAmount($record['amount_fee'])];
    }
    if ($record['absorb_fee'] != 'yes' || $flatFeeAmount > 0) {
        $rows[] = ['Total Amount', formatAmount($record['amount_city'] + $record['amount_fee'] + $flatFeeAmount)];
    }
    $rows[] = ['Transaction ID', $record['TransIDCity']];
    $rows[] = ['Auth Code', $record['authcode_city']];
    $rows[] = ['User', $record['username']];
    $rows[] = ['Status', $record['Status']];
    if ($record['Status'] == 'Voided' || $record['Status'] == 'Refunded') {
        $rows[] = ['Reason', $record['void_reason']];
    }
    // Check if has document files
    $documents = $db->where('trans_id', $tran_id)->getValue('gp_documents_all', 'file_path', null);
    global $mailer;
    $mailer->sendTransactionMail($entity, $portal['portal_name'], $rows, $siteUrl, $to, preg_replace('/[^0-9]/', '', $portal['Customer_Service_Number']), $portal['Reporting_Email'], $documents);
    // Add Email Address for Future Business
    $db->insert('gp_emails', [
        'post_time' => date('Y-m-d H:i:s'),
        'source' => 'send_transaction',
        'source_id' => $record['GP_id'],
        'source_data' => json_encode($rows),
        'email' => $to
    ]);
    if ($portal['Var_Partner'] == 'NewRedLine') {
        // Check email is admin email
        $adminEmails = $db->where('pb_portal_id', $portal['Portal_Id'])->getValue('portal_boolean', 'pb_admin_email1');
        if ($adminEmails) {
            if (strpos($adminEmails, $to) !== false) {
                // echo 'true';
            } else {
                $db->where('trans_all_id', $record['GP_id'])->update('redline_transactions', ['email' => $to]);
            }
        } else {
            $db->where('trans_all_id', $record['GP_id'])->update('redline_transactions', ['email' => $to]);
        }
    }
    echo json_encode(['result' => 'Success']);
}

function sendTransactionSMS($tran_id, $phone)
{
    global $db;
    $record = $db->where('GP_id', $tran_id)->getOne('trans_all');
    if (!$record) {
        echoErrorAndExit(400, 'Transaction Record Not Found');
    }
    // Get Portal
    $portal = $db->where('Portal_Id', $record['Portal_Id'])->getOne('zoho_products');
    if (!$portal) {
        echoErrorAndExit(400, 'Portal Record Not Found');
    }
    // Get Entity
    $entity = $portal['Entity'];
    // Get Timezone
    $timezone = getOption($entity, 'timezone', 'America/New_York');

    $transTime = DateTime::createFromFormat('Y-m-d H:i:s', $record['tdate_city']);
    $transTime->setTimezone(new DateTimeZone($timezone));
    if ($record['absorb_fee'] != 'yes') {
        $sms_body = "Your payment has been approved for " .
            formatAmount($record['amount_city']) .
            " to Auth Code {$record['authcode_city']} and " .
            formatAmount($record['amount_fee']) .
            " Service Fee to Auth Code {$record['authcode_fee']}. Thank you for using " . getenv('COMPANY');
    } else {
        $sms_body = "Your payment has been approved for " .
            formatAmount($record['amount_city']) .
            " to Auth Code {$record['authcode_city']}. Thank you for using " . getenv('COMPANY');
    }
    sendSMS($sms_body, $phone);
    // Add Email Address for Future Business
    $db->insert('gp_phones', [
        'post_time' => date('Y-m-d H:i:s'),
        'source' => 'send_transaction',
        'source_id' => $record['GP_id'],
        'source_data' => $sms_body,
        'phone' => $phone
    ]);
    if ($portal['Var_Partner'] == 'NewRedLine') {
        $db->where('trans_all_id', $record['GP_id'])->update('redline_transactions', ['phone' => $phone]);
    }
    echo json_encode(['result' => 'Success']);
}

function sendSMS($text, $phone, $from = NULL)
{

    try {
        // $sid = getenv("TWILIO_ACCOUNT_SID");
        // $apiKey = getenv("TWILIO_API_KEY");
        // $apiSecret = getenv("TWILIO_API_SECRET");
        $sid = "AC789439c0b2cf606918ba41629e2900a3";
        $apiKey = "SKfc7ec7e9b433547315774f1da57b1288";
        $apiSecret = "E1HKuI46x7UIxhrHNNy5t4upuAB6h8yo";
        $twilio = new Client($apiKey, $apiSecret, $sid);
        $from = '+14704076378';

        $message = $twilio->messages
            ->create(
                $phone, // to
                [
                    "body" => $text,
                    "from" => $from
                ]
            );


        if (in_array($message->status, ['failed', 'undelivered'])) {
            echoErrorAndExit(500, 'Delivery Error');
        } else {
            if ($sid == 'AC369abc7dd013bcf6ee576c042ef48982') {
                global $mailer;
                $mailer->sendMail(sprintf('SMS sent to %s', $phone), $text);
            }
            return true;
        }
    } catch (Exception $th) {
        echoErrorAndExit(500, $th->getMessage());
    }
}
/**
 * @var string[] $tran_ids
 */
function sendTransactionsTableEmail($tran_ids, $to)
{
    global $db, $mailer;
    $voidedTransactionCount =
        $voidedTransactionAmount =
        $approvedTransactionAmount =
        $approvedTransactionCount =
        $totalTransactionAmount =
        $totalTransactionCount =
        $refundedTransactionAmount =
        $refundedTransactionCount = 0;
    $portal_names = [];
    $table_content = '<table class="table table-hover dataTable" id="alt-style" style="border-collapse:collapse;margin:0 auto;">
    <tr>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em; padding: 0 3px;">#</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Date</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Portal Name</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Customer Name</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Customer Data</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Trans. Ref & Auth</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Cardholder & Type</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Amount Paid</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Status</th>
        <th style="background: #074d89;color: #fff;border: 1px solid #fff;border-radius: .4em;">Username (Clerk)</th>
    </tr>';
    foreach ($tran_ids as $index => $tran_id) {
        $record = $db->where('GP_id', $tran_id)->getOne('trans_all');
        if ($record) {
            // Get entity and timezone if first record
            if (!isset($timezone)) {
                $entity = $record['entity'];
                $timezone = getOption($entity, 'timezone', 'America/New_York');
            }
            $portal_names[] = $record['portal_name'];
            $date = format_mysql_datetime($record['tdate_city'], $timezone);
            $customer_data = implode('<br>', array_filter([$record['standard1'], $record['standard2'], $record['custom1']]));
            $ref = implode('<br>', [$record['TransIDCity'], $record['authcode_city']]);
            $card = implode('<br>', [$record['CardHolder'], $record['cc_type']]);
            $amount = formatAmount($record['amount_city']);
            $status = $record['Status'];
            $status_text = implode('<br>', array_filter([$record['Status'], $record['void_reason']]));
            $text_color = ($status == "Voided" || $status == "Refunded") ? 'style="color: red"' : '';
            $table_content .=
                '<tr ' . $text_color . '>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:right;vertical-align:middle; padding: 0 8px;">' . ($index + 1) . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $date . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $record['portal_name'] . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $record['PersonName'] . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $customer_data . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $ref . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $card . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:right;vertical-align:middle; padding: 0 8px;">' . $amount . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $status_text . '</td>
                    <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle; padding: 0 8px;">' . $record['username'] . '</td>
                </tr>';
            if ($record['Status'] == 'Approved') {
                $approvedTransactionCount++;
                $approvedTransactionAmount += $record['amount_city'];
            }
            if ($record['Status'] == 'Voided') {
                $voidedTransactionCount++;
                $voidedTransactionAmount += $record['amount_city'];
            }
            if ($record['Status'] == 'Refunded') {
                $refundedTransactionCount++;
                $refundedTransactionAmount += $record['amount_city'];
            }
        }
    }
    $totalTransactionCount = $approvedTransactionCount + $voidedTransactionCount + $refundedTransactionCount;
    $totalTransactionAmount = $approvedTransactionAmount + $refundedTransactionAmount;
    $table_content .=
        '<tr>
            <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle;" colspan=5>' .
        implode('<br>', ['Approved Transactions: ' . number_format($approvedTransactionCount), 'Approved Amount: ' . formatAmount($approvedTransactionAmount)]) . '</td>
            <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle;" colspan=5>' .
        implode('<br>', ['Total Transactions: ' . number_format($totalTransactionCount), 'Total Gross Amount: ' . formatAmount($totalTransactionAmount)]) . '</td>
        </tr>
        <tr>
            <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle;" colspan=5>' .
        implode('<br>', ['Voided Transactions: ' . number_format($voidedTransactionCount), 'Voided Amount: ' . formatAmount($voidedTransactionAmount)]) . '</td>
            <td style="border: 1px solid #DCDCDC;border-radius: .4em;text-align:center;vertical-align:middle;" colspan=5>' .
        implode('<br>', ['Refunded Transactions: ' . number_format($refundedTransactionCount), 'Refunded Amount: ' . formatAmount($refundedTransactionAmount)]) . '</td>
        </tr></table>';
    if ($mailer->sendMail('Report for ' . implode(', ', array_unique($portal_names)), $table_content, $to)) {
        echo json_encode(['result' => 'Success']);
    } else {
        echoErrorAndExit(500, $mailer->getError());
    }
}

function format_mysql_datetime($str, $timezone)
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $str);
    $dt->setTimezone(new DateTimeZone($timezone));
    return $dt->format('M j, Y, g:i A');
}
