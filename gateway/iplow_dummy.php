<?php
// header('Content-Type: application/json');
date_default_timezone_set('America/New_York');
set_time_limit(3600);
require_once 'functions.php';
function make_iplow_dummy_record()
{
    $db = new MysqliDb(getenv('DB_SERVER'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
    $record = $db->where('TotalBalanceDue', 0, '>')->where('PortalID', 'gp2517', '!=')->orderBy('RAND()')->getOne('iplow_incoming');
    $record['FirstName'] = filter_input(INPUT_POST, 'first');
    $record['LastName'] = filter_input(INPUT_POST, 'last');
    $record['Street'] = filter_input(INPUT_POST, 'street');
    $record['City'] = filter_input(INPUT_POST, 'city');
    $record['State'] = filter_input(INPUT_POST, 'state');
    $record['PhoneHome'] = filter_input(INPUT_POST, 'phone');
    $record['RecordSource'] = 'TEST';
    $record['InsertedTime'] = date('Y-m-d H:i:s');
    $record['PortalID'] = 'gp2517';
    $record['IplowID'] = 'gp2517_' . $record['CaseNumber'];
    $record['ID'] = null;
    $db->insert('iplow_incoming', $record);
    echo $record['CaseNumber'];
}
make_iplow_dummy_record();
