<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715125141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT DEFAULT NULL, old_data JSON DEFAULT NULL, new_data JSON DEFAULT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_F6E1C0F5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE financial_offer (id INT AUTO_INCREMENT NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, version INT NOT NULL, effective_date DATE NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, INDEX IDX_58B76D11166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(50) NOT NULL, year INT NOT NULL, month INT NOT NULL, status VARCHAR(30) NOT NULL, currency VARCHAR(5) NOT NULL, total_ht NUMERIC(15, 4) NOT NULL, tva_rate NUMERIC(5, 2) NOT NULL, total_tva NUMERIC(15, 4) NOT NULL, total_ttc NUMERIC(15, 4) NOT NULL, validated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, validated_by_id INT DEFAULT NULL, project_id INT NOT NULL, financial_offer_id INT NOT NULL, UNIQUE INDEX UNIQ_90651744AEA34913 (reference), INDEX IDX_90651744C69DE5E5 (validated_by_id), INDEX IDX_90651744166D1F9C (project_id), INDEX IDX_90651744FEF33FEA (financial_offer_id), UNIQUE INDEX UNIQ_90651744166D1F9CBB8273378EB61006 (project_id, year, month), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invoice_line (id INT AUTO_INCREMENT NOT NULL, resource_name VARCHAR(255) NOT NULL, service_type VARCHAR(100) DEFAULT NULL, unit VARCHAR(50) NOT NULL, unit_price NUMERIC(12, 4) NOT NULL, consumed_quantity NUMERIC(12, 4) NOT NULL, line_total NUMERIC(15, 4) NOT NULL, invoice_id INT NOT NULL, offer_line_id INT DEFAULT NULL, INDEX IDX_D3D1D6932989F1FD (invoice_id), INDEX IDX_D3D1D693EC73B34B (offer_line_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offer_line (id INT AUTO_INCREMENT NOT NULL, resource_name VARCHAR(255) NOT NULL, service_type VARCHAR(100) DEFAULT NULL, unit VARCHAR(50) NOT NULL, unit_price NUMERIC(12, 4) NOT NULL, quantity NUMERIC(12, 4) DEFAULT NULL, description LONGTEXT DEFAULT NULL, financial_offer_id INT NOT NULL, INDEX IDX_2FEC9AE1FEF33FEA (financial_offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, so_number VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, company_id INT NOT NULL, UNIQUE INDEX UNIQ_2FB3D0EED4237E00 (so_number), INDEX IDX_2FB3D0EE979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE financial_offer ADD CONSTRAINT FK_58B76D11166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744FEF33FEA FOREIGN KEY (financial_offer_id) REFERENCES financial_offer (id)');
        $this->addSql('ALTER TABLE invoice_line ADD CONSTRAINT FK_D3D1D6932989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE invoice_line ADD CONSTRAINT FK_D3D1D693EC73B34B FOREIGN KEY (offer_line_id) REFERENCES offer_line (id)');
        $this->addSql('ALTER TABLE offer_line ADD CONSTRAINT FK_2FEC9AE1FEF33FEA FOREIGN KEY (financial_offer_id) REFERENCES financial_offer (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE financial_offer DROP FOREIGN KEY FK_58B76D11166D1F9C');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744C69DE5E5');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744166D1F9C');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744FEF33FEA');
        $this->addSql('ALTER TABLE invoice_line DROP FOREIGN KEY FK_D3D1D6932989F1FD');
        $this->addSql('ALTER TABLE invoice_line DROP FOREIGN KEY FK_D3D1D693EC73B34B');
        $this->addSql('ALTER TABLE offer_line DROP FOREIGN KEY FK_2FEC9AE1FEF33FEA');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE979B1AD6');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE financial_offer');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_line');
        $this->addSql('DROP TABLE offer_line');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
