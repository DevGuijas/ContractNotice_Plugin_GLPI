<?php

namespace GlpiPlugin\Contractnotice\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Contractnotice\AnnouncementRepository;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnouncementPollingController extends AbstractController
{
    #[Route('/Announcements/Feed', name: 'contractnotice_announcements_feed', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $userId = Session::getLoginUserID();
        if ($userId <= 0) {
            return new JsonResponse(['notices' => []], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'session_key' => hash('sha256', session_id()),
            'notices' => AnnouncementRepository::getForUser(
                $userId,
                $request->query->getString('mode') === 'poll'
            ),
        ]);
    }
}
