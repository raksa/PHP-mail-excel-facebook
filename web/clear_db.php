<?php
require_once 'db.php';
$db = connect();
if ($db) {
    clearAll($db);
    unlink(getcwd() . '/../' . 'files/*.xls');
    unlink(getcwd() . '/../' . 'files/*.xlsx');
    $db->close();
} else {
    $_SESSION[$SESSION_DB_MESSAGE] = 'Can\'t connect to database.';
}
header("Location: index.php");