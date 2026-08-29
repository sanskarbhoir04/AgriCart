<?php
// =====================================================================
// includes/agri_translate_config.php
//
// Put your Google Cloud Translate API key here. This file should NEVER
// be committed to git and NEVER be sent to the browser/JavaScript — it
// is only ever read on the server inside includes/agri_translate.php.
//
// How to get a key:
//   1. https://console.cloud.google.com/ → create/select a project
//   2. Enable "Cloud Translation API"
//   3. Create an API key under "APIs & Services" → "Credentials"
//   4. (Recommended) Restrict the key to the Cloud Translation API only
//
// Google Cloud Translate has a permanent free tier of 500,000 characters
// per month — product-name translation at AgriCart's scale should stay
// well within that.
//
// If you leave this as an empty string (or the request fails, quota
// runs out, network is down, etc.), AgriCart automatically falls back
// to the built-in offline dictionary translator — a listing is NEVER
// blocked by a translation problem.
// =====================================================================

require_once __DIR__ . '/env.php';
define('AGRI_GOOGLE_TRANSLATE_API_KEY', env('GOOGLE_TRANSLATE_API_KEY', ''));
