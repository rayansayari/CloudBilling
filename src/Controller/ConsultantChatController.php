<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ConsultantChatController extends AbstractController
{
    #[Route('', name: 'app_consultant_chat_index', methods: ['GET'])]
    public function index(UserRepository $userRepo): Response
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        
        $allUsers = $userRepo->createQueryBuilder('u')
            ->where('u.id != :myId')
            ->setParameter('myId', $currentUser->getId())
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('chat/index.html.twig', [
            'allUsers' => $allUsers,
        ]);
    }
}
