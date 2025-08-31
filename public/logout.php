<?php
require_once __DIR__ . '/../bootstrap.php'; 
auth_logout();
header("Location: " . LOGIN_URL);
exit();
