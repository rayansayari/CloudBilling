<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $userRepository->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC');
        
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin/user/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword(
                $passwordHasher->hashPassword($user, $plainPassword)
            );

            // Sauvegarder le descripteur facial si fourni
            $faceJson = $request->request->get('face_descriptor');
            if ($faceJson) {
                $descriptor = json_decode($faceJson, true);
                if (is_array($descriptor) && count($descriptor) === 128) {
                    $user->setFaceDescriptor($descriptor);
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // ─── Envoi de l'email de bienvenue ──────────────────────────
            $roles = $user->getRoles();
            $roleLabel = in_array('ROLE_ADMIN', $roles)
                ? '👑 Administrateur'
                : (in_array('ROLE_CONSULTANT', $roles) ? '💼 Consultant' : '👤 Utilisateur');

            try {
                $htmlBody = $this->renderView('emails/welcome_user.html.twig', [
                    'userEmail'     => $user->getEmail(),
                    'plainPassword' => $plainPassword,
                    'userRole'      => $roleLabel,
                    'userName'      => $user->getFullName(),
                    'appUrl'        => $request->getSchemeAndHttpHost() . '/login',
                ]);

                $fromAddress = $_ENV['MAILER_FROM_ADDRESS'] ?? 'noreply@cloudbill.com';

                $email = (new Email())
                    ->from(new Address($fromAddress, 'CloudBill Platform'))
                    ->to(new Address($user->getEmail(), $user->getFullName()))
                    ->replyTo(new Address($fromAddress, 'CloudBill Support'))
                    ->subject('[CloudBill] Vos identifiants de connexion')
                    ->priority(Email::PRIORITY_HIGH)
                    ->text(
                        "Bonjour " . $user->getFullName() . ",\n\n" .
                        "Votre compte CloudBill a été créé.\n\n" .
                        "Email    : " . $user->getEmail() . "\n" .
                        "Mot de passe : " . $plainPassword . "\n" .
                        "Rôle     : " . $roleLabel . "\n\n" .
                        "Connectez-vous sur : " . $request->getSchemeAndHttpHost() . "/login\n\n" .
                        "Changez votre mot de passe dès la première connexion.\n\n" .
                        "Cordialement,\nL'équipe CloudBill"
                    )
                    ->html($htmlBody);

                $mailer->send($email);
                $emailStatus = ' 📧 Email envoyé à ' . $user->getEmail() . '.';
            } catch (\Exception $e) {
                $emailStatus = ' ⚠️ Email non envoyé (' . $e->getMessage() . ').';
            }
            // ────────────────────────────────────────────────────────────

            $hasFace = $user->getFaceDescriptor() !== null;
            $this->addFlash('success', 'Consultant créé avec succès.' . ($hasFace ? ' ✅ Visage enregistré.' : '') . $emailStatus);

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }


    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        // Don't require password when editing
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($plainPassword = $form->get('plainPassword')->getData()) {
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $plainPassword)
                );
            }

            // Mise à jour du descripteur facial
            $faceJson = $request->request->get('face_descriptor');
            if ($faceJson) {
                $descriptor = json_decode($faceJson, true);
                if (is_array($descriptor) && count($descriptor) === 128) {
                    $user->setFaceDescriptor($descriptor);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur mis à jour avec succès.');

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            // Prevent self-deletion
            if ($user === $this->getUser()) {
                $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
                return $this->redirectToRoute('app_admin_user_index');
            }
            
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * API AJAX : Enregistrer le descripteur facial d'un consultant existant
     */
    #[Route('/{id}/enroll-face', name: 'app_admin_user_enroll_face', methods: ['POST'])]
    public function enrollFace(Request $request, User $user, EntityManagerInterface $em): JsonResponse
    {
        $data       = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!$descriptor || !is_array($descriptor) || count($descriptor) !== 128) {
            return $this->json(['success' => false, 'message' => 'Descripteur invalide.'], 400);
        }

        $user->setFaceDescriptor($descriptor);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Visage enregistré avec succès pour ' . $user->getFullName()]);
    }
}
