<?php

namespace GlpiPlugin\Contractnotice\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;
use GlpiPlugin\Contractnotice\Menu;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnouncementsController extends AbstractController
{
    #[Route('/Announcements', name: 'contractnotice_announcements', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Manager::checkCanManage();

        $pageData = [
            'page_title' => __('Disparar aviso', 'contractnotice'),
            'menu_item' => Menu::class,
        ];

        if (!AnnouncementRepository::isInstalled()) {
            return $this->render('@contractnotice/announcements/needs-update.html.twig', $pageData + [
                'plugins_url' => rtrim($request->getBaseUrl(), '/') . '/front/plugin.php',
            ]);
        }

        $editId = $request->query->getInt('id');
        $formData = $editId > 0 ? AnnouncementRepository::get($editId) : null;
        if ($formData === null) {
            $formData = AnnouncementRepository::getBlankFormData();
        } else {
            $formData['start_at'] = substr(str_replace(' ', 'T', $formData['start_at']), 0, 16);
            $formData['end_at'] = $formData['end_at'] === null
                ? ''
                : substr(str_replace(' ', 'T', $formData['end_at']), 0, 16);
        }

        return $this->render('@contractnotice/announcements/manage.html.twig', $pageData + [
            'announcements' => AnnouncementRepository::getForManagement(),
            'form' => $formData,
            'groups' => AnnouncementRepository::getGroups(),
            'profiles' => AnnouncementRepository::getProfiles(),
            'management_url' => $this->managementUrl($request),
            'save_url' => $this->managementUrl($request, '/Save'),
            'manager_profile' => PLUGIN_CONTRACTNOTICE_MANAGER_PROFILE,
        ]);
    }

    private function managementUrl(Request $request, string $suffix = ''): string
    {
        return rtrim($request->getBaseUrl(), '/') . '/plugins/contractnotice/Announcements' . $suffix;
    }
}
