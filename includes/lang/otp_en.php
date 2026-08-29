<?php
// =====================================================================
// includes/lang/otp_en.php — English strings for the email-OTP
// registration flow (backend validation/status messages + OTP email).
// Keep the ARRAY KEYS identical across otp_en.php / otp_mr.php / otp_hi.php
// so agri_otp_lang() can safely fall back key-by-key to English.
// =====================================================================

return [
    // ── Step 1 / general ──
    'fill_all_fields'        => 'Please fill all required fields.',
    'invalid_mobile'         => 'Please enter a valid 10-digit mobile number.',
    'invalid_email'          => 'Please enter a valid email address.',
    'email_required'         => 'Email address is required — your verification OTP will be sent there.',
    'password_too_short'     => 'Password must be at least 6 characters.',
    'password_mismatch'      => 'Passwords do not match.',
    'mobile_taken'           => 'This mobile number is already registered. Please login.',
    'email_taken'            => 'This email address is already registered. Please login.',
    'session_expired'        => 'Your session expired. Please reload the page and try again.',

    // ── OTP send / email-sent banner ──
    'otp_sent_email'         => 'A verification OTP has been sent to your registered email address.',
    'otp_send_failed'        => 'Could not send the OTP email right now. Please try again shortly.',

    // ── OTP verify ──
    'otp_enter_6'            => 'Please enter the 6-digit OTP.',
    'otp_verified_ok'        => 'Email verified successfully! Please complete your profile.',
    'otp_expired'            => 'OTP expired. Please request a new one.',
    'otp_too_many_attempts'  => 'Too many incorrect attempts. Please request a new OTP.',
    'otp_no_active'          => 'Please request a new OTP.',
    'otp_incorrect'          => 'Incorrect OTP. %d attempt(s) remaining.',
    'otp_session_mismatch'   => 'Your verification session no longer matches this email. Please request a new OTP.',

    // ── Resend ──
    'resend_wait'            => 'You can resend the OTP after %d seconds.',
    'resend_ok'              => 'A new OTP has been sent to your email.',
    'resend_limit_reached'   => 'Maximum OTP resend limit reached. Please restart registration.',
    'resend_restart'         => 'Please start registration again.',

    // ── Change email ──
    'change_email_prompt'    => 'Change Email Address',
    'change_email_done'      => 'You can now enter a new email address.',

    // ── Rate limiting ──
    'rate_limited_email'     => 'Too many OTP requests for this email. Please try again later.',
    'rate_limited_ip'        => 'Too many OTP requests from this device/network. Please try again later.',

    // ── Registration completion ──
    'register_failed'        => 'Registration failed. Please try again.',
    'register_invalid_state' => 'Please complete email verification before creating your account.',

    // ── Local dev-mode banner (never shows the OTP itself) ──
    'dev_mode_notice'        => 'Local development mode: Gmail SMTP is not configured, so this OTP was written to the PHP error log instead of being emailed.',

    // ── UI labels (also mirrored into the page-level JS translation table) ──
    'lbl_email_verification' => '📧 Email Verification',
    'lbl_otp_sent_to'        => 'OTP sent to:',
    'lbl_change_email'       => 'Change Email Address',
    'lbl_resend_otp'         => 'Resend OTP',
    'lbl_verify_continue'    => 'Verify & Continue',
    'lbl_back'               => '← Back',

    // ── OTP email content ──
    'email_subject'          => 'Your AgriCart Registration OTP',
    'email_greeting'         => 'Hello %s,',
    'email_intro'            => 'Your AgriCart registration verification OTP is:',
    'email_validity'         => 'This OTP is valid for 5 minutes.',
    'email_warning'          => 'Do not share this OTP with anyone. AgriCart staff will never ask you for your OTP over phone, SMS, or email.',
    'email_not_you'          => 'If you did not request this, you can safely ignore this email.',
    'email_support'          => 'Need help? Contact AgriCart Support at support@agricart.example or call 1800-419-8888 (24×7).',
    'email_signoff'          => 'Thank you,',
    'email_team'             => 'AgriCart Team',

    // ── Premium OTP email template (includes/templates/otp_email.html) ──
    'email_heading'          => 'Verify Your Email Address',
    'email_intro2'           => 'Thank you for choosing AgriCart. To complete your registration, please verify your email address using the One-Time Password (OTP) below.',
    'email_note_expiry'      => 'This OTP is valid for %s.',
    'email_note_share'       => 'Do not share this OTP with anyone.',
    'email_note_ignore'      => "If you didn't request this verification, you can safely ignore this email.",
    'email_footer_tagline'   => 'A Digital Agriculture Service and E-Commerce Platform',
    'email_minutes'          => '%d minutes',
];
