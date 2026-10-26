<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    startSession();
    unset($_SESSION['admin_id'], $_SESSION['admin_csrf']);
    session_regenerate_id(true);
}
redirect('login.php');
