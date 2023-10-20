<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$exe = mysqli_query($dbc, "UPDATE sessions SET sessionStatus=1 WHERE sessionId=1");
if ($exe) {
    $res['status'] = true;
    echo json_encode($res);
    exit();
} else {
    $res['status'] = false;
    $res['message'] = "No session found";
    echo json_encode($res);
    exit();
}
