<?php
// =====================================================================
// includes/lang/otp_mr.php — Marathi strings for the email-OTP
// registration flow. Same keys as otp_en.php.
// =====================================================================

return [
    'fill_all_fields'        => 'कृपया सर्व आवश्यक फील्ड भरा.',
    'invalid_mobile'         => 'कृपया वैध 10-अंकी मोबाइल नंबर टाका.',
    'invalid_email'          => 'कृपया वैध ईमेल पत्ता टाका.',
    'email_required'         => 'ईमेल पत्ता आवश्यक आहे — तुमचा verification OTP तिथे पाठवला जाईल.',
    'password_too_short'     => 'Password किमान 6 अक्षरांचा असावा.',
    'password_mismatch'      => 'Passwords जुळत नाहीत.',
    'mobile_taken'           => 'हा मोबाइल नंबर आधीच नोंदणीकृत आहे. कृपया Login करा.',
    'email_taken'            => 'हा ईमेल पत्ता आधीच नोंदणीकृत आहे. कृपया Login करा.',
    'session_expired'        => 'तुमचे सत्र संपले आहे. कृपया पेज रीलोड करून पुन्हा प्रयत्न करा.',

    'otp_sent_email'         => 'तुमच्या नोंदणीकृत ईमेल पत्त्यावर verification OTP पाठवण्यात आला आहे.',
    'otp_send_failed'        => 'सध्या OTP ईमेल पाठवता आला नाही. कृपया थोड्या वेळाने पुन्हा प्रयत्न करा.',

    'otp_enter_6'            => 'कृपया 6-अंकी OTP टाका.',
    'otp_verified_ok'        => 'ईमेल यशस्वीरित्या सत्यापित झाला! कृपया तुमची प्रोफाईल पूर्ण करा.',
    'otp_expired'            => 'OTP ची मुदत संपली. कृपया नवीन OTP मागवा.',
    'otp_too_many_attempts'  => 'खूप चुकीचे प्रयत्न झाले. कृपया नवीन OTP मागवा.',
    'otp_no_active'          => 'कृपया नवीन OTP मागवा.',
    'otp_incorrect'          => 'चुकीचा OTP. %d प्रयत्न शिल्लक.',
    'otp_session_mismatch'   => 'तुमचे verification session या ईमेलशी जुळत नाही. कृपया नवीन OTP मागवा.',

    'resend_wait'            => 'तुम्ही %d सेकंदांनंतर OTP पुन्हा पाठवू शकता.',
    'resend_ok'              => 'तुमच्या ईमेलवर नवीन OTP पाठवण्यात आला आहे.',
    'resend_limit_reached'   => 'OTP पुन्हा पाठवण्याची कमाल मर्यादा गाठली आहे. कृपया नोंदणी पुन्हा सुरू करा.',
    'resend_restart'         => 'कृपया नोंदणी पुन्हा सुरू करा.',

    'change_email_prompt'    => 'ईमेल पत्ता बदला',
    'change_email_done'      => 'आता तुम्ही नवीन ईमेल पत्ता टाकू शकता.',

    'rate_limited_email'     => 'या ईमेलसाठी खूप जास्त OTP विनंत्या झाल्या आहेत. कृपया नंतर प्रयत्न करा.',
    'rate_limited_ip'        => 'या डिव्हाइस/नेटवर्कवरून खूप जास्त OTP विनंत्या झाल्या आहेत. कृपया नंतर प्रयत्न करा.',

    'register_failed'        => 'नोंदणी अयशस्वी झाली. कृपया पुन्हा प्रयत्न करा.',
    'register_invalid_state' => 'खाते तयार करण्यापूर्वी कृपया ईमेल सत्यापन पूर्ण करा.',

    'dev_mode_notice'        => 'Local development mode: Gmail SMTP कॉन्फिगर केलेले नाही, त्यामुळे हा OTP ईमेल पाठवण्याऐवजी PHP error log मध्ये लिहिण्यात आला.',

    'lbl_email_verification' => '📧 ईमेल सत्यापन',
    'lbl_otp_sent_to'        => 'OTP पाठवला:',
    'lbl_change_email'       => 'ईमेल पत्ता बदला',
    'lbl_resend_otp'         => 'OTP पुन्हा पाठवा',
    'lbl_verify_continue'    => 'Verify & पुढे जा',
    'lbl_back'               => '← मागे',

    'email_subject'          => 'तुमचा AgriCart नोंदणी OTP',
    'email_greeting'         => 'नमस्कार %s,',
    'email_intro'            => 'तुमचा AgriCart नोंदणी verification OTP आहे:',
    'email_validity'         => 'हा OTP 5 मिनिटांसाठी वैध आहे.',
    'email_warning'          => 'हा OTP कोणाशीही शेअर करू नका. AgriCart टीम कधीही फोन, SMS किंवा ईमेलद्वारे तुमचा OTP विचारणार नाही.',
    'email_not_you'          => 'जर तुम्ही ही विनंती केली नसेल, तर हा ईमेल दुर्लक्षित करा.',
    'email_support'          => 'मदत हवी आहे? AgriCart Support ला support@agricart.example वर संपर्क करा किंवा 1800-419-8888 (24×7) वर कॉल करा.',
    'email_signoff'          => 'धन्यवाद,',
    'email_team'             => 'AgriCart टीम',

    // ── Premium OTP email template (includes/templates/otp_email.html) ──
    'email_heading'          => 'तुमचा ईमेल पत्ता सत्यापित करा',
    'email_intro2'           => 'AgriCart निवडल्याबद्दल धन्यवाद. नोंदणी पूर्ण करण्यासाठी, कृपया खालील One-Time Password (OTP) वापरून तुमचा ईमेल पत्ता सत्यापित करा.',
    'email_note_expiry'      => 'हा OTP %s साठी वैध आहे.',
    'email_note_share'       => 'हा OTP कोणाशीही शेअर करू नका.',
    'email_note_ignore'      => 'जर तुम्ही हे सत्यापन मागितलं नसेल, तर हा ईमेल दुर्लक्षित करू शकता.',
    'email_footer_tagline'   => 'एक Digital Agriculture Service आणि E-Commerce Platform',
    'email_minutes'          => '%d मिनिटं',
];
