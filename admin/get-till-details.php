<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
// echo "SELECT * FROM till_balance WHERE setDate ='" . date('Y-m-d') . "'";
$exe = mysqli_query($dbc, "SELECT * FROM till_balance WHERE setDate ='" . date('Y-m-d') . "'");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
} else {
    $res['status'] = true;
    $res['message'] = "There are no till details";
    echo json_encode($res);
    exit();
}
