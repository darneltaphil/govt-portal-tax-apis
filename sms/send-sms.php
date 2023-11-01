<?php

require_once '../vendor/autoload.php'; // Loads the library
use Twilio\Rest\Client;

// Your Account Sid and Auth Token from twilio.com/user/account
$sid = "AC098e3bae27bdc7c36e87477ffdde7b70";
$token = "LeDjQ5MBXIXeqRdDZvYQ4tdqgunMrkjW";
$client = new Client($sid, $token);

$message = $client->messages->create(
    '+233200111391',
    array(
        'from' => 'GOVTPORTAL',
        'body' => "testing this"
    )
);
print("Message sent successfully with sid = " . $message->sid . "\n\n");
