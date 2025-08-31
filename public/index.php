<?php
// /public/index.php
session_start();
require_once __DIR__ . '/../helpers/auth.php';

// force login if not authenticated
auth_required();

// if logged in, send them to the right dashboard
redirect_by_role();
