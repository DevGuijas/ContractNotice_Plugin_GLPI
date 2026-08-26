<?php

require_once __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Contractnotice\AnnouncementRepository;

header('Content-Type: application/json; charset=UTF-8');

$userId = Session::getLoginUserID();
if ($userId <= 0 || !AnnouncementRepository::isInstalled()) {
    echo json_encode(['notices' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    echo json_encode([
        'session_key' => hash('sha256', session_id()),
        'notices' => AnnouncementRepository::getForUser(
            $userId,
            ($_GET['mode'] ?? '') === 'poll'
        ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        sprintf("contractnotice: unable to retrieve announcements: %s\n", $exception->getMessage())
    );
    http_response_code(500);
    echo json_encode(['notices' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
