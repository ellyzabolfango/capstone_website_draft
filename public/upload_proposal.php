<?php
// upload_proposal.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
auth_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploadDir = __DIR__ . '/../public/uploads/proposals/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // create folder if not exists
    }

    $fileName = basename($_FILES['file']['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        // Save file path to DB
        $stmt = $conn->prepare("UPDATE proposals SET attachment_path = ? WHERE id = ?");
        $pathForDb = '/public/uploads/proposals/' . $fileName;
        $stmt->bind_param("si", $pathForDb, $_POST['proposal_id']);
        $stmt->execute();

        echo "✅ File uploaded successfully!";
    } else {
        echo "❌ Upload failed.";
    }
}
