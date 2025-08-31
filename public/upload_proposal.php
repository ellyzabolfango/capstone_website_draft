<?php
// /public/upload_proposal.php
session_start();
require_once __DIR__ . '/../bootstrap.php'; 
auth_required();
if (!is_admin()) { http_response_code(403); exit('Forbidden'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if (!csrf_check($_POST['csrf_token'] ?? null)) { http_response_code(400); exit('Bad request (CSRF).'); }

$proposalId = isset($_POST['proposal_id']) ? (int)$_POST['proposal_id'] : 0;
if ($proposalId <= 0 || empty($_FILES['file']['name'])) { http_response_code(400); exit('Missing data'); }

// basic validation
$allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg'];
$maxMB   = 10;
$sizeOK  = ($_FILES['file']['size'] ?? 0) <= ($maxMB * 1024 * 1024);
$ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true) || !$sizeOK) { http_response_code(400); exit('Invalid file'); }

// storage dir (NOT web-accessible)
$baseDir   = realpath(__DIR__ . '/../content');           // points to /content
$uploadDir = $baseDir . '/uploads/proposals';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

// unique filename
$orig   = basename($_FILES['file']['name']);
$slug   = preg_replace('/[^A-Za-z0-9._-]/','_', $orig);
$fname  = time() . '_' . bin2hex(random_bytes(4)) . '_' . $slug;
$target = $uploadDir . '/' . $fname;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
  http_response_code(500); exit('Upload failed');
}

// save RELATIVE path (e.g., uploads/proposals/XYZ.pdf)
$relPath = 'uploads/proposals/' . $fname;
$stmt = db()->prepare("UPDATE proposals SET attachment_path=? WHERE id=?");
$stmt->bind_param("si", $relPath, $proposalId);
$stmt->execute();

// back to manage page (stay on same proposal)
header("Location: " . PUBLIC_URL . "/manage_proposals.php?" . http_build_query(['id'=>$proposalId]));
exit;

