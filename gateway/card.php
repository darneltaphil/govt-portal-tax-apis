<?php
header('Content-Type: application/json');
date_default_timezone_set('America/New_York');
set_time_limit(3600);
require_once 'functions.php';
require_once 'card_function.php';
require_once 'card_lib.php';
require_once 'mailer.php';
$db = new MysqliDb(getenv('DB_SERVER'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
$mailer = new GPMailer();
function processRequest()
{
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            addCard();
            break;
        default:
            echoErrorAndExit(400, 'Invalid Payment Request');
            break;
    }
}
processRequest();
