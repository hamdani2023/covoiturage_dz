-- Delete existing database (Caution: irreversible)
DROP DATABASE IF EXISTS covoiturage_dz;

-- Create database with UTF-8 support
CREATE DATABASE IF NOT EXISTS covoiturage_dz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE covoiturage_dz;

-- Table: wilayas
CREATE TABLE wilayas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code INT UNIQUE NOT NULL,
    nom VARCHAR(50) NOT NULL,
    nom_ar VARCHAR(50) NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8)
);

-- Table: utilisateurs
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    wilaya_id INT,
    date_naissance DATE,
    genre ENUM('homme', 'femme'),
    photo VARCHAR(255),
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'suspendu', 'banni') DEFAULT 'actif',
    role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    account_locked BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (wilaya_id) REFERENCES wilayas(id)
);

-- Table: vehicules
CREATE TABLE vehicules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    marque VARCHAR(50) NOT NULL,
    modele VARCHAR(50) NOT NULL,
    annee INT,
    couleur VARCHAR(30),
    plaque_immatriculation VARCHAR(20) NOT NULL,
    places_disponibles INT NOT NULL,
    climatise BOOLEAN DEFAULT FALSE,
    photo VARCHAR(255),
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

-- Table: trajets
CREATE TABLE trajets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conducteur_id INT NOT NULL,
    vehicule_id INT NOT NULL,
    wilaya_depart_id INT NOT NULL,
    wilaya_arrivee_id INT NOT NULL,
    lieu_depart VARCHAR(100) NOT NULL,
    lieu_arrivee VARCHAR(100) NOT NULL,
    date_depart DATETIME NOT NULL,
    places_disponibles INT NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    description TEXT,
    bagages_autorises BOOLEAN DEFAULT TRUE,
    animaux_autorises BOOLEAN DEFAULT FALSE,
    fumeur_autorise BOOLEAN DEFAULT FALSE,
    statut ENUM('planifie', 'en_cours', 'termine', 'annule') DEFAULT 'planifie',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conducteur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (vehicule_id) REFERENCES vehicules(id),
    FOREIGN KEY (wilaya_depart_id) REFERENCES wilayas(id),
    FOREIGN KEY (wilaya_arrivee_id) REFERENCES wilayas(id)
);

-- Table: reservations
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trajet_id INT NOT NULL,
    passager_id INT NOT NULL,
    places_reservees INT NOT NULL,
    date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'confirme', 'refuse', 'annule') DEFAULT 'en_attente',
    FOREIGN KEY (trajet_id) REFERENCES trajets(id),
    FOREIGN KEY (passager_id) REFERENCES utilisateurs(id)
);

-- Table: paiements
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    methode ENUM('carte', 'paypal', 'ccp', 'edahabia') NOT NULL,
    transaction_id VARCHAR(100),
    statut ENUM('en_attente', 'paye', 'echec', 'rembourse') DEFAULT 'en_attente',
    date_paiement DATETIME,
    banque VARCHAR(255),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)
);

-- Table: notations
CREATE TABLE notations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluateur_id INT NOT NULL,
    evalue_id INT NOT NULL,
    trajet_id INT NOT NULL,
    note INT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire TEXT,
    date_notation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (evalue_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (trajet_id) REFERENCES trajets(id)
);

-- Table: messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expediteur_id INT NOT NULL,
    destinataire_id INT NOT NULL,
    trajet_id INT,
    sujet VARCHAR(100),
    contenu TEXT NOT NULL,
    lu BOOLEAN DEFAULT FALSE,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (trajet_id) REFERENCES trajets(id)
);

-- Table: favoris
CREATE TABLE favoris (
    utilisateur_id INT NOT NULL,
    trajet_id INT NOT NULL,
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (utilisateur_id, trajet_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (trajet_id) REFERENCES trajets(id)
);

-- Table: phone_number_views
CREATE TABLE phone_number_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viewer_id INT NOT NULL,
    target_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    view_date DATETIME NOT NULL,
    FOREIGN KEY (viewer_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (target_id) REFERENCES utilisateurs(id)
);

-- Table: admin activity log
CREATE TABLE admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_table VARCHAR(50),
    target_id INT,
    action_details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES utilisateurs(id)
);

-- Table: system settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertion: wilayas
INSERT INTO wilayas (code, nom, nom_ar) VALUES
(1, 'Adrar', 'أدرار'), (2, 'Chlef', 'الشلف'), (3, 'Laghouat', 'الأغواط'),
(4, 'Oum El Bouaghi', 'أم البواقي'), (5, 'Batna', 'باتنة'), (6, 'Béjaïa', 'بجاية'),
(7, 'Biskra', 'بسكرة'), (8, 'Béchar', 'بشار'), (9, 'Blida', 'البليدة'),
(10, 'Bouira', 'البويرة'), (11, 'Tamanrasset', 'تمنراست'), (12, 'Tébessa', 'تبسة'),
(13, 'Tlemcen', 'تلمسان'), (14, 'Tiaret', 'تيارت'), (15, 'Tizi Ouzou', 'تيزي وزو'),
(16, 'Alger', 'الجزائر'), (17, 'Djelfa', 'الجلفة'), (18, 'Jijel', 'جيجل'),
(19, 'Sétif', 'سطيف'), (20, 'Saïda', 'سعيدة'), (21, 'Skikda', 'سكيكدة'),
(22, 'Sidi Bel Abbès', 'سيدي بلعباس'), (23, 'Annaba', 'عنابة'), (24, 'Guelma', 'قالمة'),
(25, 'Constantine', 'قسنطينة'), (26, 'Médéa', 'المدية'), (27, 'Mostaganem', 'مستغانم'),
(28, 'M''Sila', 'المسيلة'), (29, 'Mascara', 'معسكر'), (30, 'Ouargla', 'ورقلة'),
(31, 'Oran', 'وهران'), (32, 'El Bayadh', 'البيض'), (33, 'Illizi', 'إليزي'),
(34, 'Bordj Bou Arreridj', 'برج بوعريريج'), (35, 'Boumerdès', 'بومرداس'),
(36, 'El Tarf', 'الطارف'), (37, 'Tindouf', 'تندوف'), (38, 'Tissemsilt', 'تسمسيلت'),
(39, 'El Oued', 'الوادي'), (40, 'Khenchela', 'خنشلة'), (41, 'Souk Ahras', 'سوق أهراس'),
(42, 'Tipaza', 'تيبازة'), (43, 'Mila', 'ميلة'), (44, 'Aïn Defla', 'عين الدفلى'),
(45, 'Naâma', 'النعامة'), (46, 'Aïn Témouchent', 'عين تموشنت'),
(47, 'Ghardaïa', 'غرداية'), (48, 'Relizane', 'غليزان');

INSERT INTO utilisateurs (
    nom, 
    prenom, 
    email, 
    mot_de_passe, 
    telephone, 
    date_inscription, 
    role
) VALUES (
    'admin', 
    'admin', 
    'admin@admin.com', 
    '$2b$10$KuGItRVjg3HNSnP2Uy7GFeTgYBheDbazrME1Q8TbjsJTxrNstUSBW',  -- bcrypt("admin")
    '0000000000', 
    NOW(), 
    'super_admin'
);


-- bcrypt hash for "covoiturage" (cost 10)
SET @pwd_hash = '$2b$10$cf26f63Kf0OF.e34hog2b.lOzj/k2IfpOS/6uTxF62p2Ugalo2ESy';

INSERT INTO utilisateurs (
    nom, prenom, email, mot_de_passe, telephone, wilaya_id, date_inscription, role
) VALUES
-- 1
('Ali', 'Ahmed', CONCAT('Ahmed','.Ali','@example.com'), @pwd_hash, '0550000001', NULL, NOW(), 'user'),
-- 2
('Saidi', 'Mohamed', CONCAT('Mohamed','.Saidi','@example.com'), @pwd_hash, '0550000002', NULL, NOW(), 'user'),
-- 3
('Zahra', 'Fatima', CONCAT('Fatima','.Zahra','@example.com'), @pwd_hash, '0550000003', NULL, NOW(), 'user'),
-- 4
('Boudiaf', 'Amina', CONCAT('Amina','.Boudiaf','@example.com'), @pwd_hash, '0550000004', NULL, NOW(), 'user'),
-- 5
('Benyahia', 'Youssef', CONCAT('Youssef','.Benyahia','@example.com'), @pwd_hash, '0550000005', NULL, NOW(), 'user'),
-- 6
('El Amrani', 'Sara', CONCAT('Sara','.ElAmrani','@example.com'), @pwd_hash, '0550000006', NULL, NOW(), 'user'),
-- 7
('Mansouri', 'Khaled', CONCAT('Khaled','.Mansouri','@example.com'), @pwd_hash, '0550000007', NULL, NOW(), 'user'),
-- 8
('Touati', 'Salima', CONCAT('Salima','.Touati','@example.com'), @pwd_hash, '0550000008', NULL, NOW(), 'user'),
-- 9
('Khelifi', 'Rachid', CONCAT('Rachid','.Khelifi','@example.com'), @pwd_hash, '0550000009', NULL, NOW(), 'user'),
-- 10
('Bouazghi', 'Leila', CONCAT('Leila','.Bouazghi','@example.com'), @pwd_hash, '0550000010', NULL, NOW(), 'user');