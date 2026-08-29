<?php
// =====================================================================
// pages/update_profile.php — Saves edits made in the "Edit Profile"
// form back to the users table, and refreshes the session so every
// page (header, orders, etc.) reflects the updated info immediately.
//
// Supports updating: profile photo, name, email, mobile, delivery
// address (line 1/2, village/area, city, district, state, pincode),
// primary crop, and (optionally) password. Account Type / role is
// intentionally NOT accepted from this form — that stays controlled by
// the existing Admin/RBAC system (see admin/roles.php, admin/user_action.php).
//
// Every error returns a "message_key" that matches a key in the
// front-end HeaderT[lang].profile translation object, so the message the
// user sees is always shown in whichever language they've selected —
// not just English.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crop_list.php';
require_once __DIR__ . '/../includes/profile_edit_schema.php';
require_once __DIR__ . '/../includes/secure_upload.php';
agri_profile_edit_bootstrap_schema($conn);
header('Content-Type: application/json');

function respond($arr) { echo json_encode($arr); exit; }

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'message_key' => 'errLoginFirst', 'message' => 'Please login first.']);
}

// CSRF — this form is a state-changing POST just like save_address.php,
// so it's held to the same standard.
if (!csrf_verify()) {
    respond(['success' => false, 'message_key' => 'errSaveFailed', 'message' => 'Invalid session, please refresh the page and try again.']);
}

$user_id = (int)$_SESSION['user_id'];

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$mobile  = trim($_POST['mobile'] ?? '');
$crop    = trim($_POST['primary_crop'] ?? '');

// Structured delivery-address fields (new — replaces the old free-text
// "Village, Taluka, District" textarea with real fields).
$addr_line1 = trim($_POST['address_line1'] ?? '');
$addr_line2 = trim($_POST['address_line2'] ?? '');
$village    = trim($_POST['village'] ?? ''); // "Village / Area"
$city       = trim($_POST['city'] ?? '');
$district   = trim($_POST['district'] ?? '');
$state      = trim($_POST['state'] ?? '');
$pincode    = trim($_POST['pincode'] ?? '');

$current_password = (string)($_POST['current_password'] ?? '');
$new_password      = (string)($_POST['new_password'] ?? '');
$confirm_password  = (string)($_POST['confirm_password'] ?? '');
$wants_password_change = ($new_password !== '' || $confirm_password !== '');

// ---- Validation ----
if ($name === '') {
    respond(['success' => false, 'message_key' => 'errName', 'message' => 'Name is required.']);
}
// Indian mobile number: 10 digits, starting 6-9 (matches save_address.php / register.php).
if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    respond(['success' => false, 'message_key' => 'errMobile', 'message' => 'Please enter a valid 10-digit Indian mobile number.']);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message_key' => 'errEmail', 'message' => 'Please enter a valid email address.']);
}
if ($pincode !== '' && !preg_match('/^\d{6}$/', $pincode)) {
    respond(['success' => false, 'message_key' => 'errPincode', 'message' => 'Please enter a valid 6-digit PIN code.']);
}
// Only accept a crop that's actually in the canonical list (or blank —
// crop is optional), so this can never be used to write arbitrary text.
if ($crop !== '' && !in_array($crop, $AGRI_CROPS, true)) {
    respond(['success' => false, 'message_key' => 'errCrop', 'message' => 'Please choose a crop from the list.']);
}

// Fetch the current row so we can compare email + verify the current
// password (needed for both the password change and, as a safety check,
// confirming the account is still valid).
$curStmt = $conn->prepare("SELECT email, password, profile_photo FROM users WHERE id = ? LIMIT 1");
$curStmt->bind_param("i", $user_id);
$curStmt->execute();
$currentRow = $curStmt->get_result()->fetch_assoc();
if (!$currentRow) {
    respond(['success' => false, 'message_key' => 'errSaveFailed', 'message' => 'Could not save profile. Please try again.']);
}

$email_changed = ($email !== '' && $email !== $currentRow['email']);

// If the email is being changed, make sure no other account already uses it.
if ($email_changed) {
    $chkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $chkStmt->bind_param("si", $email, $user_id);
    $chkStmt->execute();
    if ($chkStmt->get_result()->fetch_assoc()) {
        respond(['success' => false, 'message_key' => 'errEmailTaken', 'message' => 'This email is already registered with another account.']);
    }
}

$new_password_hash = null;
if ($wants_password_change) {
    if ($current_password === '') {
        respond(['success' => false, 'message_key' => 'errPwdRequired', 'message' => "Please enter your current password to change your password."]);
    }
    if (!password_verify($current_password, $currentRow['password'])) {
        respond(['success' => false, 'message_key' => 'errWrongPassword', 'message' => 'Current password is incorrect.']);
    }
    if (strlen($new_password) < 6) {
        respond(['success' => false, 'message_key' => 'errPwdShort', 'message' => 'New password must be at least 6 characters.']);
    }
    if ($new_password !== $confirm_password) {
        respond(['success' => false, 'message_key' => 'errPwdMismatch', 'message' => 'New password and confirm password do not match.']);
    }
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
}

// ---- Profile photo (optional) ----
// Keeps the existing photo untouched if no new file was uploaded.
$new_photo_path = null;
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploadResult = agri_secure_upload_image(
        $_FILES['profile_photo'],
        __DIR__ . '/../assets/uploads/profile_photos',
        'assets/uploads/profile_photos',
        2 * 1024 * 1024 // 2MB
    );
    if (!$uploadResult['ok']) {
        respond(['success' => false, 'message_key' => 'errPhoto', 'message' => $uploadResult['error']]);
    }
    $new_photo_path = $uploadResult['path'];
}

// Compose the single-line delivery address that the rest of the app
// (checkout prefill on marketplace.php / rental.php / book_equipment.php)
// already reads from users.saved_address, so those flows automatically
// pick up whatever is saved here — same composition register.php uses.
$composedAddress = trim(implode(', ', array_filter([
    $addr_line1, $addr_line2, $village, $city, $district, $state,
])));

// Build the UPDATE dynamically depending on which optional fields were sent.
$setClauses = [
    'full_name = ?', 'village = ?', 'district = ?', 'address_line1 = ?',
    'address_line2 = ?', 'city = ?', 'state = ?', 'primary_crop = ?',
    'saved_address = ?', 'saved_name = ?',
];
$types  = 'ssssssssss';
$values = [$name, $village, $district, $addr_line1, $addr_line2, $city, $state, $crop, $composedAddress, $name];

if ($mobile !== '') {
    $setClauses[] = 'mobile = ?';
    $setClauses[] = 'saved_mobile = ?';
    $types .= 'ss';
    $values[] = $mobile;
    $values[] = $mobile;
}
if ($email !== '') {
    $setClauses[] = 'email = ?';
    $types .= 's';
    $values[] = $email;
    // Changing the email invalidates the verification this project's
    // OTP flow granted at registration — the new address hasn't been
    // proven yet, so it goes back to unverified rather than silently
    // keeping the old "verified" status on an address nobody confirmed.
    $setClauses[] = 'email_verified = 0';
}
if ($pincode !== '') {
    $setClauses[] = 'saved_pincode = ?';
    $types .= 's';
    $values[] = $pincode;
}
if ($new_photo_path !== null) {
    $setClauses[] = 'profile_photo = ?';
    $types .= 's';
    $values[] = $new_photo_path;
}
if ($new_password_hash !== null) {
    $setClauses[] = 'password = ?';
    $types .= 's';
    $values[] = $new_password_hash;
}

$types .= 'i';
$values[] = $user_id;

$sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    // Delete the old photo file only after the new one is safely saved
    // and committed, so a mid-request failure never leaves the profile
    // pointing at a deleted file.
    if ($new_photo_path !== null && !empty($currentRow['profile_photo']) && $currentRow['profile_photo'] !== $new_photo_path) {
        agri_delete_uploaded_file($currentRow['profile_photo']);
    }

    // Keep the session (and therefore the profile/navbar shown across
    // the site) in sync with what was just saved to the database.
    $_SESSION['user_name']    = $name;
    if ($mobile !== '') { $_SESSION['user'] = $mobile; }
    if ($email !== '')  { $_SESSION['user_email'] = $email; }
    $_SESSION['user_village']  = $village;
    $_SESSION['user_district'] = $district;
    $_SESSION['user_crop']     = $crop;
    if ($new_photo_path !== null) { $_SESSION['user_photo'] = $new_photo_path; }

    respond([
        'success' => true,
        'name'    => $name,
        'mobile'  => $_SESSION['user'] ?? '',
        'email'   => $_SESSION['user_email'] ?? '',
        'crop'    => $crop,
        'address' => $composedAddress,
        'photo'   => $new_photo_path ?? ($currentRow['profile_photo'] ?? ''),
    ]);
} else {
    respond(['success' => false, 'message_key' => 'errSaveFailed', 'message' => 'Could not save profile. Please try again.']);
}
