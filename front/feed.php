<?php

require_once __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Contractnotice\AnnouncementRepository;

header('Content-Type: application/json; charset=UTF-8');

$userId = (int) Session::getLoginUserID();
if ($userId <= 0 || !AnnouncementRepository::isInstalled()) {
    echo json_encode(['notices' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $impersonatorId = Session::isImpersonateActive() ? Session::getImpersonatorId() : null;
    $isImpersonating = $impersonatorId !== null;
    $pollOnly = ($_GET['mode'] ?? '') === 'poll';
    $returnSuppressionNotices = [];
    if ($isImpersonating) {
        foreach (AnnouncementRepository::getForUser((int) $impersonatorId, false) as $notice) {
            $returnSuppressionNotices[] = [
                'id' => (int) $notice['id'],
                'date_mod' => (string) $notice['date_mod'],
            ];
        }
    }

    echo json_encode([
        'session_key' => hash('sha256', session_id()),
        'csrf_token' => Session::getNewCSRFToken(true),
        'user_id' => $userId,
        'is_impersonating' => $isImpersonating,
        'impersonator_id' => $impersonatorId,
        'return_suppression_notices' => $returnSuppressionNotices,
        'notices' => $isImpersonating ? [] : AnnouncementRepository::getForUser($userId, $pollOnly),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        sprintf("contractnotice: unable to retrieve announcements: %s\n", $exception->getMessage())
    );
    http_response_code(500);
    echo json_encode(['notices' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
