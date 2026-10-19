<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014162722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table de messagerie Symfony et ajuste les colonnes des films.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (
            id BIGINT AUTO_INCREMENT NOT NULL,
            body LONGTEXT NOT NULL,
            headers LONGTEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL,
            INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750
            (queue_name, available_at, delivered_at, id),
            PRIMARY KEY (id)
        )');

        $this->addSql('ALTER TABLE films
            CHANGE description description LONGTEXT NOT NULL,
            CHANGE imageFilm imageFilm VARCHAR(255) DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');

        $this->addSql('ALTER TABLE films
            CHANGE description description TEXT NOT NULL,
            CHANGE imageFilm imageFilm VARCHAR(255) DEFAULT NULL
        ');
    }
}
