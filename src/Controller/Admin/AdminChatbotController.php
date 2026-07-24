<?php

namespace App\Controller\Admin;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/chatbot')]
#[IsGranted('ROLE_ADMIN')]
class AdminChatbotController extends AbstractController
{
    #[Route('/', name: 'app_admin_chatbot', methods: ['GET'])]
    public function index(SessionInterface $session): Response
    {
        // Réinitialiser l'historique si demandé
        $session->remove('chatbot_history');

        return $this->render('admin/chatbot/index.html.twig');
    }

    #[Route('/message', name: 'app_admin_chatbot_message', methods: ['POST'])]
    public function message(Request $request, ChatbotService $chatbot, SessionInterface $session): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $message = trim($data['message'] ?? '');

        if (empty($message)) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        // Récupérer l'historique de la session (max 10 échanges)
        $history = $session->get('chatbot_history', []);

        try {
            $reply = $chatbot->chat($message, $history);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur : ' . $e->getMessage()], 500);
        }

        // Sauvegarder dans l'historique (rôles Gemini : user / model)
        $history[] = ['role' => 'user',  'text' => $message];
        $history[] = ['role' => 'model', 'text' => $reply];

        // Garder seulement les 20 derniers messages (10 échanges)
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        $session->set('chatbot_history', $history);

        return $this->json(['reply' => $reply]);
    }

    #[Route('/reset', name: 'app_admin_chatbot_reset', methods: ['POST'])]
    public function reset(SessionInterface $session): JsonResponse
    {
        $session->remove('chatbot_history');
        return $this->json(['status' => 'ok']);
    }
}
