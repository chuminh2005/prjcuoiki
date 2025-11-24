<?php
require_once 'session.php';

destroyUserSession();

header("Location: dangnhap.php");
exit();
?>