<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/face-login')]
class FaceLoginController extends AbstractController
{
    #[Route('', name: 'app_face_login', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        return $this->render('face_login/index.html.twig');
    }

    /**
     * Reçoit un descripteur facial depuis le JS, trouve l'utilisateur correspondant,
     * et retourne un token de session pour le connecter.
     */
    #[Route('/verify', name: 'app_face_login_verify', methods: ['POST'])]
    public function verify(Request $request, UserRepository $userRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $incomingDescriptor = $data['descriptor'] ?? null;

        if (!$incomingDescriptor || !is_array($incomingDescriptor) || count($incomingDescriptor) !== 128) {
            return $this->json(['success' => false, 'message' => 'Descripteur facial invalide.'], 400);
        }

        // Charger tous les utilisateurs ayant un visage enregistré
        $users = $userRepo->findAllWithFaceDescriptor();

        $bestMatch  = null;
        $bestDist   = PHP_FLOAT_MAX;
        $threshold  = 0.5; // Distance euclidienne max (0.4 = strict, 0.6 = souple)

        foreach ($users as $user) {
            $storedDescriptor = $user->getFaceDescriptor();
            if (!$storedDescriptor || count($storedDescriptor) !== 128) {
                continue;
            }

            $distance = $this->euclideanDistance($incomingDescriptor, $storedDescriptor);

            if ($distance < $bestDist) {
                $bestDist  = $distance;
                $bestMatch = $user;
            }
        }

        if ($bestMatch && $bestDist <= $threshold) {
            // Retourner les infos de l'utilisateur reconnu pour que le JS redirige
            return $this->json([
                'success'  => true,
                'distance' => round($bestDist, 4),
                'user'     => [
                    'id'       => $bestMatch->getId(),
                    'name'     => $bestMatch->getFullName(),
                    'email'    => $bestMatch->getEmail(),
                    'isAdmin'  => in_array('ROLE_ADMIN', $bestMatch->getRoles()),
                ],
                'token'    => $this->generateFaceToken($bestMatch->getId()),
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Visage non reconnu. Veuillez vous connecter avec votre mot de passe.',
        ]);
    }

    /**
     * Endpoint de connexion après vérification faciale côté client.
     * Reçoit le token signé et connecte l'utilisateur via un formulaire POST.
     */
    #[Route('/authenticate', name: 'app_face_authenticate', methods: ['POST'])]
    public function authenticate(
        Request $request,
        UserRepository $userRepo,
        Security $security
    ): Response {
        $userId = (int) $request->request->get('user_id');
        $token  = $request->request->get('face_token');

        if (!$userId || !$token || !$this->verifyFaceToken($userId, $token)) {
            $this->addFlash('error', 'Authentification faciale échouée. Token invalide.');
            return $this->redirectToRoute('app_face_login');
        }

        $user = $userRepo->find($userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_face_login');
        }

        // Connexion manuelle via Security helper
        $security->login($user, 'security.authenticator.form_login.main', 'main');
        
        return $this->redirectToRoute('app_dashboard');
    }

    // ==================== Helpers ====================

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $diff = ($a[$i] ?? 0) - ($b[$i] ?? 0);
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    /**
     * Génère un token signé temporaire (valide 5 minutes) pour le flux de connexion.
     */
    private function generateFaceToken(int $userId): string
    {
        $expires = time() + 300; // 5 minutes
        $raw     = $userId . ':' . $expires;
        $sig     = hash_hmac('sha256', $raw, $_ENV['APP_SECRET'] ?? 'cloudbill_secret');
        return base64_encode($raw . ':' . $sig);
    }

    private function verifyFaceToken(int $userId, string $token): bool
    {
        $decoded = base64_decode($token);
        $parts   = explode(':', $decoded);
        if (count($parts) !== 3) return false;

        [$id, $expires, $sig] = $parts;

        if ((int) $id !== $userId || (int) $expires < time()) return false;

        $raw      = $id . ':' . $expires;
        $expected = hash_hmac('sha256', $raw, $_ENV['APP_SECRET'] ?? 'cloudbill_secret');
        return hash_equals($expected, $sig);
    }
}
