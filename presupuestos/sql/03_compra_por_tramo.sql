/* =====================================================================
   Fase 3 - Compra proyectada abierta por tramo

   QUE RESUELVE
   La fase 2 dejo una fila de detalle por rubro/categoria con UN solo numero de
   compra proyectada, para toda la ventana que cubre la solapa. Transitando
   verano, la solapa verano calcula:

       compra = stock proyectado - (resto VER 26-27 + VER 27-28) - INV 27

   Ese numero sirve para decidir cuanto comprar, pero no para que el cashflow de
   finanzas proyecte pagos: lo de INV 27 y lo de VER 27-28 llegan en
   CONTENEDORES DISTINTOS y en MESES DISTINTOS, asi que se pagan en meses
   distintos. Con un solo total no hay forma de separarlos.

   Este script agrega la tabla hija con una fila por rubro, categoria y TRAMO, y
   una columna de estado en la cabecera para distinguir las versiones cuyo
   reparto se calculo al guardar de las que se reconstruyeron en la migracion y
   de las que no se pudieron reconstruir.

   NO borra ni modifica datos existentes: solo crea una tabla, agrega una columna
   nullable a la cabecera y crea indices. El detalle NO se toca: sigue teniendo
   compra_proyectada y venta_proyectada_verano/invierno con el mismo significado,
   para no romper a ningun lector actual. La carga de las versiones ya guardadas
   va en 04_migracion_tramos.php.

   DONDE CORRERLO
   En las dos bases de la aplicacion, una por pais:
     - POWER_BI_CONTROL          (Argentina)
     - POWER_BI_CONTROL_URUGUAY  (Uruguay)

   COMO CORRERLO
   Los tres bloques estan guardados por las variables @CONFIRMAR_* de abajo.
   En 0 (el valor que viene) NO se escribe nada: cada bloque informa por PRINT
   que haria. Poner en 1 los que se quieran aplicar y volver a ejecutar.
   El script es reejecutable: lo ya aplicado se saltea solo.

   Todo va en un solo lote (sin GO intermedios) a proposito, para que las
   variables de confirmacion valgan para los tres bloques. Por eso los DDL que
   dependen de objetos creados en este mismo script van por sp_executesql.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* =====================================================================
   PONER EN 1 LOS BLOQUES QUE SE QUIERAN APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_TRAMOS       BIT = 0;   -- bloque 1: tabla de tramos
DECLARE @CONFIRMAR_COL_CABECERA BIT = 0;   -- bloque 2: estado del reparto en la cabecera
DECLARE @CONFIRMAR_INDICES      BIT = 0;   -- bloque 3: indices y FK

DECLARE @tablaTra SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO';
DECLARE @tablaCab SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA';
DECLARE @tablaDet SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO';
DECLARE @sql NVARCHAR(MAX);
DECLARE @faltantes INT;

PRINT '=== ' + DB_NAME() + ' ===';

/* =====================================================================
   BLOQUE 1 - Tabla de tramos: una fila por rubro, categoria y tramo
   ===================================================================== */
IF OBJECT_ID(@tablaTra, 'U') IS NOT NULL
    PRINT 'BLOQUE 1 - OMITIDO: la tabla de tramos ya existe.';
ELSE IF OBJECT_ID(@tablaCab, 'U') IS NULL
    PRINT 'BLOQUE 1 - ERROR: falta la cabecera. Correr antes 01_cabecera_versiones.sql.';
ELSE
BEGIN
    SET @sql = N'
    CREATE TABLE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO (
        id            INT IDENTITY(1,1) NOT NULL,

        /* La version a la que pertenece. Se guarda ademas de id_detalle porque
           el consumidor externo filtra por version y por temporada objetivo, y
           asi no tiene que pasar por el detalle para hacerlo. */
        id_cabecera   INT           NOT NULL,

        /* Fila de detalle que se abre en estos tramos. Es la que tiene el
           stock proyectado, las bases y la compra_proyectada contra la que
           cierra el invariante. */
        id_detalle    INT           NOT NULL,

        /* Se repiten rubro y categoria a proposito: el cashflow lee esta tabla
           sola, agrupada por temporada, y obligarlo a unir con el detalle solo
           para saber de que rubro es cada fila no aporta nada. */
        rubro           VARCHAR(255) NULL,
        categoria_padre VARCHAR(255) NULL,

        /* Posicion en el orden CRONOLOGICO de venta, empezando en 0. Es el orden
           en que se consume el stock, asi que sin el no se puede auditar por que
           un tramo recibio una compra y el siguiente otra. */
        orden         TINYINT       NOT NULL,

        /* Temporada del tramo, convencion VER AA-AA / INV AA. Se guardan tambien
           las fechas porque un tramo puede ser el RESTO de una temporada, y ahi
           el codigo solo no dice desde cuando cuenta: el resto arranca el dia en
           que se calculo, no el dia en que empezo la temporada. */
        temporada_codigo VARCHAR(20) NOT NULL,
        temporada_tipo   VARCHAR(10) NOT NULL,
        temporada_desde  DATE        NOT NULL,
        temporada_hasta  DATE        NOT NULL,

        /* Resto de la temporada en curso (parcial) o temporada completa. */
        es_resto      BIT           NOT NULL CONSTRAINT DF_RO_T_HCP_TRA_resto DEFAULT (0),

        /* Es la temporada objetivo de ESTA version, o sea la que la compra tiene
           que cubrir. Los demas tramos son control: una version oficial aporta
           al consumidor externo solo la compra de su temporada objetivo, porque
           si no INV 27 quedaria cubierta dos veces (por la oficial de INV 27 y
           por el tramo intermedio de la oficial de VER 27-28). */
        es_objetivo   BIT           NOT NULL CONSTRAINT DF_RO_T_HCP_TRA_objetivo DEFAULT (0),

        /* COMPRABLE = se puede cubrir con un contenedor nuevo. El resto de la
           temporada en curso NO lo es: lo que falte ahi ya no llega a tiempo.
           Su compra se guarda igual —es venta que va a quedar sin cubrir, y se
           muestra aparte con ese nombre— pero no es mercaderia a comprar y el
           consumidor externo la filtra con esta columna. Se la dejo dentro de
           `compra` en vez de en una columna separada para que el invariante de
           abajo siga siendo literal. */
        es_comprable  BIT           NOT NULL CONSTRAINT DF_RO_T_HCP_TRA_comprable DEFAULT (1),

        /* Venta proyectada de ESTE tramo. Redondeada por tramo y no sobre la
           suma: asi venta_proyectada_verano del detalle es exactamente la suma
           de los tramos de tipo VERANO, sin diferencias de uno o dos. */
        venta_proyectada INT        NOT NULL,

        /* Cuanto del stock proyectado alcanzo a cubrir este tramo. Se guarda
           aunque se pueda derivar porque es lo que explica el reparto: el stock
           se consume en orden cronologico y lo que sobra de un tramo es lo que
           cubre al siguiente. */
        stock_aplicado   INT        NOT NULL,

        /* Lo que el stock no alcanzo a cubrir. INVARIANTE, por fila de detalle:
             SUM(compra) = MAX(0, -compra_proyectada)
           Las filas con excedente dan 0 en todos sus tramos. */
        compra           INT        NOT NULL,

        /* Cuanto de `compra` es deficit de stock de cobertura y no venta
           proyectada. Aparece cuando el stock de seguridad supera a todo lo
           disponible y el stock proyectado arranca negativo (ACCESORIO DE CUERO:
           0 - 309 = -309). Se imputa al primer tramo COMPRABLE, no al primero
           cronologico: ese es el resto de la temporada en curso, que ya no se
           puede comprar y que el consumidor externo no lee, asi que el deficit
           se habria perdido. Va en columna propia para que finanzas pueda
           tratarlo distinto de una compra por venta. */
        compra_deficit_cobertura INT NOT NULL CONSTRAINT DF_RO_T_HCP_TRA_deficit DEFAULT (0),

        CONSTRAINT PK_RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO PRIMARY KEY CLUSTERED (id),

        CONSTRAINT CK_RO_T_HCP_TRA_tipo
            CHECK (temporada_tipo IN (''VERANO'', ''INVIERNO'')),

        CONSTRAINT CK_RO_T_HCP_TRA_rango
            CHECK (temporada_hasta >= temporada_desde),

        /* Las cantidades no pueden ser negativas: una compra negativa seria un
           excedente, y el excedente no se reparte, se deja en 0. */
        CONSTRAINT CK_RO_T_HCP_TRA_no_negativos
            CHECK (venta_proyectada >= 0 AND stock_aplicado >= 0
                   AND compra >= 0 AND compra_deficit_cobertura >= 0),

        /* El deficit es una PARTE de la compra del tramo, no algo aparte. */
        CONSTRAINT CK_RO_T_HCP_TRA_deficit_parte
            CHECK (compra_deficit_cobertura <= compra),

        /* El resto de la temporada en curso nunca es comprable, y ningun otro
           tramo deja de serlo. Se valida en la base porque es la regla que
           decide que se le informa al consumidor externo. */
        CONSTRAINT CK_RO_T_HCP_TRA_resto_no_comprable
            CHECK (es_comprable = CASE WHEN es_resto = 1 THEN 0 ELSE 1 END)
    );';

    IF @CONFIRMAR_TRAMOS = 0
        PRINT 'BLOQUE 1 - PENDIENTE: crearia la tabla de tramos. Poner @CONFIRMAR_TRAMOS = 1.';
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 1 - OK: tabla de tramos creada.';
    END
END

/* =====================================================================
   BLOQUE 2 - Estado del reparto, en la cabecera

   Va en la cabecera y no en la tabla de tramos porque describe a la VERSION
   entera: una version reconstruida no tiene filas distintas, tiene otro origen.
   Sin esto, una version sin tramos no se distingue de una que todavia no se
   migro, y el consumidor externo no puede saber si le falta informacion o si
   esa version nunca la tuvo.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF OBJECT_ID(@tablaCab, 'U') IS NULL
    PRINT 'BLOQUE 2 - OMITIDO: falta la cabecera (01_cabecera_versiones.sql).';
ELSE
BEGIN
    IF COL_LENGTH(@tablaCab, 'tramos_estado') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD tramos_estado VARCHAR(20) NULL;' + CHAR(10),
               @faltantes += 1;

    /* Motivo, cuando no se pudo reconstruir. Texto libre y no un codigo: es para
       que una persona entienda por que esa version quedo sin reparto. */
    IF COL_LENGTH(@tablaCab, 'tramos_observacion') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD tramos_observacion VARCHAR(500) NULL;' + CHAR(10),
               @faltantes += 1;

    IF @faltantes = 0
        PRINT 'BLOQUE 2 - OMITIDO: la cabecera ya tiene las columnas de estado.';
    ELSE IF @CONFIRMAR_COL_CABECERA = 0
    BEGIN
        PRINT 'BLOQUE 2 - PENDIENTE: agregaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_COL_CABECERA = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 2 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) agregada(s) a la cabecera.';

        /* El CHECK se crea despues de la columna y solo si falta, para que el
           script se pueda volver a correr sin fallar. Los tres valores son los
           unicos posibles:
             CALCULADO     - el reparto se calculo al guardar la version
             RECONSTRUIDO  - se reconstruyo en la migracion desde los dias guardados
             SIN_REPARTO   - no se pudo reconstruir exacto; NO se invento nada */
        IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_RO_T_HCP_CAB_tramos_estado')
        BEGIN
            SET @sql = N'ALTER TABLE ' + @tablaCab + N' ADD CONSTRAINT CK_RO_T_HCP_CAB_tramos_estado
                CHECK (tramos_estado IS NULL OR tramos_estado IN (''CALCULADO'', ''RECONSTRUIDO'', ''SIN_REPARTO''));';
            EXEC sp_executesql @sql;
            PRINT 'BLOQUE 2 - OK: CHECK de tramos_estado creado.';
        END
    END
END

/* =====================================================================
   BLOQUE 3 - Indices y claves foraneas

   Depende de los bloques anteriores. Si todavia no se aplicaron, se saltea:
   correr el script de nuevo despues de aplicarlos.
   ===================================================================== */
SET @sql = N'';

IF OBJECT_ID(@tablaTra, 'U') IS NULL
    PRINT 'BLOQUE 3 - OMITIDO: falta la tabla de tramos (bloque 1). Volver a correr despues.';
ELSE
BEGIN
    /* UN SOLO tramo por fila de detalle y posicion. Es lo que impide que una
       migracion corrida dos veces duplique el reparto y rompa el invariante:
       la suma de las compras por tramo dejaria de dar la compra proyectada. */
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE object_id = OBJECT_ID(@tablaTra)
                     AND name = 'UQ_RO_T_HCP_TRA_detalle_orden')
        SET @sql += N'CREATE UNIQUE NONCLUSTERED INDEX UQ_RO_T_HCP_TRA_detalle_orden
    ON dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO (id_detalle, orden);' + CHAR(10);

    /* La consulta del cashflow: dame la compra de tal temporada en tal version.
       Se incluyen las columnas de cantidad para que no tenga que ir a la tabla. */
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE object_id = OBJECT_ID(@tablaTra)
                     AND name = 'IX_RO_T_HCP_TRA_cabecera_temporada')
        SET @sql += N'CREATE NONCLUSTERED INDEX IX_RO_T_HCP_TRA_cabecera_temporada
    ON dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO (id_cabecera, temporada_codigo)
    INCLUDE (es_objetivo, es_comprable, compra, compra_deficit_cobertura);' + CHAR(10);

    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_RO_T_HCP_TRA_cabecera')
        SET @sql += N'ALTER TABLE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO
    ADD CONSTRAINT FK_RO_T_HCP_TRA_cabecera FOREIGN KEY (id_cabecera)
    REFERENCES dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA (id);' + CHAR(10);

    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_RO_T_HCP_TRA_detalle')
        SET @sql += N'ALTER TABLE dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO
    ADD CONSTRAINT FK_RO_T_HCP_TRA_detalle FOREIGN KEY (id_detalle)
    REFERENCES dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (id);' + CHAR(10);

    IF LEN(@sql) = 0
        PRINT 'BLOQUE 3 - OMITIDO: los indices y las FK ya existen.';
    ELSE IF @CONFIRMAR_INDICES = 0
    BEGIN
        PRINT 'BLOQUE 3 - PENDIENTE: crearia:';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_INDICES = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 3 - OK: indices y FK creados.';
    END
END

PRINT '';
PRINT '--- Estado ---';
SELECT
    DB_NAME() AS base,
    CASE WHEN OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO','U') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS tabla_tramos,
    CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','tramos_estado') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS col_tramos_estado,
    CASE WHEN NOT EXISTS (SELECT 1 FROM sys.indexes
                          WHERE name = 'UQ_RO_T_HCP_TRA_detalle_orden')
         THEN 'FALTA' ELSE 'OK' END AS indice_unico_tramo,
    CASE WHEN NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_RO_T_HCP_TRA_detalle')
         THEN 'FALTA' ELSE 'OK' END AS fk_detalle;
GO
