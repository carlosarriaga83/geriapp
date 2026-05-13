<?php
require_once dirname(__DIR__) . '/conf/config.php';
unset($_SESSION['sa_authenticated'], $_SESSION['sa_user'], $_SESSION['sa_name']);
header('Location: ' . BASE_URL . '/superadmin/login.php');
exit;
