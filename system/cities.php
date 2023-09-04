<?php
include("../services/header-filter.php");
include("../inc/functions.php");
include("../db/index.php");
$exe = mysqli_query($dbc, "SELECT * FROM cities WHERE city_state >3018 AND city_state <3979");
if (mysqli_num_rows($exe) > 0) {
    $res['status'] = true;
    $res['data'] = mysqli_fetch_all($exe, MYSQLI_ASSOC);
    echo json_encode($res);
    exit();
}
