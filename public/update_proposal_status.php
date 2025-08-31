<?php
// /public/update_proposal_status.php
session_start();
require_once __DIR__ . '/../bootstrap.php'; 

auth_required();
if (!is_admin()) { http_response_code(403); exit('Forbidden'); }

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$valid  = ['Pending','Approved','Rejected','Completed'];

if (!csrf_check($_POST['csrf_token'] ?? null)) {
  http_response_code(400); exit('Bad request (CSRF).');
}
if ($id <= 0 || !in_array($status, $valid, true)) {
  http_response_code(400); exit('Bad request.');
}

$stmt = db()->prepare("UPDATE proposals SET status=? WHERE id=?");
$stmt->bind_param('si', $status, $id);
$stmt->execute();

// bounce back to manage view, staying on the same proposal id
$qs = http_build_query(['id'=>$id]);
header("Location: " . PUBLIC_URL . "/manage_proposals.php?{$qs}");
exit;
