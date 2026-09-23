/* =====================================================================
   Fase 4 - Baja logica de versiones

   QUE RESUELVE
   Eliminar una version borraba fisicamente su cabecera, su detalle, su compra
   por tramo y su log de oficial. Con un consumidor externo leyendo estas tablas
   eso es demasiado definitivo, y ademas dejaba dos reglas en contradiccion:

     - "el log de oficial no se borra nunca"
     - "la oficial se puede eliminar despues de desmarcarla"

   No pueden cumplirse las dos con borrado fisico: el log apunta a la cabecera
   por clave foranea, asi que conservar el log obliga a conservar la cabecera. Con
   borrado fisico, una version que ALGUNA VEZ fue oficial no se podia eliminar
   nunca mas, ni siquiera desmarcada.

   Con baja logica la version deja de listarse pero sigue existiendo: el log
   conserva su ancla, la version "se elimina" y nada se destruye. A partir de este
   script el modulo NO tiene ninguna escritura destructiva sobre versiones con
   cabecera.

   LO QUE NO HACE
   No hay restaurar. Fue una decision explicita: la baja sigue siendo definitiva
   desde la aplicacion. La diferencia es que los datos siguen ahi, asi que una
   baja por error se revierte con un UPDATE puntual y no con un backup.

   OJO CON EL CONSUMIDOR EXTERNO
   Una version dada de baja SIGUE en las tablas. Cualquier consulta que no filtre
   `eliminada = 0` la va a seguir viendo. El riesgo real esta acotado porque una
   version dada de baja no puede ser oficial (lo garantiza el CHECK del bloque 2),
   asi que todo lo que filtre `es_oficial = 1` —que es lo que corresponde para el
   cashflow— queda cubierto solo. Para lo demas esta la vista del bloque 4, que ya
   filtra las dos cosas.

   DONDE CORRERLO
   En las dos bases de la aplicacion, una por pais:
     - POWER_BI_CONTROL          (Argentina)
     - POWER_BI_CONTROL_URUGUAY  (Uruguay)

   COMO CORRERLO
   Los cuatro bloques estan guardados por las variables @CONFIRMAR_* de abajo.
   En 0 (el valor que viene) NO se escribe nada: cada bloque informa por PRINT
   que haria. Poner en 1 los que se quieran aplicar y volver a ejecutar.
   El script es reejecutable: lo ya aplicado se saltea solo.

   El bloque 4 (la vista para el consumidor externo) es OPCIONAL y se puede
   aplicar despues, cuando se acuerde con finanzas como van a leer.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* =====================================================================
   PONER EN 1 LOS BLOQUES QUE SE QUIERAN APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_COLUMNAS BIT = 0;   -- bloque 1: columnas de baja en la cabecera
DECLARE @CONFIRMAR_CHECK    BIT = 0;   -- bloque 2: una version dada de baja no puede ser oficial
DECLARE @CONFIRMAR_INDICE   BIT = 0;   -- bloque 3: indice de listado
DECLARE @CONFIRMAR_VISTA    BIT = 0;   -- bloque 4: vista para el consumidor externo (opcional)

DECLARE @tablaCab SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA';
DECLARE @sql NVARCHAR(MAX);
DECLARE @faltantes INT;

PRINT '=== ' + DB_NAME() + ' ===';

/* =====================================================================
   BLOQUE 1 - Columnas de baja en la cabecera

   Todas con DEFAULT o NULL: las versiones que ya existen quedan como vigentes
   (eliminada = 0), que es lo que son. No se toca ninguna fila.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF OBJECT_ID(@tablaCab, 'U') IS NULL
    PRINT 'BLOQUE 1 - ERROR: falta la cabecera. Correr antes 01_cabecera_versiones.sql.';
ELSE
BEGIN
    /* La baja en si. NOT NULL con default 0: que una version este o no dada de
       baja es una respuesta que siempre existe, y un NULL ahi obligaria a todas
       las consultas a escribir ISNULL para no perder filas. */
    IF COL_LENGTH(@tablaCab, 'eliminada') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab
             + N' ADD eliminada BIT NOT NULL CONSTRAINT DF_RO_T_HCP_CAB_eliminada DEFAULT (0);' + CHAR(10),
               @faltantes += 1;

    /* Quien la dio de baja y cuando. Mismas convenciones que oficial_usuario /
       oficial_fecha: el usuario sale de $_SESSION[''usuario_dns''] y hoy queda en
       NULL porque el modulo todavia no tiene autenticacion. */
    IF COL_LENGTH(@tablaCab, 'eliminada_fecha') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD eliminada_fecha DATETIME NULL;' + CHAR(10),
               @faltantes += 1;
    IF COL_LENGTH(@tablaCab, 'eliminada_usuario') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD eliminada_usuario VARCHAR(100) NULL;' + CHAR(10),
               @faltantes += 1;

    /* POR QUE se dio de baja. Con borrado fisico este dato no existia en ningun
       lado: la version desaparecia y nadie podia decir por que. */
    IF COL_LENGTH(@tablaCab, 'eliminada_motivo') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD eliminada_motivo VARCHAR(500) NULL;' + CHAR(10),
               @faltantes += 1;

    IF @faltantes = 0
        PRINT 'BLOQUE 1 - OMITIDO: la cabecera ya tiene las columnas de baja.';
    ELSE IF @CONFIRMAR_COLUMNAS = 0
    BEGIN
        PRINT 'BLOQUE 1 - PENDIENTE: agregaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_COLUMNAS = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 1 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) agregada(s).';
    END
END

/* =====================================================================
   BLOQUE 2 - Una version dada de baja no puede ser la oficial

   Es la regla que protege al consumidor externo y por eso va en la BASE y no
   solo en la aplicacion, igual que CK_RO_T_HCP_CAB_oficial_completa. Ademas es
   lo que vuelve seguro el indice unico filtrado que ya existe
   (UQ_..._oficial_por_temporada, WHERE es_oficial = 1): como ninguna fila puede
   estar dada de baja Y ser oficial, ese indice sigue describiendo exactamente el
   conjunto de versiones vigentes, sin tocarlo.
   ===================================================================== */
IF OBJECT_ID(@tablaCab, 'U') IS NULL OR COL_LENGTH(@tablaCab, 'eliminada') IS NULL
    PRINT 'BLOQUE 2 - OMITIDO: falta la columna eliminada (bloque 1). Volver a correr despues.';
ELSE IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_RO_T_HCP_CAB_oficial_no_eliminada')
    PRINT 'BLOQUE 2 - OMITIDO: el CHECK ya existe.';
ELSE IF @CONFIRMAR_CHECK = 0
    PRINT 'BLOQUE 2 - PENDIENTE: crearia CK_RO_T_HCP_CAB_oficial_no_eliminada. Poner @CONFIRMAR_CHECK = 1.';
ELSE
BEGIN
    SET @sql = N'ALTER TABLE ' + @tablaCab + N' ADD CONSTRAINT CK_RO_T_HCP_CAB_oficial_no_eliminada
        CHECK (es_oficial = 0 OR eliminada = 0);';
    EXEC sp_executesql @sql;
    PRINT 'BLOQUE 2 - OK: CHECK creado.';
END

/* =====================================================================
   BLOQUE 3 - Indice de listado

   El panel de versiones y el historial listan siempre lo NO dado de baja, asi
   que la columna entra al indice que ya se usaba para esas busquedas. Se crea uno
   nuevo en vez de rehacer el existente para no tener que dropearlo: son tres
   filas hoy, pero dropear un indice en produccion sin necesidad no se justifica.
   ===================================================================== */
IF OBJECT_ID(@tablaCab, 'U') IS NULL OR COL_LENGTH(@tablaCab, 'eliminada') IS NULL
    PRINT 'BLOQUE 3 - OMITIDO: falta la columna eliminada (bloque 1). Volver a correr despues.';
ELSE IF EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID(@tablaCab) AND name = 'IX_RO_T_HCP_CAB_vigentes')
    PRINT 'BLOQUE 3 - OMITIDO: el indice ya existe.';
ELSE IF @CONFIRMAR_INDICE = 0
    PRINT 'BLOQUE 3 - PENDIENTE: crearia IX_RO_T_HCP_CAB_vigentes. Poner @CONFIRMAR_INDICE = 1.';
ELSE
BEGIN
    SET @sql = N'CREATE NONCLUSTERED INDEX IX_RO_T_HCP_CAB_vigentes
        ON ' + @tablaCab + N' (pais, eliminada, fecha_guardado DESC);';
    EXEC sp_executesql @sql;
    PRINT 'BLOQUE 3 - OK: indice creado.';
END

/* =====================================================================
   BLOQUE 4 - Vista para el consumidor externo   (OPCIONAL)

   Para que el cashflow de finanzas no tenga que conocer ni `eliminada` ni
   `es_oficial`: la vista ya devuelve UNICAMENTE la compra por tramo de las
   versiones oficiales vigentes. Se prefirio darles una vista antes que pedirles
   que agreguen un WHERE: un filtro que hay que acordarse de escribir es un filtro
   que alguna vez no se escribe, y el error seria silencioso.

   Devuelve TODOS los tramos, no solo el objetivo, con sus banderas: el tramo
   intermedio hace falta para resolver la mercaderia que todavia falte ingresar en
   esa temporada. Quien quiera solo lo que la version aporta, filtra es_objetivo.

   Aplicarlo es independiente del resto: se puede dejar para cuando se acuerde con
   finanzas como van a leer.
   ===================================================================== */
IF OBJECT_ID(@tablaCab, 'U') IS NULL OR COL_LENGTH(@tablaCab, 'eliminada') IS NULL
    PRINT 'BLOQUE 4 - OMITIDO: falta la columna eliminada (bloque 1). Volver a correr despues.';
ELSE IF OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO', 'U') IS NULL
    PRINT 'BLOQUE 4 - OMITIDO: falta la tabla de tramos. Correr antes 03_compra_por_tramo.sql.';
ELSE IF @CONFIRMAR_VISTA = 0
    PRINT 'BLOQUE 4 - PENDIENTE: crearia la vista RO_V_COMPRA_PROYECTADA_VIGENTE. Poner @CONFIRMAR_VISTA = 1.';
ELSE
BEGIN
    /* CREATE OR ALTER para que el script siga siendo reejecutable: si la
       definicion cambia, se actualiza en el lugar sin dropear ni perder permisos. */
    SET @sql = N'
    CREATE OR ALTER VIEW dbo.RO_V_COMPRA_PROYECTADA_VIGENTE
    AS
    SELECT
        c.pais,
        c.id                        AS id_version,
        c.nombre_presupuesto,
        c.solapa,
        c.fecha_calculo,
        c.temporada_objetivo        AS temporada_objetivo_version,
        c.tramos_estado,

        d.rubro,
        d.categoria_padre,
        d.stock_proyectado,
        /* Costo con el que se calculo la version, para valorizar sin tener que
           volver a FP_T_COSTOS_PARAMETROS, que se pisa en el lugar. */
        d.costo_prom,
        d.inc_fob,
        d.vcosto,

        t.orden,
        t.temporada_codigo,
        t.temporada_tipo,
        t.temporada_desde,
        t.temporada_hasta,
        t.es_resto,
        /* Es la temporada que ESTA version tiene que cubrir. Los demas tramos son
           control: cada temporada la aporta su propia version oficial. */
        t.es_objetivo,
        /* 0 = resto de la temporada que estaba en curso al calcular: no es
           mercaderia a comprar, es venta que va a quedar sin cubrir. */
        t.es_comprable,
        t.venta_proyectada,
        t.stock_aplicado,
        t.compra,
        /* Parte de `compra` que es stock de seguridad sin reponer y no venta. */
        t.compra_deficit_cobertura
    FROM dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA c
    JOIN dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO d ON d.id_cabecera = c.id
    JOIN dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO t ON t.id_detalle = d.id
    WHERE c.es_oficial = 1
      AND c.eliminada  = 0;';

    EXEC sp_executesql @sql;
    PRINT 'BLOQUE 4 - OK: vista RO_V_COMPRA_PROYECTADA_VIGENTE creada o actualizada.';
END

PRINT '';
PRINT '--- Estado ---';
SELECT
    DB_NAME() AS base,
    CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','eliminada') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS col_eliminada,
    CASE WHEN NOT EXISTS (SELECT 1 FROM sys.check_constraints
                          WHERE name = 'CK_RO_T_HCP_CAB_oficial_no_eliminada')
         THEN 'FALTA' ELSE 'OK' END AS check_oficial_no_eliminada,
    CASE WHEN NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_RO_T_HCP_CAB_vigentes')
         THEN 'FALTA' ELSE 'OK' END AS indice_vigentes,
    CASE WHEN OBJECT_ID('dbo.RO_V_COMPRA_PROYECTADA_VIGENTE','V') IS NULL
         THEN 'FALTA (opcional)' ELSE 'OK' END AS vista_consumidor;
GO
