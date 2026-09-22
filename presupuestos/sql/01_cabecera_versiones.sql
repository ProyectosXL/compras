/* =====================================================================
   Fase 2 - Cabecera de versiones del presupuesto de compras proyectadas

   QUE RESUELVE
   La tabla RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO guarda una fila por
   rubro/categoria y repite en cada una el nombre, la fecha y el pais. Eso alcanza
   para listar el historial, pero no para que el cashflow de finanzas lea de aca:
   la temporada guardada es la SOLAPA ('verano'/'invierno'), no una temporada con
   año; no se sabe cual version es la vigente; una version puede ser parcial sin
   que nada lo diga; y no queda registro del costo con el que se calculo.

   Este script agrega la cabecera y las columnas de detalle que faltan.
   NO borra ni modifica datos existentes: solo crea objetos y agrega columnas
   nullable. La migracion de las versiones ya guardadas va en
   02_migracion_versiones.sql.

   DONDE CORRERLO
   En las dos bases de la aplicacion, una por pais:
     - POWER_BI_CONTROL          (Argentina)
     - POWER_BI_CONTROL_URUGUAY  (Uruguay)

   COMO CORRERLO
   Los cuatro bloques estan guardados por las variables @CONFIRMAR_* de abajo.
   En 0 (el valor que viene) NO se escribe nada: cada bloque informa por PRINT
   que haria. Poner en 1 los que se quieran aplicar y volver a ejecutar.
   El script es reejecutable: lo ya aplicado se saltea solo.

   Todo va en un solo lote (sin GO intermedios) a proposito, para que las
   variables de confirmacion valgan para los cuatro bloques. Por eso los DDL
   que dependen de objetos creados en este mismo script van por sp_executesql.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;      -- requerido para crear el indice unico filtrado
GO

/* =====================================================================
   PONER EN 1 LOS BLOQUES QUE SE QUIERAN APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_CABECERA     BIT = 0;   -- bloque 1: tabla de cabecera
DECLARE @CONFIRMAR_LOG_OFICIAL  BIT = 0;   -- bloque 2: log de marcado oficial
DECLARE @CONFIRMAR_COLUMNAS_DET BIT = 0;   -- bloque 3: columnas nuevas del detalle
DECLARE @CONFIRMAR_INDICES      BIT = 0;   -- bloque 4: indices y FK

DECLARE @tablaCab SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA';
DECLARE @tablaLog SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG';
DECLARE @tablaDet SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO';
DECLARE @sql NVARCHAR(MAX);
DECLARE @faltantes INT;

PRINT '=== ' + DB_NAME() + ' ===';

/* =====================================================================
   BLOQUE 1 - Tabla de cabecera: una fila por presupuesto guardado
   ===================================================================== */
IF OBJECT_ID(@tablaCab, 'U') IS NOT NULL
    PRINT 'BLOQUE 1 - OMITIDO: la cabecera ya existe.';
ELSE
BEGIN
    SET @sql = N'
    CREATE TABLE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (
        id                   INT IDENTITY(1,1) NOT NULL,

        /* Se mantiene el nombre como lo genera la app: sigue siendo la clave
           natural con la que se agrupan las filas de detalle y con la que la
           gente reconoce una version. */
        nombre_presupuesto   VARCHAR(255)  NOT NULL,

        /* Reloj del SERVIDOR. Hasta ahora la fecha la mandaba el navegador, asi
           que dependia de como estuviera la maquina de quien guardaba. */
        fecha_guardado       DATETIME      NOT NULL CONSTRAINT DF_RO_T_HCP_CAB_fecha DEFAULT (GETDATE()),

        /* Sale de $_SESSION[''usuario_dns''], la misma convencion que usa
           comercioExterior. El modulo de presupuestos todavia no tiene
           autenticacion, asi que hoy queda en NULL; la columna se crea igual
           para no tener que migrar cuando la haya. */
        usuario_guardado     VARCHAR(100)  NULL,

        pais                 VARCHAR(50)   NOT NULL,

        /* Solapa desde la que se guardo: ''verano'' o ''invierno''. Determina
           que periodo cubre cada columna de venta proyectada, asi que sin esto
           la version no se puede interpretar. */
        solapa               VARCHAR(20)   NOT NULL,

        /* El "hoy" con el que se calculo. Los restos de temporada se prorratean
           contra esta fecha: sin ella el numero no se puede reproducir. */
        fecha_calculo        DATE          NOT NULL,

        /* Dias de la temporada en curso al momento del calculo. Se guardan
           aunque se puedan derivar de fecha_calculo porque son los dos factores
           exactos del prorrateo, y asi la fila se audita sin recalcular nada. */
        dias_restantes_temporada INT       NULL,
        dias_totales_temporada   INT       NULL,

        /* TEMPORADA OBJETIVO: la que esta compra tiene que cubrir, o sea aquella
           en la que los contenedores tienen que estar. Convencion VER AA-AA /
           INV AA. Es el dato que le faltaba a la tabla para que finanzas pueda
           proyectar contra un periodo concreto. */
        temporada_objetivo        VARCHAR(20) NOT NULL,
        temporada_objetivo_desde  DATE        NOT NULL,
        temporada_objetivo_hasta  DATE        NOT NULL,

        /* Periodo que cubre cada columna de venta proyectada. Se guardan las
           fechas y no solo un codigo de temporada porque una columna puede ser
           un resto de temporada, o la suma de un resto mas una temporada
           completa, y ahi un solo codigo no alcanza para describirla. */
        periodo_venta_verano_desde      DATE        NULL,
        periodo_venta_verano_hasta      DATE        NULL,
        periodo_venta_verano_etiqueta   VARCHAR(80) NULL,
        periodo_venta_invierno_desde    DATE        NULL,
        periodo_venta_invierno_hasta    DATE        NULL,
        periodo_venta_invierno_etiqueta VARCHAR(80) NULL,

        /* Completa = se guardo el presupuesto entero, ignorando los filtros de
           la vista. Las cantidades quedan para poder justificar el valor y para
           ver cuanto le falta a una parcial. */
        es_completa          BIT           NOT NULL CONSTRAINT DF_RO_T_HCP_CAB_completa DEFAULT (1),
        filas_guardadas      INT           NULL,
        filas_totales        INT           NULL,

        /* OFICIAL: la version vigente para su pais y temporada objetivo. Una
           sola por combinacion, garantizado por el indice unico filtrado del
           bloque 4. */
        es_oficial           BIT           NOT NULL CONSTRAINT DF_RO_T_HCP_CAB_oficial DEFAULT (0),
        oficial_usuario      VARCHAR(100)  NULL,
        oficial_fecha        DATETIME      NULL,

        CONSTRAINT PK_RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA PRIMARY KEY CLUSTERED (id),

        CONSTRAINT CK_RO_T_HCP_CAB_solapa
            CHECK (solapa IN (''verano'', ''invierno'')),

        /* Una version parcial no representa el presupuesto completo, asi que no
           puede ser la vigente de nada. Se valida en la base y no solo en la
           aplicacion porque es la regla que protege al consumidor externo. */
        CONSTRAINT CK_RO_T_HCP_CAB_oficial_completa
            CHECK (es_oficial = 0 OR es_completa = 1),

        CONSTRAINT CK_RO_T_HCP_CAB_temporada_rango
            CHECK (temporada_objetivo_hasta >= temporada_objetivo_desde)
    );';

    IF @CONFIRMAR_CABECERA = 0
        PRINT 'BLOQUE 1 - PENDIENTE: crearia la cabecera. Poner @CONFIRMAR_CABECERA = 1.';
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 1 - OK: cabecera creada.';
    END
END

/* =====================================================================
   BLOQUE 2 - Log de marcado de version oficial

   Tabla aparte y no columnas en la cabecera: la cabecera dice quien marco la
   version que esta vigente AHORA, pero se pide historial de quien marco que y
   cuando, incluido el desmarcado de la anterior. Eso son varias filas por
   cabecera y no entra en la cabecera misma.
   ===================================================================== */
IF OBJECT_ID(@tablaLog, 'U') IS NOT NULL
    PRINT 'BLOQUE 2 - OMITIDO: el log de oficial ya existe.';
ELSE IF OBJECT_ID(@tablaCab, 'U') IS NULL AND @CONFIRMAR_CABECERA = 0
    PRINT 'BLOQUE 2 - OMITIDO: depende de la cabecera (bloque 1).';
ELSE
BEGIN
    SET @sql = N'
    CREATE TABLE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG (
        id                 INT IDENTITY(1,1) NOT NULL,
        id_cabecera        INT           NOT NULL,

        /* ''MARCAR'' o ''DESMARCAR''. Marcar una version genera las dos filas
           —el desmarcado de la anterior y el marcado de la nueva— en la misma
           transaccion, para que el historial explique el reemplazo completo. */
        accion             VARCHAR(20)   NOT NULL,

        /* La combinacion se repite en el log a proposito: si mañana se corrige
           la temporada objetivo de una cabecera, el log sigue diciendo para que
           combinacion se habia marcado en su momento. */
        pais               VARCHAR(50)   NOT NULL,
        temporada_objetivo VARCHAR(20)   NOT NULL,

        /* Cabecera que quedo desmarcada al marcar esta. NULL si no habia
           ninguna vigente todavia. */
        id_cabecera_reemplazada INT      NULL,

        usuario            VARCHAR(100)  NULL,
        fecha              DATETIME      NOT NULL CONSTRAINT DF_RO_T_HCP_LOG_fecha DEFAULT (GETDATE()),
        observacion        VARCHAR(500)  NULL,

        CONSTRAINT PK_RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG PRIMARY KEY CLUSTERED (id),
        CONSTRAINT CK_RO_T_HCP_LOG_accion CHECK (accion IN (''MARCAR'', ''DESMARCAR'')),
        CONSTRAINT FK_RO_T_HCP_LOG_cabecera FOREIGN KEY (id_cabecera)
            REFERENCES dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (id)
    );';

    IF @CONFIRMAR_LOG_OFICIAL = 0
        PRINT 'BLOQUE 2 - PENDIENTE: crearia el log de oficial. Poner @CONFIRMAR_LOG_OFICIAL = 1.';
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 2 - OK: log de oficial creado.';
    END
END

/* =====================================================================
   BLOQUE 3 - Columnas nuevas en el detalle

   Todas NULL: las filas ya guardadas no las tienen y no se inventan valores.
   nombre_presupuesto y temporada se MANTIENEN como estan, para no romper a
   ningun lector actual del historial.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF OBJECT_ID(@tablaDet, 'U') IS NULL
    PRINT 'BLOQUE 3 - ERROR: no existe ' + @tablaDet + '. Revisar la base.';
ELSE
BEGIN
    /* Cada columna se agrega solo si falta, para poder volver a correr el
       script despues de una aplicacion parcial. */

    /* Referencia a la cabecera. Nullable: las filas historicas la reciben en la
       migracion, y si se decide no migrar, nada se rompe. */
    IF COL_LENGTH(@tablaDet, 'id_cabecera') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD id_cabecera INT NULL;' + CHAR(10), @faltantes += 1;

    /* Componentes de STOCK_PROYECTADO. Con estos seis la fila se audita sola:
         stock_proyectado = cant_stock + cant_stock_guardar
                          + cant_pend_oc_verano + cant_pend_oc_invierno
                          + cant_pend_oc_atemporal - stock_cobertura
       Las CANT_PEND_OC son las mas importantes: entran al stock proyectado, o
       sea que la compra proyectada es NETA de lo ya pedido. Sin guardarlas,
       seis meses despues esas OC ya ingresaron y no hay forma de saber cuanto
       habia pendiente cuando se calculo. Van separadas por temporada porque el
       pago de cada oleada cae en momentos distintos. */
    IF COL_LENGTH(@tablaDet, 'cant_stock') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD cant_stock INT NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'cant_stock_guardar') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD cant_stock_guardar INT NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'cant_pend_oc_verano') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD cant_pend_oc_verano INT NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'cant_pend_oc_invierno') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD cant_pend_oc_invierno INT NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'cant_pend_oc_atemporal') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD cant_pend_oc_atemporal INT NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'stock_cobertura') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD stock_cobertura INT NULL;' + CHAR(10), @faltantes += 1;

    /* Costo con el que se calculo, copiado de FP_T_COSTOS_PARAMETROS al
       guardar. Esa tabla no tiene version ni fecha: se pisa en el lugar, asi
       que una version guardada hoy no se puede reproducir mañana si alguien
       cambio un costo. Se usan los mismos tres nombres que ya usan
       FP_T_COSTOS_PROYECCION y FP_T_PRESUPUESTO_VERSION_CONSOLIDADA, que
       resolvieron lo mismo en el circuito de distribucion. vcosto se puede
       derivar de los otros dos, pero se guarda igual para que el consumidor
       externo no tenga que conocer la formula de nacionalizacion. */
    IF COL_LENGTH(@tablaDet, 'costo_prom') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD costo_prom DECIMAL(18,4) NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'inc_fob') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD inc_fob DECIMAL(18,2) NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'vcosto') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD vcosto DECIMAL(18,4) NULL;' + CHAR(10), @faltantes += 1;

    /* De que temporada historica salio cada venta anterior. Varia POR FILA: el
       calculo toma la ultima temporada con ventas, y un rubro sin movimiento
       reciente cae a una mas vieja que el de al lado. Guardar solo el numero
       dejaba la fila sin decir de cuando era. */
    IF COL_LENGTH(@tablaDet, 'temporada_base_verano') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD temporada_base_verano VARCHAR(20) NULL;' + CHAR(10), @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'temporada_base_invierno') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD temporada_base_invierno VARCHAR(20) NULL;' + CHAR(10), @faltantes += 1;

    IF @faltantes = 0
        PRINT 'BLOQUE 3 - OMITIDO: el detalle ya tiene todas las columnas nuevas.';
    ELSE IF @CONFIRMAR_COLUMNAS_DET = 0
    BEGIN
        PRINT 'BLOQUE 3 - PENDIENTE: agregaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_COLUMNAS_DET = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 3 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) agregada(s) al detalle.';
    END
END

/* =====================================================================
   BLOQUE 4 - Indices y clave foranea

   Depende de los bloques 1 y 3. Si todavia no se aplicaron, se saltea: correr
   el script de nuevo despues de aplicarlos.
   ===================================================================== */
SET @sql = N'';

IF OBJECT_ID(@tablaCab, 'U') IS NULL
    PRINT 'BLOQUE 4 - OMITIDO: falta la cabecera (bloque 1). Volver a correr despues.';
ELSE
BEGIN
    /* UNA SOLA VERSION OFICIAL por pais + temporada objetivo.
       Indice unico FILTRADO: unico solo sobre las filas con es_oficial = 1, sin
       restringir a las demas, que es justo lo que se pide. Un UNIQUE comun
       sobre (pais, temporada_objetivo) habria impedido guardar dos versiones de
       la misma combinacion, que es exactamente lo que se quiere permitir. */
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE object_id = OBJECT_ID(@tablaCab)
                     AND name = 'UQ_RO_T_HCP_CAB_oficial_por_temporada')
        SET @sql += N'CREATE UNIQUE NONCLUSTERED INDEX UQ_RO_T_HCP_CAB_oficial_por_temporada
    ON dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (pais, temporada_objetivo)
    WHERE es_oficial = 1;' + CHAR(10);

    /* Busquedas del historial: por combinacion y por fecha. */
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE object_id = OBJECT_ID(@tablaCab)
                     AND name = 'IX_RO_T_HCP_CAB_pais_temporada')
        SET @sql += N'CREATE NONCLUSTERED INDEX IX_RO_T_HCP_CAB_pais_temporada
    ON dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (pais, temporada_objetivo, fecha_guardado DESC);' + CHAR(10);

    /* El detalle se lee casi siempre por cabecera. */
    IF COL_LENGTH(@tablaDet, 'id_cabecera') IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM sys.indexes
                       WHERE object_id = OBJECT_ID(@tablaDet)
                         AND name = 'IX_RO_T_HCP_DET_cabecera')
        SET @sql += N'CREATE NONCLUSTERED INDEX IX_RO_T_HCP_DET_cabecera
    ON dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (id_cabecera);' + CHAR(10);

    /* FK del detalle a la cabecera. Se crea WITH NOCHECK para no fallar si
       todavia quedan filas sin migrar: vale de aca en adelante y la migracion
       completa las viejas. */
    IF COL_LENGTH(@tablaDet, 'id_cabecera') IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_RO_T_HCP_DET_cabecera')
        SET @sql += N'ALTER TABLE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO WITH NOCHECK
    ADD CONSTRAINT FK_RO_T_HCP_DET_cabecera FOREIGN KEY (id_cabecera)
    REFERENCES dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (id);' + CHAR(10);

    IF LEN(@sql) = 0
        PRINT 'BLOQUE 4 - OMITIDO: los indices y la FK ya existen.';
    ELSE IF @CONFIRMAR_INDICES = 0
    BEGIN
        PRINT 'BLOQUE 4 - PENDIENTE: crearia:';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_INDICES = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 4 - OK: indices y FK creados.';
    END
END

PRINT '';
PRINT '--- Estado ---';
SELECT
    DB_NAME() AS base,
    CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','U') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS cabecera,
    CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG','U') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS log_oficial,
    CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO','id_cabecera') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS columnas_detalle,
    CASE WHEN NOT EXISTS (SELECT 1 FROM sys.indexes
                          WHERE name = 'UQ_RO_T_HCP_CAB_oficial_por_temporada')
         THEN 'FALTA' ELSE 'OK' END AS indice_unico_oficial;
GO
