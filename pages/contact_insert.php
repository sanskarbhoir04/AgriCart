<?php
// =====================================================
// AgriCart — Contact Form Insert Handler
// XAMPP: C:\xampp\htdocs\AgriCart\contact_insert.php
// फक्त POST insert logic — form contact.php madhech distay,
// pan actual save karayla ha vegla backend file run hoto (PRG pattern)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

// Direct GET ने (browser madhe URL टाकून) access kela tar form var pathva
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['agricart_contact_submit'])) {
    header('Location: contact.php');
    exit;
}

$fullName = trim($_POST['fullName'] ?? '');
$mobile   = trim($_POST['mobile']   ?? '');
$email    = trim($_POST['email']    ?? '');
$priority = trim($_POST['priority'] ?? 'low');
$topics   = isset($_POST['topics']) ? implode(', ', $_POST['topics']) : '';
$message  = trim($_POST['message']  ?? '');

// ── Validation ──────────────────────────────────────
if (empty($fullName) || empty($mobile) || empty($message)) {
    $_SESSION['formError'] = "कृपया नाव, मोबाइल आणि संदेश (सर्व * असलेले fields) भरा.";
    header('Location: contact.php#ac-form');
    exit;
}

if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    $_SESSION['formError'] = "10-digit वैध मोबाइल नंबर टाका.";
    header('Location: contact.php#ac-form');
    exit;
}

// ── Insert ───────────────────────────────────────────
$ticketNumber = 'AGC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
$subject      = $topics !== '' ? $topics : ucfirst($priority) . ' priority query';
$userId       = $_SESSION['user_id'] ?? null;
// Email column NOT NULL aahe DB madhe — fall back safely
$emailToSave  = $email !== '' ? $email : 'not-provided@agricart.local';

$stmt = $conn->prepare(
    "INSERT INTO contact_messages (ticket_number, user_id, name, email, phone, subject, message, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'new')"
);
$stmt->bind_param("sisssss", $ticketNumber, $userId, $fullName, $emailToSave, $mobile, $subject, $message);

if ($stmt->execute()) {
    // Notify Admin (spec §14 "New Complaint") — best-effort.
    require_once __DIR__ . '/../includes/admin_notifications_schema.php';
    agri_notify_admin(
        $conn,
        'new_complaint',
        'New Contact Message — ' . $ticketNumber,
        ($fullName ?: 'A visitor') . ': ' . mb_substr($subject ?: $message, 0, 120),
        'index.php'
    );

    // Insert success -> contact.php var redirect, tyala ticket number pass
    header('Location: contact.php?ticket=' . urlencode($ticketNumber) . '&submitted=1#ac-form');
    exit;
} else {
    $_SESSION['formError'] = "संदेश save करताना अडचण आली. पुन्हा try करा.";
    header('Location: contact.php#ac-form');
    exit;
}
