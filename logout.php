<?php
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
$auth = new Auth();
$auth->logout();