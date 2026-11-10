<?php
require_once 'config.php';
require_login();

if ($_SESSION['role'] === 'boss') {
    header('Location: boss.php');
} else {
    header('Location: secretaire.php');
}
exit;
