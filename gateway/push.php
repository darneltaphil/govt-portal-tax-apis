<?php
header('Content-Type: application/json');
date_default_timezone_set('America/New_York');
set_time_limit(3600);
require_once __DIR__ . '/vendor/autoload.php';
$db = new MysqliDb('localhost', 'govtport_dbuser', 'Bailey99!', 'govtport_db1');
function processAction()
{
    $action = trim($_POST['action']);
    if ($action == 'redlineAddEmail') {
        redlineAddEmail();
    }
}
processAction();
function redlineAddEmail()
{
    $email = trim($_POST['email']);
    $transactionID = trim($_POST['transactionID']);
    global $db;
    $db->where('trans_all_id', $transactionID)->update('redline_transactions', ['email' => $email]);
}
