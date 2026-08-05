<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260805142806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE todo_task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(30) NOT NULL, priority VARCHAR(20) NOT NULL, position INT NOT NULL, due_date DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, assigned_to_id INT DEFAULT NULL, INDEX IDX_DAFBD3AB03A8386 (created_by_id), INDEX IDX_DAFBD3AF4BD7827 (assigned_to_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE todo_task ADD CONSTRAINT FK_DAFBD3AB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE todo_task ADD CONSTRAINT FK_DAFBD3AF4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE audit_log CHANGE old_data old_data JSON DEFAULT NULL, CHANGE new_data new_data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE company CHANGE address address VARCHAR(255) DEFAULT NULL, CHANGE phone phone VARCHAR(50) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice CHANGE validated_at validated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_line CHANGE service_type service_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE offer_line CHANGE service_type service_type VARCHAR(100) DEFAULT NULL, CHANGE sku_id sku_id VARCHAR(100) DEFAULT NULL, CHANGE quantity quantity NUMERIC(12, 4) DEFAULT NULL, CHANGE discount discount NUMERIC(10, 4) DEFAULT NULL, CHANGE discount_type discount_type VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE face_descriptor face_descriptor JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE todo_task DROP FOREIGN KEY FK_DAFBD3AB03A8386');
        $this->addSql('ALTER TABLE todo_task DROP FOREIGN KEY FK_DAFBD3AF4BD7827');
        $this->addSql('DROP TABLE todo_task');
        $this->addSql('ALTER TABLE audit_log CHANGE old_data old_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE new_data new_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE company CHANGE address address VARCHAR(255) DEFAULT \'NULL\', CHANGE phone phone VARCHAR(50) DEFAULT \'NULL\', CHANGE email email VARCHAR(180) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE invoice CHANGE validated_at validated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE invoice_line CHANGE service_type service_type VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE offer_line CHANGE service_type service_type VARCHAR(100) DEFAULT \'NULL\', CHANGE sku_id sku_id VARCHAR(100) DEFAULT \'NULL\', CHANGE quantity quantity NUMERIC(12, 4) DEFAULT \'NULL\', CHANGE discount discount NUMERIC(10, 4) DEFAULT \'NULL\', CHANGE discount_type discount_type VARCHAR(10) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE face_descriptor face_descriptor LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
    }
}
