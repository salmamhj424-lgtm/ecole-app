-- ============================================================
-- Gestion d'école primaire (mono-école) - Etape 1 : base de données
-- Chaque table métier a :
--   annee_scolaire : historique d'une année à l'autre (ex: '2026-2027')
--   deleted_at     : suppression logique (NULL = actif)
-- ============================================================

-- Utilisateurs (admin, enseignant, parent, élève, comptable)
CREATE TABLE users (
    id                  SERIAL PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL,
    prenom              VARCHAR(100),
    email               VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe_hash   VARCHAR(255) NOT NULL,
    role                VARCHAR(20)  NOT NULL
                        CHECK (role IN ('admin', 'enseignant', 'parent', 'eleve', 'comptable')),
    created_at          TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMP
);

-- Classes
CREATE TABLE classes (
    id              SERIAL PRIMARY KEY,
    niveau          VARCHAR(20) NOT NULL,          -- CP, CE1, CE2, CM1, CM2
    section         VARCHAR(10) NOT NULL,          -- A, B...
    capacite        INTEGER NOT NULL CHECK (capacite > 0),
    enseignant_id   INTEGER REFERENCES users(id),  -- enseignant titulaire
    annee_scolaire  VARCHAR(9) NOT NULL,
    deleted_at      TIMESTAMP
);

-- Matières
CREATE TABLE matieres (
    id          SERIAL PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    coefficient NUMERIC(3,1) NOT NULL DEFAULT 1 CHECK (coefficient > 0),
    niveau      VARCHAR(20),
    deleted_at  TIMESTAMP
);

-- Élèves (dossier)
CREATE TABLE eleves (
    id              SERIAL PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    date_naissance  DATE,
    classe_id       INTEGER REFERENCES classes(id),
    parent_id       INTEGER REFERENCES users(id),
    compte_id       INTEGER REFERENCES users(id),  -- compte de connexion de l'élève (optionnel)
    annee_scolaire  VARCHAR(9) NOT NULL,
    deleted_at      TIMESTAMP
);

-- Évaluations (contrôle continu, examens)
CREATE TABLE evaluations (
    id              SERIAL PRIMARY KEY,
    matiere_id      INTEGER NOT NULL REFERENCES matieres(id),
    classe_id       INTEGER NOT NULL REFERENCES classes(id),
    type            VARCHAR(30) NOT NULL DEFAULT 'controle',  -- controle, examen
    date_eval       DATE NOT NULL,
    bareme          NUMERIC(4,1) NOT NULL DEFAULT 20,
    annee_scolaire  VARCHAR(9) NOT NULL,
    deleted_at      TIMESTAMP
);

-- Notes
CREATE TABLE notes (
    id              SERIAL PRIMARY KEY,
    eleve_id        INTEGER NOT NULL REFERENCES eleves(id),
    evaluation_id   INTEGER NOT NULL REFERENCES evaluations(id),
    valeur          NUMERIC(4,2) NOT NULL CHECK (valeur >= 0),
    annee_scolaire  VARCHAR(9) NOT NULL,
    deleted_at      TIMESTAMP,
    UNIQUE (eleve_id, evaluation_id)
);

-- Paiements
CREATE TABLE paiements (
    id              SERIAL PRIMARY KEY,
    eleve_id        INTEGER NOT NULL REFERENCES eleves(id),
    montant         NUMERIC(10,2) NOT NULL CHECK (montant > 0),
    mode            VARCHAR(20) NOT NULL
                    CHECK (mode IN ('especes', 'virement', 'mobile_money')),
    date_paiement   DATE NOT NULL DEFAULT CURRENT_DATE,
    annee_scolaire  VARCHAR(9) NOT NULL,
    deleted_at      TIMESTAMP
);

-- Messages (immuables une fois envoyés ; brouillons modifiables)
CREATE TABLE messages (
    id               SERIAL PRIMARY KEY,
    expediteur_id    INTEGER NOT NULL REFERENCES users(id),
    destinataire_id  INTEGER NOT NULL REFERENCES users(id),
    objet            VARCHAR(200) NOT NULL,
    corps            TEXT NOT NULL,
    statut           VARCHAR(10) NOT NULL DEFAULT 'envoye'
                     CHECK (statut IN ('brouillon', 'envoye')),
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    masque_destinataire BOOLEAN NOT NULL DEFAULT FALSE,  -- le destinataire peut masquer, pas supprimer
    lu               BOOLEAN NOT NULL DEFAULT FALSE
);

-- Index utiles
CREATE INDEX idx_eleves_classe   ON eleves(classe_id);
CREATE INDEX idx_eleves_parent   ON eleves(parent_id);
CREATE INDEX idx_notes_eleve     ON notes(eleve_id);
CREATE INDEX idx_paiements_eleve ON paiements(eleve_id);
CREATE INDEX idx_messages_dest   ON messages(destinataire_id);
