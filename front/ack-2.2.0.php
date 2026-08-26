<?php

require_once __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Contractnotice\AnnouncementRepository;

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
$userId = (int) Session::getLoginUserID();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $userId <= 0 || !AnnouncementRepository::isInstalled()) {
    http_response_code(400);
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    AnnouncementRepository::acknowledgeDaily(
        (int) ($_POST['id'] ?? 0),
        $userId,
        (string) ($_POST['date_mod'] ?? '')
    );
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\DomainException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $exception) {
    Toolbox::logInFile('php-errors', 'contractnotice: daily acknowledgement failed: ' . $exception->getMessage() . PHP_EOL);
    http_response_code(500);
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
