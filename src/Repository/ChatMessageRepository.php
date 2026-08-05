<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * Find channel messages ordered chronologically.
     *
     * @return ChatMessage[]
     */
    public function findChannelMessages(string $channel, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find direct messages between two users ordered chronologically.
     *
     * @return ChatMessage[]
     */
    public function findDirectMessages(User $user1, User $user2, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('(c.sender = :u1 AND c.recipient = :u2) OR (c.sender = :u2 AND c.recipient = :u1)')
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get count of unread DM messages where current user is the recipient.
     */
    public function getUnreadCount(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.recipient = :user')
            ->andWhere('c.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Mark all direct messages from sender to recipient as read.
     */
    public function markAsRead(User $sender, User $recipient): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.isRead', 'true')
            ->where('c.sender = :sender')
            ->andWhere('c.recipient = :recipient')
            ->andWhere('c.isRead = false')
            ->setParameter('sender', $sender)
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->execute();
    }

    /**
     * Mark all unread DM messages for a recipient as read.
     */
    public function markAllAsReadForUser(User $user): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.isRead', 'true')
            ->where('c.recipient = :user')
            ->andWhere('c.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}



