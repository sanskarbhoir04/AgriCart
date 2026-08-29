<?php
// =====================================================================
// includes/lang/otp_hi.php — Hindi strings for the email-OTP
// registration flow. Same keys as otp_en.php.
// =====================================================================

return [
    'fill_all_fields'        => 'कृपया सभी आवश्यक फ़ील्ड भरें.',
    'invalid_mobile'         => 'कृपया वैध 10-अंकों का मोबाइल नंबर दर्ज करें.',
    'invalid_email'          => 'कृपया वैध ईमेल पता दर्ज करें.',
    'email_required'         => 'ईमेल पता आवश्यक है — आपका verification OTP वहीं भेजा जाएगा.',
    'password_too_short'     => 'Password कम से कम 6 अक्षरों का होना चाहिए.',
    'password_mismatch'      => 'Passwords मेल नहीं खाते.',
    'mobile_taken'           => 'यह मोबाइल नंबर पहले से पंजीकृत है. कृपया Login करें.',
    'email_taken'            => 'यह ईमेल पता पहले से पंजीकृत है. कृपया Login करें.',
    'session_expired'        => 'आपका सत्र समाप्त हो गया है. कृपया पेज रीलोड करके पुनः प्रयास करें.',

    'otp_sent_email'         => 'आपके पंजीकृत ईमेल पते पर verification OTP भेज दिया गया है.',
    'otp_send_failed'        => 'फ़िलहाल OTP ईमेल नहीं भेजा जा सका. कृपया कुछ देर बाद पुनः प्रयास करें.',

    'otp_enter_6'            => 'कृपया 6-अंकों का OTP दर्ज करें.',
    'otp_verified_ok'        => 'ईमेल सफलतापूर्वक सत्यापित हुआ! कृपया अपनी प्रोफ़ाइल पूरी करें.',
    'otp_expired'            => 'OTP की समय सीमा समाप्त हो गई. कृपया नया OTP मंगवाएं.',
    'otp_too_many_attempts'  => 'बहुत सारे गलत प्रयास हो गए. कृपया नया OTP मंगवाएं.',
    'otp_no_active'          => 'कृपया नया OTP मंगवाएं.',
    'otp_incorrect'          => 'गलत OTP. %d प्रयास शेष.',
    'otp_session_mismatch'   => 'आपका verification session इस ईमेल से मेल नहीं खाता. कृपया नया OTP मंगवाएं.',

    'resend_wait'            => 'आप %d सेकंड बाद OTP दोबारा भेज सकते हैं.',
    'resend_ok'              => 'आपके ईमेल पर नया OTP भेज दिया गया है.',
    'resend_limit_reached'   => 'OTP दोबारा भेजने की अधिकतम सीमा पूरी हो गई है. कृपया पंजीकरण फिर से शुरू करें.',
    'resend_restart'         => 'कृपया पंजीकरण फिर से शुरू करें.',

    'change_email_prompt'    => 'ईमेल पता बदलें',
    'change_email_done'      => 'अब आप नया ईमेल पता दर्ज कर सकते हैं.',

    'rate_limited_email'     => 'इस ईमेल के लिए बहुत अधिक OTP अनुरोध हुए हैं. कृपया बाद में प्रयास करें.',
    'rate_limited_ip'        => 'इस डिवाइस/नेटवर्क से बहुत अधिक OTP अनुरोध हुए हैं. कृपया बाद में प्रयास करें.',

    'register_failed'        => 'पंजीकरण विफल हुआ. कृपया पुनः प्रयास करें.',
    'register_invalid_state' => 'खाता बनाने से पहले कृपया ईमेल सत्यापन पूरा करें.',

    'dev_mode_notice'        => 'Local development mode: Gmail SMTP कॉन्फ़िगर नहीं है, इसलिए यह OTP ईमेल भेजने के बजाय PHP error log में लिखा गया.',

    'lbl_email_verification' => '📧 ईमेल सत्यापन',
    'lbl_otp_sent_to'        => 'OTP भेजा गया:',
    'lbl_change_email'       => 'ईमेल पता बदलें',
    'lbl_resend_otp'         => 'OTP दोबारा भेजें',
    'lbl_verify_continue'    => 'Verify करें & आगे बढ़ें',
    'lbl_back'               => '← वापस',

    'email_subject'          => 'आपका AgriCart पंजीकरण OTP',
    'email_greeting'         => 'नमस्ते %s,',
    'email_intro'            => 'आपका AgriCart पंजीकरण verification OTP है:',
    'email_validity'         => 'यह OTP 5 मिनट के लिए मान्य है.',
    'email_warning'          => 'इस OTP को किसी के साथ साझा न करें. AgriCart टीम कभी भी फ़ोन, SMS या ईमेल के ज़रिए आपका OTP नहीं पूछेगी.',
    'email_not_you'          => 'यदि आपने यह अनुरोध नहीं किया है, तो आप इस ईमेल को नज़रअंदाज़ कर सकते हैं.',
    'email_support'          => 'मदद चाहिए? AgriCart Support से support@agricart.example पर संपर्क करें या 1800-419-8888 (24×7) पर कॉल करें.',
    'email_signoff'          => 'धन्यवाद,',
    'email_team'             => 'AgriCart टीम',

    // ── Premium OTP email template (includes/templates/otp_email.html) ──
    'email_heading'          => 'अपना ईमेल पता सत्यापित करें',
    'email_intro2'           => 'AgriCart चुनने के लिए धन्यवाद. पंजीकरण पूरा करने के लिए, कृपया नीचे दिए गए One-Time Password (OTP) का उपयोग करके अपना ईमेल पता सत्यापित करें.',
    'email_note_expiry'      => 'यह OTP %s के लिए मान्य है.',
    'email_note_share'       => 'इस OTP को किसी के साथ साझा न करें.',
    'email_note_ignore'      => 'यदि आपने यह सत्यापन नहीं मांगा है, तो आप इस ईमेल को नज़रअंदाज़ कर सकते हैं.',
    'email_footer_tagline'   => 'एक Digital Agriculture Service और E-Commerce Platform',
    'email_minutes'          => '%d मिनट',
];
