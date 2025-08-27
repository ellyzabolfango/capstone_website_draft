<?php
// /views/user/upload_proposal.php
session_start();
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../config/db.php';
auth_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: projects.php"); exit(); }
if (!csrf_check($_POST['csrf_token'] ?? null)) { header("Location: projects.php?msg=" . urlencode("Invalid request.")); exit(); }

$type = trim($_POST['type'] ?? '');
$validTypes = ['Program','Project','Activity'];
if (!in_array($type, $validTypes, true)) { $type = 'Project'; }

if (!isset($_FILES['proposal_file']) || !$_FILES['proposal_file']['name']) {
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("No file uploaded."));
  exit();
}

$allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx'];
$orig = $_FILES['proposal_file']['name'];
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("Unsupported file type."));
  exit();
}
if ($_FILES['proposal_file']['error'] !== UPLOAD_ERR_OK) {
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("Upload error."));
  exit();
}
if ($_FILES['proposal_file']['size'] > 20 * 1024 * 1024) {
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("File too large (max 20MB)."));
  exit();
}

// save
$dirDisk = __DIR__ . '/../../uploads/proposals';
if (!is_dir($dirDisk)) { @mkdir($dirDisk, 0777, true); }
$saved   = uniqid(strtolower($type) . '_', true) . '.' . $ext;
$disk    = $dirDisk . '/' . $saved;
$relPath = 'uploads/proposals/' . $saved;

if (!move_uploaded_file($_FILES['proposal_file']['tmp_name'], $disk)) {
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("Failed to save file."));
  exit();
}

// build insert
$userId = (int)($_SESSION['user_id'] ?? 0);
$title  = pathinfo($orig, PATHINFO_FILENAME); // filename as title
$status = 'Pending';

// get user's location to stamp
$stmtU = $conn->prepare("SELECT COALESCE(location,'') AS loc FROM users WHERE id=?");
$stmtU->bind_param('i', $userId); $stmtU->execute();
$userLoc = $stmtU->get_result()->fetch_assoc()['loc'] ?? '';

// find which location column exists
$hasBarangay = $conn->query("SHOW COLUMNS FROM proposals LIKE 'barangay'")->num_rows > 0;
$hasLocation = $conn->query("SHOW COLUMNS FROM proposals LIKE 'location'")->num_rows > 0;

if ($hasBarangay) {
  $stmt = $conn->prepare("
    INSERT INTO proposals (title, type, status, submitted_by, submitted_at, attachment_path, barangay)
    VALUES (?, ?, ?, ?, NOW(), ?, ?)
  ");
  $stmt->bind_param('sssiss', $title, $type, $status, $userId, $relPath, $userLoc);
} elseif ($hasLocation) {
  $stmt = $conn->prepare("
    INSERT INTO proposals (title, type, status, submitted_by, submitted_at, attachment_path, location)
    VALUES (?, ?, ?, ?, NOW(), ?, ?)
  ");
  $stmt->bind_param('sssiss', $title, $type, $status, $userId, $relPath, $userLoc);
} else {
  $stmt = $conn->prepare("
    INSERT INTO proposals (title, type, status, submitted_by, submitted_at, attachment_path)
    VALUES (?, ?, ?, ?, NOW(), ?)
  ");
  $stmt->bind_param('sssis', $title, $type, $status, $userId, $relPath);
}

if ($stmt && $stmt->execute()) {
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("Uploaded “$orig”."));
  exit();
} else {
  @unlink($disk);
  header("Location: " . strtolower($type) . "s.php?msg=" . urlencode("Failed to save record."));
  exit();
}
