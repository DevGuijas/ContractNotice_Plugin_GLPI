<?php

require_once __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;

global $CFG_GLPI;

Session::checkLoginUser();
Manager::checkCanManage();

$managementUrl = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/announcements.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::redirect($managementUrl);
}

// The page issues a standalone token so the three action forms can safely share it.
Session::checkCSRF($_POST, true);
if (!AnnouncementRepository::isInstalled()) {
    Session::addMessageAfterRedirect(
        __('O plugin precisa ser atualizado em Configuração > Plugins antes de salvar avisos.', 'contractnotice'),
        false,
        ERROR
    );
    Html::redirect($managementUrl);
}

$action = (string) ($_POST['action'] ?? 'save');
$id = (int) ($_POST['id'] ?? 0);

try {
    if ($action === 'delete') {
        AnnouncementRepository::delete($id);
        Session::addMessageAfterRedirect(__('Aviso apagado.', 'contractnotice'));
    } elseif ($action === 'toggle') {
        AnnouncementRepository::toggle($id);
        Session::addMessageAfterRedirect(__('Status do aviso atualizado.', 'contractnotice'));
    } else {
        AnnouncementRepository::save($_POST, $id);
        Session::addMessageAfterRedirect(__('Aviso salvo com sucesso.', 'contractnotice'));
    }
} catch (\DomainException $exception) {
    Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
} catch (\Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        sprintf("contractnotice: unable to save an announcement: %s\n", $exception->getMessage())
    );
    Session::addMessageAfterRedirect(
        __('Não foi possível salvar o aviso. Consulte o log php-errors do GLPI.', 'contractnotice'),
        false,
        ERROR
    );
}

Html::redirect($managementUrl);
