<?php
// index.php
// Entry Point - Redirects to appropriate page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;