<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
    adminFlash('error', 'Invalid session token.');
    adminRedirect('index.php');
}

$auth->logout();
adminRedirect('login.php');
