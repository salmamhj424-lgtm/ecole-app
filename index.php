<?php
require __DIR__ . '/config.php';
header('Location: ' . (current_user() ? 'dashboard.php' : 'login.php'));
exit;
