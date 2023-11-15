<?php
header('Content-Type: application/json');
date_default_timezone_set('America/New_York');
set_time_limit(3600);
require_once 'functions.php';
require_once 'payment_function.php';
require_once 'pay_api.php';
require_once 'pay2_api.php';
require_once 'mailer.php';
require_once 'pdfmaker.php';
require_once 'recurring_helper.php';
$db = new MysqliDb(getenv('DB_SERVER'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
$mailer = new GPMailer();
function processRequest()
{
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            processPayment();
            break;
        case 'GET':
            getTransactionStatus();
            break;
        case 'DELETE':
            processVoidOrRefundPayment();
            break;
        case 'PUT':
            processCapturePayment();
            break;
        default:
            echoErrorAndExit(400, 'Invalid Payment Request');
            break;
    }
}
processRequest();
