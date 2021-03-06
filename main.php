<?php

require_once __DIR__ . '/vendor/autoload.php';

use Src\Checking;
use Src\Chatwork;

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

$roomId = "20238447";
$codeToCWId = [
    "B120085" => "775585",
    "B120050" => "645435",
    "B120052" => "899965",
    "B120157" => "972249",
    "B120048" => "641279",
    "B120342" => "856778",
    "B120051" => "657237",
    "B120007" => "638461",
];
$codes = array_keys($codeToCWId);

$checking = new Checking();
$timesheetResult = $checking->exec($codes);

$chatwork = new Chatwork();
$message = $chatwork->createMessage($timesheetResult, $codeToCWId);
$chatwork->sendMessage($roomId, $message);