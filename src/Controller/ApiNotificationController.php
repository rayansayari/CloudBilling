<?php

namespace App\Controller;

use App\Service\SmartNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class ApiNotificationController extends AbstractController
{
    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(SmartNotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        $userId = $user ? $user->getId() : null;

        $data = $notificationService->getNotifications($userId);

        return $this->json($data);
    }
}
