<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'staging23_taxusr');
define('DB_PASSWORD', 'yCGQ2^U5');
define('DB_DATABASE', 'staging23_tax');
$dbc = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE) or die('Could not connect to the database');
$dbc_pdo = new PDO('mysql:host=' . DB_SERVER . ';dbname=' . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
