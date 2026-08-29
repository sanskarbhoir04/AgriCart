<?php
// =====================================================================
// setup/_guard.php — installer lock check.
//
// Include this at the very top of any one-time setup/admin-generator
// script under setup/. Once setup/.installed exists, these scripts
// refuse to run over HTTP, closing off a class of "forgot to delete
// the setup tool" vulnerabilities.
//
// To (re)run a setup tool intentionally, delete setup/.installed,
// use the tool, then let this guard recreate it (or create it
// yourself) when you're done.
// =====================================================================

$__lockFile = __DIR__ . '/.installed';

if (file_exists($__lockFile)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "This setup tool is disabled because AgriCart has already been installed.\n"
       . "If you intentionally need to re-run it, delete setup/.installed on the server first.";
    exit;
}
