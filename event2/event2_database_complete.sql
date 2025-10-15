CREATE DATABASE IF NOT EXISTS envent_2;
USE envent_2;

-- Set character set
ALTER DATABASE envent_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================
-- TABLE CREATIONS
-- =============================================

-- Users table (main table)
CREATE TABLE utilisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom_complet VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organisateur', 'participant') DEFAULT 'participant',
    email_verifie BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut_verification ENUM('en_attente', 'verifie', 'rejete') DEFAULT 'en_attente',
    date_demande_verification DATETIME NULL,
    date_verification DATETIME NULL,
    verifie_par_admin_id INT NULL,
    gmail_app_password VARCHAR(255) NULL
);

-- Administrators table
CREATE TABLE administrateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    privileges VARCHAR(500) DEFAULT 'all',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Organizers table
CREATE TABLE organisateurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    est_approuve BOOLEAN DEFAULT FALSE,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Participants table
CREATE TABLE participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Events table
CREATE TABLE evenements (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
    organisateur_exclusif BOOLEAN DEFAULT TRUE
);

-- Registrations table
CREATE TABLE inscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evenement_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    statut ENUM('en_attente', 'confirme', 'annule') DEFAULT 'en_attente',
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Certificates table
CREATE TABLE attestations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inscription_id INT NOT NULL,
    numero_unique VARCHAR(100) NOT NULL,
    chemin_fichier_pdf VARCHAR(255) NOT NULL,
    date_generation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut_envoi ENUM('en_attente', 'envoye', 'echec') DEFAULT 'en_attente'
);

-- Membership cards table
CREATE TABLE cartes_adhesion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    numero_carte VARCHAR(50) NOT NULL,
    nom_club VARCHAR(255) NOT NULL,
    date_emission DATE NOT NULL,
    date_expiration DATE NOT NULL,
    fichier_carte VARCHAR(255),
    statut ENUM('active', 'expiree', 'revoquee') DEFAULT 'active',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Verification requests table
CREATE TABLE demandes_verification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utilisateur_id INT NOT NULL,
    type_demande ENUM('organisateur', 'carte_adhesion') NOT NULL,
    statut ENUM('en_attente', 'approuvee', 'rejetee') DEFAULT 'en_attente',
    commentaire TEXT,
    informations_complementaires TEXT,
    fichiers_joints TEXT,
    admin_id INT,
    date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME
);

-- Verification documents table
CREATE TABLE documents_verification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    demande_id INT NOT NULL,
    type_document ENUM('piece_identite', 'carte_club', 'justificatif_domicile') NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Event files table
CREATE TABLE fichiers_evenements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evenement_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    type_contenu VARCHAR(100),
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Audit logs table
CREATE TABLE logs_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    from_user_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Password resets table
CREATE TABLE password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
);

-- =============================================
-- FOREIGN KEY CONSTRAINTS
-- =============================================

-- Self-referencing FK for admin verification
ALTER TABLE utilisateurs ADD CONSTRAINT fk_admin_verif FOREIGN KEY (verifie_par_admin_id) REFERENCES utilisateurs(id);

-- Role-specific tables FKs
ALTER TABLE administrateurs ADD CONSTRAINT fk_admin_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;
ALTER TABLE organisateurs ADD CONSTRAINT fk_org_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;
ALTER TABLE participants ADD CONSTRAINT fk_part_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;

-- Events and registrations FKs
ALTER TABLE evenements ADD CONSTRAINT fk_event_org FOREIGN KEY (organisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;

ALTER TABLE inscriptions ADD CONSTRAINT fk_insc_event FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE;
ALTER TABLE inscriptions ADD CONSTRAINT fk_insc_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;

-- Certificates FK
ALTER TABLE attestations ADD CONSTRAINT fk_att_insc FOREIGN KEY (inscription_id) REFERENCES inscriptions(id) ON DELETE CASCADE;

-- Membership cards FK
ALTER TABLE cartes_adhesion ADD CONSTRAINT fk_card_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;

-- Verification system FKs
ALTER TABLE demandes_verification ADD CONSTRAINT fk_dem_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;
ALTER TABLE demandes_verification ADD CONSTRAINT fk_dem_admin FOREIGN KEY (admin_id) REFERENCES utilisateurs(id);

ALTER TABLE documents_verification ADD CONSTRAINT fk_doc_dem FOREIGN KEY (demande_id) REFERENCES demandes_verification(id) ON DELETE CASCADE;

-- Event files FK
ALTER TABLE fichiers_evenements ADD CONSTRAINT fk_file_event FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE;

-- Audit logs FK
ALTER TABLE logs_audit ADD CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;

-- Notifications FKs
ALTER TABLE notifications ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE;
ALTER TABLE notifications ADD CONSTRAINT fk_notif_from FOREIGN KEY (from_user_id) REFERENCES utilisateurs(id);

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================

-- Users table indexes
CREATE INDEX idx_utilisateurs_role ON utilisateurs(role);
CREATE INDEX idx_utilisateurs_statut ON utilisateurs(statut_verification);
CREATE INDEX idx_utilisateurs_email ON utilisateurs(email);

-- Events table indexes
CREATE INDEX idx_evenements_organisateur ON evenements(organisateur_id);
CREATE INDEX idx_evenements_statut ON evenements(statut);
CREATE INDEX idx_evenements_date ON evenements(date_debut);

-- Registrations table indexes
CREATE INDEX idx_inscriptions_evenement ON inscriptions(evenement_id);
CREATE INDEX idx_inscriptions_utilisateur ON inscriptions(utilisateur_id);
CREATE INDEX idx_inscriptions_statut ON inscriptions(statut);

-- Certificates table indexes
CREATE INDEX idx_attestations_inscription ON attestations(inscription_id);
CREATE INDEX idx_attestations_numero ON attestations(numero_unique);

-- Audit logs indexes
CREATE INDEX idx_logs_date ON logs_audit(date_creation);

-- Notifications indexes
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_unread ON notifications(is_read, created_at);
