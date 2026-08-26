<?php

namespace GlpiPlugin\Contractnotice\Controller;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Controller\AbstractController;
use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;
use GlpiPlugin\Contractnotice\Menu;
use Html;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnouncementsController extends AbstractController
{
    #[Route('/Announcements', name: 'contractnotice_announcements', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Manager::checkCanManage();

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

        $content = TemplateRenderer::getInstance()->render('@contractnotice/announcements/manage.html.twig', [
            'announcements' => AnnouncementRepository::getForManagement(),
            'form' => $formData,
            'groups' => AnnouncementRepository::getGroups(),
            'profiles' => AnnouncementRepository::getProfiles(),
            'management_url' => $this->managementUrl($request),
            'save_url' => $this->managementUrl($request, '/Save'),
            'manager_profile' => PLUGIN_CONTRACTNOTICE_MANAGER_PROFILE,
        ]);

        ob_start();
        Html::header(
            __('Disparar aviso', 'contractnotice'),
            $request->getPathInfo(),
            'admin',
            Menu::class,
            'contractnotice'
        );
        echo $content;
        Html::footer();

        return new Response((string) ob_get_clean());
    }

    private function managementUrl(Request $request, string $suffix = ''): string
    {
        return rtrim($request->getBaseUrl(), '/') . '/plugins/contractnotice/Announcements' . $suffix;
    }
}
