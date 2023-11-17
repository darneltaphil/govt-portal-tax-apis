<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
include("../gateway/service.php");
$msg = "SMS is working";
$res = sendSMS($msg, "+14044481630", "+14704076378");

echo json_encode($res);
