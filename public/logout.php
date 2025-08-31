<?php
require_once __DIR__ . '/../helpers/auth.php';
auth_logout();
header("Location: " . LOGIN_URL);
exit();
