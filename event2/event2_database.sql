-- EVENT2 Complete Database Schema
-- Database: envent_2
-- This file creates all necessary tables for the EVENT2 platform

-- Create the database
CREATE DATABASE IF NOT EXISTS envent_2;
USE envent_2;

-- Users table
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_complet VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organisateur', 'participant') DEFAULT 'participant',
    email_verifie BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut_verification ENUM('en_attente', 'verifie', 'rejete') DEFAULT 'en_attente',
    date_demande_verification DATETIME NULL,
    date_verification DATETIME NULL,
    verifie_par_admin_id INT NULL,
    gmail_app_password VARCHAR(255) NULL,
    FOREIGN KEY (verifie_par_admin_id) REFERENCES utilisateurs(id)
);

-- Events table
CREATE TABLE evenements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME,
    lieu VARCHAR(255),
    places_max INT,
    prix DECIMAL(10,2) DEFAULT 0.00,
    organisateur_id INT NOT NULL,
    statut ENUM('brouillon', 'en_attente', 'publie', 'actif', 'termine', 'annule') DEFAULT 'brouillon',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    organisateur_exclusif BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (organisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Registrations table
CREATE TABLE inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    statut ENUM('en_attente', 'confirme', 'annule') DEFAULT 'en_attente',
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Certificates table
CREATE TABLE attestations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    participant_id INT NOT NULL,
    fichier_pdf VARCHAR(255) NOT NULL,
    date_generation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE),
    UNIQUE KEY unique_participant_event (evenement_id, participant_id)
);

-- Membership cards table
CREATE TABLE cartes_adhesion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    numero_carte VARCHAR(50) UNIQUE NOT NULL,
    nom_club VARCHAR(255) NOT NULL,
    date_emission DATE NOT NULL,
    date_expiration DATE NOT NULL,
    fichier_carte VARCHAR(255),
    statut ENUM('active', 'expiree', 'revoquee') DEFAULT 'active',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Verification requests table
CREATE TABLE demandes_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type_demande ENUM('organisateur', 'carte_adhesion') NOT NULL,
    statut ENUM('en_attente', 'approuvee', 'rejetee') DEFAULT 'en_attente',
    commentaire TEXT,
    fichiers_joints TEXT,
    admin_id INT,
    date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES utilisateurs(id)
);

-- Event files table
CREATE TABLE fichiers_evenements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    type_contenu VARCHAR(100),
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE
);

-- Audit logs table
CREATE TABLE logs_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    from_user_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES utilisateurs(id)
);

-- Create indexes for better performance
CREATE INDEX idx_utilisateurs_role ON utilisateurs(role);
CREATE INDEX idx_utilisateurs_statut ON utilisateurs(statut_verification);
CREATE INDEX idx_evenements_organisateur ON evenements(organisateur_id);
CREATE INDEX idx_evenements_statut ON evenements(statut);
CREATE INDEX idx_evenements_date ON evenements(date_debut);
CREATE INDEX idx_inscriptions_evenement ON inscriptions(evenement_id);
CREATE INDEX idx_inscriptions_utilisateur ON inscriptions(utilisateur_id);
CREATE INDEX idx_inscriptions_statut ON inscriptions(statut);

-- Insert sample data
INSERT INTO utilisateurs (nom_complet, email, mot_de_passe_hash, role, email_verifie, statut_verification) VALUES
('Administrateur', 'admin@event2.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'admin', TRUE, 'verifie'),
('Organisateur Test', 'organisateur@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'organisateur', TRUE, 'verifie'),
('Participant Test', 'participant@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'participant', TRUE, 'verifie');

INSERT INTO evenements (titre, description, date_debut, date_fin, lieu, places_max, prix, organisateur_id, statut) VALUES
('Conférence Tech 2025', 'Une conférence sur les dernières technologies', DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 31 DAY), 'Centre de Conférences Casablanca', 100, 50.00, 2, 'publie');

INSERT INTO inscriptions (evenement_id, utilisateur_id, statut) VALUES
(1, 3, 'confirme');

INSERT INTO logs_audit (user_id, action, details) VALUES
(1, 'system_setup', 'Installation initiale du système');

INSERT INTO notifications (user_id, type, title, message, from_user_id) VALUES
(3, 'info', 'Bienvenue sur EVENT2', 'Votre compte a été créé avec succès. Vous pouvez maintenant participer aux événements.', 1);

-- Database setup complete
-- Connection details:
-- Host: localhost
-- Database: envent_2
-- User: root
-- Password: NouveauMotDePasse123
--
-- Test accounts:
-- Admin: admin@event2.com / password
-- Organizer: organisateur@test.com / password
-- Participant: participant@test.com / password