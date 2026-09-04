USE [extra_bloc]
GO

/*
 * ============================================================================
 * Cache local des vues ERP (schéma "cache")
 * ============================================================================
 *
 * Pourquoi : app.vw_erp_actes_bloc_direction et app.vw_erp_acte_intervenants
 * interrogent ERP_LINK (serveur lié) en direct, avec UNION ALL + plusieurs
 * jointures. Même restreintes à une poignée de NumIntv (cf. correctif dans
 * DirectionController.php), chaque évaluation de ces vues repart taper sur
 * le serveur distant -> 30 à 40 secondes observées en prod avec seulement
 * 306 lignes dans app.extra_declarations.
 *
 * Solution : on matérialise localement, dans des tables indexées, le
 * RÉSULTAT de ces deux vues pour une fenêtre glissante de dates (passé +
 * futur proche). Le rafraîchissement se fait :
 *   - en tâche de fond toutes les ~10 min (cache.usp_refresh_erp, appelée
 *     par `php artisan erp:cache-sync`, voir ErpCacheSync.php)
 *   - ET ponctuellement, pour une seule journée, juste après la création
 *     d'une déclaration (cache.usp_refresh_erp_range avec une plage d'un
 *     jour, appelée par SyncActeJob.php) — pour ne pas attendre le prochain
 *     passage planifié.
 *
 * Portée volontairement LIMITÉE à DirectionController.php pour l'instant :
 * app.vw_erp_actes_bloc_direction / app.vw_erp_acte_intervenants restent
 * inchangées et continuent de servir TechnicianController/MajorController/
 * RegistryController. Une fois ce cache validé en prod, on pourra
 * envisager de rediriger ces vues elles-mêmes vers le cache pour que tout
 * le monde en profite (à auditer séparément : ces contrôleurs utilisent
 * peut-être des colonnes non reprises ici).
 * ============================================================================
 */

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'cache')
    EXEC('CREATE SCHEMA cache');
GO

/* ----------------------------------------------------------------------
 * cache.erp_actes  ==  copie locale de app.vw_erp_actes_bloc_direction
 * ---------------------------------------------------------------------- */
IF OBJECT_ID('cache.erp_actes', 'U') IS NULL
BEGIN
    CREATE TABLE cache.erp_actes (
        NumIntv             int             NOT NULL,
        NumDoss             varchar(50)     NULL,
        Nature              char(1)         NULL,
        TypeActe            nvarchar(20)    NULL,
        CodeActe            nvarchar(50)    NULL,
        LibelleActe         nvarchar(255)   NULL,
        DatOpe              datetime        NULL,
        Bloc                varchar(20)     NULL,
        Salle               varchar(20)     NULL,
        HDAnest             datetime        NULL,
        HFAnest             datetime        NULL,
        CodMed              varchar(20)     NULL,
        MedRea              varchar(20)     NULL,
        Code_Examen         varchar(50)     NULL,
        DesignationSalle    nvarchar(100)   NULL,
        CodBloc             varchar(20)     NULL,
        LibBloc             nvarchar(100)   NULL,
        Chirurgien          nvarchar(150)   NULL,
        Reanimateur         nvarchar(150)   NULL,
        Debut_Anesthesie    datetime        NULL,
        Fin_Anesthesie      datetime        NULL,
        NomPatient          nvarchar(100)   NULL,
        PrenomPatient       nvarchar(100)   NULL,
        IdentifiantPatient  varchar(50)     NULL,
        CinPatient          varchar(20)     NULL,
        DatOpeJour          AS (CAST(DatOpe AS date)) PERSISTED,
        synced_at           datetime2       NOT NULL CONSTRAINT DF_cache_erp_actes_synced_at DEFAULT SYSDATETIME(),
        CONSTRAINT PK_cache_erp_actes PRIMARY KEY CLUSTERED (NumIntv)
    );

    CREATE INDEX IX_cache_erp_actes_DatOpeJour ON cache.erp_actes (DatOpeJour) INCLUDE (NumDoss, CodBloc, CodeActe);
    CREATE INDEX IX_cache_erp_actes_NumDoss    ON cache.erp_actes (NumDoss);
    CREATE INDEX IX_cache_erp_actes_CodBloc    ON cache.erp_actes (CodBloc);
END
GO

/* ----------------------------------------------------------------------
 * cache.erp_acte_intervenants  ==  copie locale de app.vw_erp_acte_intervenants
 * ---------------------------------------------------------------------- */
IF OBJECT_ID('cache.erp_acte_intervenants', 'U') IS NULL
BEGIN
    CREATE TABLE cache.erp_acte_intervenants (
        NumIntv              int            NOT NULL,
        RoleIntervenant      nvarchar(30)   NOT NULL,
        CodInterv            varchar(20)    NOT NULL,
        DesInterv            nvarchar(150)  NULL,
        TypInterv            varchar(20)    NULL,
        DesTypInterv         nvarchar(100)  NULL,
        LoginErp             varchar(50)    NULL,
        MatriculePointeuse   varchar(20)    NULL,
        HeureEmploiDebut1    time           NULL,
        HeureEmploiFin1      time           NULL,
        HeureEmploiDebut2    time           NULL,
        HeureEmploiFin2      time           NULL,
        Repos                bit            NULL,
        HeurePointageEntree  datetime2      NULL,
        HeurePointageSortie  datetime2      NULL,
        synced_at            datetime2      NOT NULL CONSTRAINT DF_cache_erp_acte_interv_synced_at DEFAULT SYSDATETIME(),
        CONSTRAINT PK_cache_erp_acte_intervenants PRIMARY KEY CLUSTERED (NumIntv, RoleIntervenant, CodInterv)
    );

    CREATE INDEX IX_cache_erp_acte_intervenants_NumIntv_CodInterv
        ON cache.erp_acte_intervenants (NumIntv, CodInterv);
END
GO

/* ----------------------------------------------------------------------
 * cache.sync_log  ==  petit journal des synchronisations, pour diagnostiquer
 * si le job tourne bien et combien de temps il prend.
 * ---------------------------------------------------------------------- */
IF OBJECT_ID('cache.sync_log', 'U') IS NULL
BEGIN
    CREATE TABLE cache.sync_log (
        id              int IDENTITY(1,1) PRIMARY KEY,
        started_at      datetime2      NOT NULL,
        finished_at     datetime2      NULL,
        date_debut      date           NOT NULL,
        date_fin        date           NOT NULL,
        actes_count     int            NULL,
        intervenants_count int        NULL,
        num_intv_cible  int            NULL,       -- rempli seulement pour une resync ciblée (SyncActeJob)
        erreur          nvarchar(2000) NULL
    );
END
GO

/* ============================================================================
 * cache.usp_refresh_erp_range : cœur de la synchronisation, sur une plage de
 * dates explicite. Utilisée à la fois par le job planifié (fenêtre glissante)
 * et par la resynchronisation ciblée d'un seul jour (SyncActeJob).
 *
 * @NumIntvCible : optionnel — si renseigné, ne resynchronise QUE cet acte
 * (utile pour un rafraîchissement immédiat après la saisie d'une déclaration,
 * sans reprendre toute la journée).
 * ============================================================================ */
CREATE OR ALTER PROCEDURE cache.usp_refresh_erp_range
    @DateDebut date,
    @DateFin date,
    @NumIntvCible int = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @StartedAt datetime2 = SYSDATETIME();
    DECLARE @ActesCount int = 0;
    DECLARE @IntervCount int = 0;

    BEGIN TRY
        -- 1) Actes de la période (ou l'acte ciblé) depuis la vue live ERP.
        SELECT *
        INTO #actes
        FROM app.vw_erp_actes_bloc_direction AS a
        WHERE (@NumIntvCible IS NOT NULL AND a.NumIntv = @NumIntvCible)
           OR (@NumIntvCible IS NULL AND CAST(a.DatOpe AS date) BETWEEN @DateDebut AND @DateFin);

        SET @ActesCount = @@ROWCOUNT;

        -- 2) Intervenants des actes récupérés à l'étape 1 (jamais plus
        --    large : on ne va chercher que ce dont on a besoin).
        SELECT i.*
        INTO #intervenants
        FROM app.vw_erp_acte_intervenants AS i
        WHERE i.NumIntv IN (SELECT NumIntv FROM #actes);

        SET @IntervCount = @@ROWCOUNT;

        BEGIN TRAN;

        -- 3) MERGE des actes.
        MERGE cache.erp_actes AS target
        USING #actes AS source
        ON target.NumIntv = source.NumIntv
        WHEN MATCHED THEN UPDATE SET
            NumDoss = source.NumDoss, Nature = source.Nature, TypeActe = source.TypeActe,
            CodeActe = source.CodeActe, LibelleActe = source.LibelleActe, DatOpe = source.DatOpe,
            Bloc = source.Bloc, Salle = source.Salle, HDAnest = source.HDAnest, HFAnest = source.HFAnest,
            CodMed = source.CodMed, MedRea = source.MedRea, Code_Examen = source.Code_Examen,
            DesignationSalle = source.DesignationSalle, CodBloc = source.CodBloc, LibBloc = source.LibBloc,
            Chirurgien = source.Chirurgien, Reanimateur = source.Reanimateur,
            Debut_Anesthesie = source.Debut_Anesthesie, Fin_Anesthesie = source.Fin_Anesthesie,
            NomPatient = source.NomPatient, PrenomPatient = source.PrenomPatient,
            IdentifiantPatient = source.IdentifiantPatient, CinPatient = source.CinPatient,
            synced_at = SYSDATETIME()
        WHEN NOT MATCHED BY TARGET THEN INSERT (
            NumIntv, NumDoss, Nature, TypeActe, CodeActe, LibelleActe, DatOpe, Bloc, Salle,
            HDAnest, HFAnest, CodMed, MedRea, Code_Examen, DesignationSalle, CodBloc, LibBloc,
            Chirurgien, Reanimateur, Debut_Anesthesie, Fin_Anesthesie, NomPatient, PrenomPatient,
            IdentifiantPatient, CinPatient
        ) VALUES (
            source.NumIntv, source.NumDoss, source.Nature, source.TypeActe, source.CodeActe,
            source.LibelleActe, source.DatOpe, source.Bloc, source.Salle, source.HDAnest, source.HFAnest,
            source.CodMed, source.MedRea, source.Code_Examen, source.DesignationSalle, source.CodBloc,
            source.LibBloc, source.Chirurgien, source.Reanimateur, source.Debut_Anesthesie,
            source.Fin_Anesthesie, source.NomPatient, source.PrenomPatient, source.IdentifiantPatient,
            source.CinPatient
        )
        -- Un acte qui existait dans le cache pour cette fenêtre mais qui a
        -- disparu côté ERP (annulé) : on le retire aussi du cache.
        WHEN NOT MATCHED BY SOURCE
             AND (
                    (@NumIntvCible IS NOT NULL AND target.NumIntv = @NumIntvCible)
                 OR (@NumIntvCible IS NULL AND target.DatOpeJour BETWEEN @DateDebut AND @DateFin)
             )
        THEN DELETE;

        -- 4) MERGE des intervenants (même logique).
        MERGE cache.erp_acte_intervenants AS target
        USING #intervenants AS source
        ON  target.NumIntv = source.NumIntv
        AND target.RoleIntervenant = source.RoleIntervenant
        AND target.CodInterv = source.CodInterv
        WHEN MATCHED THEN UPDATE SET
            DesInterv = source.DesInterv, TypInterv = source.TypInterv, DesTypInterv = source.DesTypInterv,
            LoginErp = source.LoginErp, MatriculePointeuse = source.MatriculePointeuse,
            HeureEmploiDebut1 = source.HeureEmploiDebut1, HeureEmploiFin1 = source.HeureEmploiFin1,
            HeureEmploiDebut2 = source.HeureEmploiDebut2, HeureEmploiFin2 = source.HeureEmploiFin2,
            Repos = source.Repos, HeurePointageEntree = source.HeurePointageEntree,
            HeurePointageSortie = source.HeurePointageSortie, synced_at = SYSDATETIME()
        WHEN NOT MATCHED BY TARGET THEN INSERT (
            NumIntv, RoleIntervenant, CodInterv, DesInterv, TypInterv, DesTypInterv, LoginErp,
            MatriculePointeuse, HeureEmploiDebut1, HeureEmploiFin1, HeureEmploiDebut2, HeureEmploiFin2,
            Repos, HeurePointageEntree, HeurePointageSortie
        ) VALUES (
            source.NumIntv, source.RoleIntervenant, source.CodInterv, source.DesInterv, source.TypInterv,
            source.DesTypInterv, source.LoginErp, source.MatriculePointeuse, source.HeureEmploiDebut1,
            source.HeureEmploiFin1, source.HeureEmploiDebut2, source.HeureEmploiFin2, source.Repos,
            source.HeurePointageEntree, source.HeurePointageSortie
        )
        WHEN NOT MATCHED BY SOURCE
             AND target.NumIntv IN (SELECT NumIntv FROM #actes)
        THEN DELETE;

        COMMIT TRAN;

        INSERT INTO cache.sync_log (started_at, finished_at, date_debut, date_fin, actes_count, intervenants_count, num_intv_cible)
        VALUES (@StartedAt, SYSDATETIME(), @DateDebut, @DateFin, @ActesCount, @IntervCount, @NumIntvCible);
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRAN;

        INSERT INTO cache.sync_log (started_at, finished_at, date_debut, date_fin, num_intv_cible, erreur)
        VALUES (@StartedAt, SYSDATETIME(), @DateDebut, @DateFin, @NumIntvCible, ERROR_MESSAGE());

        THROW;
    END CATCH
END
GO

/* ============================================================================
 * cache.usp_refresh_erp : synchronisation planifiée, fenêtre glissante
 * (par défaut 90 jours en arrière, 7 jours en avant — couvre largement les
 * périodes consultées sur l'écran Direction). Appelée par
 * `php artisan erp:cache-sync` toutes les ~10 minutes.
 * ============================================================================ */
CREATE OR ALTER PROCEDURE cache.usp_refresh_erp
    @JoursArriere int = 90,
    @JoursAvant   int = 7
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @DateDebut date = CAST(DATEADD(day, -@JoursArriere, GETDATE()) AS date);
    DECLARE @DateFin   date = CAST(DATEADD(day,  @JoursAvant,   GETDATE()) AS date);

    EXEC cache.usp_refresh_erp_range @DateDebut = @DateDebut, @DateFin = @DateFin;
END
GO
