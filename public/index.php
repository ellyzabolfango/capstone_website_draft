<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header("Location: /capstone_website/public/login.php");
  exit();
}
require_once __DIR__ . '/../helpers/auth.php';
redirect_by_role();