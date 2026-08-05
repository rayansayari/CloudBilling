<?php

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ApiChatController extends AbstractController
{
    /**
     * Get list of messages for channel or direct conversation.
     */
    #[Route('/messages', name: 'api_chat_messages', methods: ['GET'])]
    public function getMessages(
        Request $request,
        ChatMessageRepository $chatRepo,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $channel = $request->query->get('channel');
        $recipientId = $request->query->getInt('user_id');

        if ($channel) {
            $messages = $chatRepo->findChannelMessages($channel, 100);
        } elseif ($recipientId > 0) {
            $otherUser = $userRepo->find($recipientId);
            if (!$otherUser) {
                return $this->json(['error' => 'Utilisateur introuvable.'], 404);
            }
            // Mark messages from other user as read
            $chatRepo->markAsRead($otherUser, $currentUser);
            $messages = $chatRepo->findDirectMessages($currentUser, $otherUser, 100);
        } else {
            // Default to general channel
            $messages = $chatRepo->findChannelMessages('general', 100);
        }

        $formatted = array_map(function (ChatMessage $msg) use ($currentUser) {
            $sender = $msg->getSender();
            return [
                'id'            => $msg->getId(),
                'senderId'      => $sender?->getId(),
                'senderName'    => $sender?->getFullName() ?? 'Utilisateur',
                'senderRoles'   => $sender?->getRoles() ?? [],
                'senderAvatar'  => 'https://ui-avatars.com/api/?name=' . urlencode($sender?->getFullName() ?? 'U') . '&background=3b0764&color=a78bfa&bold=true',
                'isMe'          => $sender?->getId() === $currentUser->getId(),
                'content'       => $msg->getContent(),
                'attachmentUrl' => $msg->getAttachmentUrl(),
                'channel'       => $msg->getChannel(),
                'time'          => $msg->getCreatedAt()?->format('H:i') ?? '',
                'date'          => $msg->getCreatedAt()?->format('d/m/Y') ?? '',
                'isRead'        => $msg->isRead()
            ];
        }, $messages);

        return $this->json([
            'success'         => true,
            'currentUserId'   => $currentUser->getId(),
            'currentUserName' => $currentUser->getFullName(),
            'currentRoles'    => $currentUser->getRoles(),
            'messages'        => $formatted
        ]);
    }


    /**
     * Send a new message.
     */
    #[Route('/send', name: 'api_chat_send', methods: ['POST'])]
    public function sendMessage(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User $sender */
        $sender = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $content = trim($data['content'] ?? '');
        $channel = $data['channel'] ?? null;
        $recipientId = isset($data['recipient_id']) ? (int)$data['recipient_id'] : null;

        if (empty($content)) {
            return $this->json(['error' => 'Le message ne peut pas être vide.'], 400);
        }

        $msg = new ChatMessage();
        $msg->setSender($sender);
        $msg->setContent($content);

        if ($recipientId && $recipientId > 0) {
            $recipient = $userRepo->find($recipientId);
            if (!$recipient) {
                return $this->json(['error' => 'Destinataire introuvable.'], 404);
            }
            $msg->setRecipient($recipient);
        } else {
            $msg->setChannel($channel ?: 'general');
        }

        $em->persist($msg);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => [
                'id'           => $msg->getId(),
                'senderId'     => $sender->getId(),
                'senderName'   => $sender->getFullName(),
                'senderAvatar' => 'https://ui-avatars.com/api/?name=' . urlencode($sender->getFullName()) . '&background=3b0764&color=a78bfa&bold=true',
                'isMe'         => true,
                'content'      => $msg->getContent(),
                'channel'      => $msg->getChannel(),
                'time'         => $msg->getCreatedAt()?->format('H:i') ?? '',
                'date'         => $msg->getCreatedAt()?->format('d/m/Y') ?? '',
            ]
        ]);
    }

    /**
     * Get latest message ID not sent by current user (for notification polling).
     * Also returns count of unread DMs.
     */
    #[Route('/unread', name: 'api_chat_unread', methods: ['GET'])]
    public function getUnreadCount(ChatMessageRepository $chatRepo): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Count unread DMs addressed to current user
        $dmCount = $chatRepo->getUnreadCount($currentUser);

        // Get the latest message ID NOT sent by current user (channels + DMs)
        $latestMsg = $chatRepo->createQueryBuilder('c')
            ->where('c.sender != :user')
            ->setParameter('user', $currentUser)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $latestMsgId = $latestMsg ? $latestMsg->getId() : 0;

        $latestUnread = null;
        if ($latestMsg) {
            $sender = $latestMsg->getSender();
            $latestUnread = [
                'senderId'    => $sender?->getId(),
                'senderName'  => $sender?->getFullName() ?? 'Expéditeur',
                'senderRoles' => $sender?->getRoles() ?? [],
                'content'     => $latestMsg->getContent(),
                'time'        => $latestMsg->getCreatedAt()?->format('H:i') ?? '',
                'channel'     => $latestMsg->getChannel(),
                'isDM'        => $latestMsg->getRecipient() !== null,
            ];
        }

        return $this->json([
            'unreadCount'  => $dmCount,
            'latestMsgId'  => $latestMsgId,
            'latestUnread' => $latestUnread
        ]);
    }

    /**
     * Mark all DM messages as read for current user.
     */
    #[Route('/read-all', name: 'api_chat_read_all', methods: ['POST'])]
    public function markAllRead(ChatMessageRepository $chatRepo): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $chatRepo->markAllAsReadForUser($currentUser);

        return $this->json(['success' => true]);
    }

    /**
     * Delete a chat message.
     */
    #[Route('/message/{id}', name: 'api_chat_delete_message', methods: ['DELETE'])]
    public function deleteMessage(
        int $id,
        ChatMessageRepository $chatRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $message = $chatRepo->find($id);

        if (!$message) {
            return $this->json(['error' => 'Message introuvable.'], 404);
        }

        // Allow deletion if sender is current user OR current user is Admin
        $isOwner = $message->getSender() && $message->getSender()->getId() === $currentUser->getId();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());

        if (!$isOwner && !$isAdmin) {
            return $this->json(['error' => 'Vous n\'avez pas la permission de supprimer ce message.'], 403);
        }

        $em->remove($message);
        $em->flush();

        return $this->json(['success' => true, 'deletedId' => $id]);
    }
}




