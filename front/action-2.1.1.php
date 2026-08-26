<?php

require_once __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;

global $CFG_GLPI;

Session::checkLoginUser();
Manager::checkCanManage();

$managementUrl = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/announcements.php';
$setFlash = static function (string $message, string $type = 'success'): void {
    $_SESSION['plugin_contractnotice_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::redirect($managementUrl);
    exit;
}

$submittedToken = (string) ($_POST['plugin_contractnotice_token'] ?? '');
$sessionToken = (string) ($_SESSION['plugin_contractnotice_csrf_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    $setFlash(__('A sessão do formulário expirou. Volte à Central de Avisos e tente novamente.', 'contractnotice'), 'error');
    Html::redirect($managementUrl);
    exit;
}
if (!AnnouncementRepository::isInstalled()) {
    $setFlash(__('O plugin precisa ser atualizado em Configuração > Plugins antes de salvar avisos.', 'contractnotice'), 'error');
    Html::redirect($managementUrl);
    exit;
}

$action = (string) ($_POST['action'] ?? 'save');
$id = (int) ($_POST['id'] ?? 0);

try {
    if ($action === 'delete') {
        AnnouncementRepository::delete($id);
        $setFlash(__('Aviso apagado.', 'contractnotice'));
    } elseif ($action === 'toggle') {
        AnnouncementRepository::toggle($id);
        $setFlash(__('Status do aviso atualizado.', 'contractnotice'));
    } else {
        AnnouncementRepository::save($_POST, $id);
        $setFlash(__('Aviso salvo com sucesso.', 'contractnotice'));
    }
} catch (\DomainException $exception) {
    $setFlash($exception->getMessage(), 'error');
} catch (\Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        'contractnotice: action failed: ' . $exception->getMessage() . PHP_EOL
    );
    $setFlash(__('Não foi possível concluir a operação. Consulte o log php-errors do GLPI.', 'contractnotice'), 'error');
}

Html::redirect($managementUrl);
