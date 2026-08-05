<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/chat')]
#[IsGranted('ROLE_ADMIN')]
class AdminChatController extends AbstractController
{
    #[Route('', name: 'app_admin_chat_index', methods: ['GET'])]
    public function index(UserRepository $userRepo): Response
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();
        
        // Fetch all consultants & users except current admin
        $allUsers = $userRepo->createQueryBuilder('u')
            ->where('u.id != :myId')
            ->setParameter('myId', $currentUser->getId())
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/chat/index.html.twig', [
            'allUsers' => $allUsers,
        ]);
    }
}
