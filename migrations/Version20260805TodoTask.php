<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create todo_task table with assignedTo / createdBy relations.
 */
final class Version20260805TodoTask extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create todo_task table for Kanban board with consultant assignment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE todo_task (
            id           INT AUTO_INCREMENT NOT NULL,
            title        VARCHAR(255) NOT NULL,
            description  LONGTEXT DEFAULT NULL,
            status       VARCHAR(30) NOT NULL DEFAULT \'todo\',
            priority     VARCHAR(20) NOT NULL DEFAULT \'medium\',
            position     INT NOT NULL DEFAULT 0,
            due_date     DATE DEFAULT NULL,
            created_at   DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at   DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_by_id INT DEFAULT NULL,
            assigned_to_id INT DEFAULT NULL,
            INDEX IDX_TODO_CREATED_BY (created_by_id),
            INDEX IDX_TODO_ASSIGNED_TO (assigned_to_id),
            INDEX IDX_TODO_STATUS (status),
            INDEX IDX_TODO_ASSIGNED_STATUS (assigned_to_id, status),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('ALTER TABLE todo_task
            ADD CONSTRAINT FK_TODO_CREATED_BY  FOREIGN KEY (created_by_id)  REFERENCES `user` (id) ON DELETE SET NULL,
            ADD CONSTRAINT FK_TODO_ASSIGNED_TO FOREIGN KEY (assigned_to_id) REFERENCES `user` (id) ON DELETE SET NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE todo_task DROP FOREIGN KEY FK_TODO_CREATED_BY');
        $this->addSql('ALTER TABLE todo_task DROP FOREIGN KEY FK_TODO_ASSIGNED_TO');
        $this->addSql('DROP TABLE todo_task');
    }
}
