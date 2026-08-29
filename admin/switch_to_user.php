<?php
// =====================================================================
// admin/switch_to_user.php — "Login as User" from the admin dropdown.
// This does NOT log you out — you stay signed in as the same account
// (user_id/user_name/user stay set, so header.php still shows you as
// logged in) but is_admin is dropped for this session, so you browse
// the site with normal user privileges only. Log in as Administration
// again from admin/login.php to get admin rights back.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();

unset($_SESSION['is_admin']);

header('Location: ../index.php');
exit;
