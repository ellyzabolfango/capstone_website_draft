<?php
require_once __DIR__ . '/../helpers/auth.php';
auth_logout();
header("Location: /capstone_website/public/login.php");
exit();
