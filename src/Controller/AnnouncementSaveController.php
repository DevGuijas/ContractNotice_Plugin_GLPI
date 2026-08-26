<?php

namespace GlpiPlugin\Contractnotice\Controller;

use DomainException;
use Glpi\Controller\AbstractController;
use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;
use Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AnnouncementSaveController extends AbstractController
{
    #[Route('/Announcements/Save', name: 'contractnotice_announcements_save', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Manager::checkCanManage();
        if (!$request->isMethod('POST')) {
            throw new MethodNotAllowedHttpException(['POST']);
        }

        $action = $request->request->getString('action', 'save');
        $id = $request->request->getInt('id');

        try {
            if ($action === 'delete') {
                AnnouncementRepository::delete($id);
                Session::addMessageAfterRedirect(__('Aviso apagado.', 'contractnotice'));
            } elseif ($action === 'toggle') {
                AnnouncementRepository::toggle($id);
                Session::addMessageAfterRedirect(__('Status do aviso atualizado.', 'contractnotice'));
            } else {
                AnnouncementRepository::save($request->request->all(), $id);
                Session::addMessageAfterRedirect(__('Aviso salvo com sucesso.', 'contractnotice'));
            }
        } catch (DomainException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
        }

        return new RedirectResponse($this->managementUrl($request));
    }

    private function managementUrl(Request $request): string
    {
        return rtrim($request->getBaseUrl(), '/') . '/plugins/contractnotice/Announcements';
    }
}
