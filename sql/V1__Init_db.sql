CREATE TABLE Candidat
(
    idCandidat INT          NOT NULL AUTO_INCREMENT,
    Nom        VARCHAR(512) NULL,
    Prenom     VARCHAR(512) NULL,
    Email      VARCHAR(512) NULL,
    Telephone  VARCHAR(512) NULL,
    CodePostal VARCHAR(512) NULL,
    Ville      VARCHAR(512) NULL,
    Handicap   BIT          NULL,

    CONSTRAINT PK_CANDIDAT PRIMARY KEY (
                                        idCandidat)
);


CREATE TABLE Candidature
(
    Message       VARCHAR(512) NULL,
    Formation     VARCHAR(512) NULL,
    idCandidat    INT          NOT NULL,
    idFormation   INT          NOT NULL,

    CONSTRAINT PK_CANDIDATURE PRIMARY KEY (idFormation, idCandidat)
);


CREATE TABLE Competences
(
    idCompetences        INT          NOT NULL AUTO_INCREMENT,
    CategorieCompetences VARCHAR(512) NULL,
    LibelleCompetences   VARCHAR(512) NULL,
    idFicheDescriptive   INT          NOT NULL,

    CONSTRAINT PK_COMPETENCES PRIMARY KEY (
                                           idCompetences)
);


CREATE TABLE Debouches
(
    idDebouches        INT          NOT NULL AUTO_INCREMENT,
    LibelleDebouches   VARCHAR(512) NULL,
    idFicheDescriptive INT          NOT NULL,

    CONSTRAINT PK_DEBOUCHES PRIMARY KEY (
                                         idDebouches)
);


CREATE TABLE Emargement
(
    idEmargement   INT      NOT NULL AUTO_INCREMENT,
    DateEmargement DATETIME NULL,
    idUtilisateur  INT      NOT NULL,

    CONSTRAINT PK_EMARGEMENT PRIMARY KEY (
                                          idEmargement)
);


CREATE TABLE Examen
(
    idExamen      INT          NOT NULL AUTO_INCREMENT,
    DateExamen    DATETIME     NOT NULL,
    Localisation  VARCHAR(512) NOT NULL,
    Salle         VARCHAR(512) NOT NULL,
    Duree         VARCHAR(512) NOT NULL,
    Nom           VARCHAR(512) NOT NULL,
    Type          VARCHAR(512) NOT NULL,
    idUtilisateur INT          NOT NULL,

    CONSTRAINT PK_EXAMEN PRIMARY KEY (
                                      idExamen)
);


CREATE TABLE FicheDescriptive
(
    idFicheDescriptive INT          NOT NULL AUTO_INCREMENT,
    Description        VARCHAR(512) NULL,

    CONSTRAINT PK_FICHEDESCRIPTIVE PRIMARY KEY (
                                                idFicheDescriptive)
);


CREATE TABLE Formation
(
    idFormation    INT                                        NOT NULL AUTO_INCREMENT,
    Intitule       VARCHAR(512)                               NULL,
    CodeRNCP       VARCHAR(512)                               NULL,
    NiveauRNCP     INT                                        NULL,
    NiveauBAC      VARCHAR(512)                               NULL,
    Prix           INT                                        NULL,
    FinancementCPF BIT                                        NULL,
    Departement    ENUM ('La Réunion', 'Mayotte')             NULL,
    Categorie      ENUM ('Informatique', 'Marketing Digital') NULL,
    Duree          VARCHAR(512)                               NULL,
    idSession      INT                                        NOT NULL,

    CONSTRAINT PK_FORMATION PRIMARY KEY (
                                         idFormation)
);


CREATE TABLE FormSession
(
    Localisation VARCHAR(512) NULL,
    idFormation  INT          NOT NULL,
    idSession    INT          NOT NULL,

    CONSTRAINT PK_FORMSESSION PRIMARY KEY (
                                           idFormation, idSession)
);


CREATE TABLE Metiers
(
    idMetiers          INT          NOT NULL AUTO_INCREMENT,
    LibelleMetiers     VARCHAR(512) NULL,
    idFicheDescriptive INT          NOT NULL,

    CONSTRAINT PK_METIERS PRIMARY KEY (
                                       idMetiers)
);


CREATE TABLE ModalitesEvaluation
(
    idModalitesEvaluation INT          NOT NULL AUTO_INCREMENT,
    Description           VARCHAR(512) NULL,
    idFicheDescriptive    INT          NOT NULL,

    CONSTRAINT PK_MODALITE_EVALUATION PRIMARY KEY (
                                                   idModalitesEvaluation)
);


CREATE TABLE MoyensTechHum
(
    idMoyensTechHum    INT          NOT NULL AUTO_INCREMENT,
    Description        VARCHAR(512) NULL,
    idFicheDescriptive INT          NOT NULL,

    CONSTRAINT PK_MOYENS PRIMARY KEY (
                                      idMoyensTechHum)
);


CREATE TABLE ObjectifsPedagogiques
(
    idObjectifsPedagogiques INT          NOT NULL AUTO_INCREMENT,
    Description             VARCHAR(512) NULL,
    idFicheDescriptive      INT          NOT NULL,

    CONSTRAINT PK_OBJECTIFS_PEDAGOGIQUES PRIMARY KEY (
                                                      idObjectifsPedagogiques)
);


CREATE TABLE Prerequis
(
    idPrerequis        INT          NOT NULL AUTO_INCREMENT,
    LibellePrerequis   VARCHAR(512) NULL,
    idFicheDescriptive INT          NOT NULL,

    CONSTRAINT PK_PREREQUIS PRIMARY KEY (
                                         idPrerequis)
);


CREATE TABLE Profil
(
    idProfil           INT          NOT NULL AUTO_INCREMENT,
    NiveauDiplome      VARCHAR(512) NULL,
    NiveauBAC          VARCHAR(512) NULL,
    Domaine            VARCHAR(512) NULL,
    idFicheDescriptive INT          NOT NULL,

    CONSTRAINT PK_PROFIL PRIMARY KEY (
                                      idProfil)
);


CREATE TABLE Session
(
    idSession INT  NOT NULL AUTO_INCREMENT,
    DateDebut DATE NULL,
    DateFin   DATE NULL,

    CONSTRAINT PK_SESSION PRIMARY KEY (
                                       idSession)
);


CREATE TABLE Utilisateur
(
    idUtilisateur INT                                     NOT NULL AUTO_INCREMENT,
    Nom           VARCHAR(512)                            NULL,
    Prenom        VARCHAR(512)                            NULL,
    Email         VARCHAR(512)                            NULL,
    Role          ENUM ('Admin', 'Formateur', 'Etudiant') NULL,
    PeutEtreJure  BIT                                     NULL,
    idFormation   INT                                     NOT NULL,

    CONSTRAINT PK_UTILISATEUR PRIMARY KEY (
                                           idUtilisateur)

);

ALTER TABLE Candidature
    ADD CONSTRAINT FK_CANDIDATURE_CANDIDAT
        FOREIGN KEY (idCandidat)
            REFERENCES Candidat (idCandidat);

ALTER TABLE Candidature
    ADD CONSTRAINT FK_CANDIDATURE_FORMATION
        FOREIGN KEY (idFormation)
            REFERENCES Formation (idFormation);

ALTER TABLE Competences
    ADD CONSTRAINT FK_COMPETENCES_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE Debouches
    ADD CONSTRAINT FK_DEBOUCHES_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE Emargement
    ADD CONSTRAINT FK_EMARGEMENT_UTILISATEUR
        FOREIGN KEY (idUtilisateur)
            REFERENCES Utilisateur (idUtilisateur);

ALTER TABLE Examen
    ADD CONSTRAINT FK_EXAMEN_UTILISATEUR
        FOREIGN KEY (idUtilisateur)
            REFERENCES Utilisateur (idUtilisateur);

ALTER TABLE Formation
    ADD CONSTRAINT FK_FORMATION_SESSION
        FOREIGN KEY (idSession)
            REFERENCES Session (idSession);

ALTER TABLE FormSession
    ADD CONSTRAINT FK_FORMSESSION_FORMATION
        FOREIGN KEY (idFormation)
            REFERENCES Formation (idFormation);

ALTER TABLE FormSession
    ADD CONSTRAINT FK_FORMSESSION_SESSION
        FOREIGN KEY (idSession)
            REFERENCES Session (idSession);

ALTER TABLE Metiers
    ADD CONSTRAINT FK_METIERS_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE ModalitesEvaluation
    ADD CONSTRAINT FK_MODALITES_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE MoyensTechHum
    ADD CONSTRAINT FK_MOYENS_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE ObjectifsPedagogiques
    ADD CONSTRAINT FK_OBJECTIFS_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE Prerequis
    ADD CONSTRAINT FK_PREREQUIS_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE Profil
    ADD CONSTRAINT FK_PROFIL_FICHEDESC
        FOREIGN KEY (idFicheDescriptive)
            REFERENCES FicheDescriptive (idFicheDescriptive);

ALTER TABLE Utilisateur
    ADD CONSTRAINT FK_UTILISATEUR_FORMATION
        FOREIGN KEY (idFormation)
            REFERENCES Formation (idFormation);
