<?php

require_once __DIR__ . '/../../../inc/includes.php';

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;
use GlpiPlugin\Contractnotice\Menu;

global $CFG_GLPI;

Session::checkLoginUser();
Manager::checkCanManage();

$managementUrl = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/announcements.php';
$saveUrl = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/save.php';

Html::header(__('Disparar aviso', 'contractnotice'), '', 'admin', Menu::class, 'contractnotice');

try {
    if (!AnnouncementRepository::isInstalled()) {
        TemplateRenderer::getInstance()->display('@contractnotice/announcements/needs-update.html.twig', [
            'plugins_url' => $CFG_GLPI['root_doc'] . '/front/plugin.php',
        ]);
    } else {
        $editId = (int) ($_GET['id'] ?? 0);
        $formData = $editId > 0 ? AnnouncementRepository::get($editId) : null;
        if ($formData === null) {
            $formData = AnnouncementRepository::getBlankFormData();
        } else {
            $formData['start_at'] = substr(str_replace(' ', 'T', $formData['start_at']), 0, 16);
            $formData['end_at'] = $formData['end_at'] === null
                ? ''
                : substr(str_replace(' ', 'T', $formData['end_at']), 0, 16);
        }

        TemplateRenderer::getInstance()->display('@contractnotice/announcements/manage.html.twig', [
            'announcements' => AnnouncementRepository::getForManagement(),
            'form' => $formData,
            'groups' => AnnouncementRepository::getGroups(),
            'profiles' => AnnouncementRepository::getProfiles(),
            'management_url' => $managementUrl,
            'save_url' => $saveUrl,
            'manager_profile' => PLUGIN_CONTRACTNOTICE_MANAGER_PROFILE,
        ]);
    }
} catch (\Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        sprintf("contractnotice: unable to display the announcement manager: %s\n", $exception->getMessage())
    );
    echo '<div class="container-fluid"><div class="alert alert-danger" role="alert">'
        . '<i class="ti ti-alert-triangle me-2"></i>'
        . __('Não foi possível carregar a Central de Avisos. Consulte o log php-errors do GLPI.', 'contractnotice')
        . '</div></div>';
}

Html::footer();
