/* =====================================================================
   Fase 6 - Parametros del presupuesto de compras

   SOLO ARGENTINA: se corre UNICAMENTE en POWER_BI_CONTROL. Uruguay sigue con
   los valores fijos de siempre y la aplicacion no le muestra la pantalla.

   QUE RESUELVE
   Hasta ahora los numeros que gobiernan el calculo estaban escritos en el
   codigo o en el SP: 1,50 meses de cobertura, 45 dias, CALZADOS y CAMPERAS sin
   cobertura, los rubros/categorias/articulos excluidos, los canales y la
   ventana de 60 dias del indice. Cambiar cualquiera era un pase a produccion.
   Con estas tablas los cambia cualquier usuario desde la pantalla Parametros,
   y queda registrado quien, cuando y que valor habia antes.

   TABLAS
     RO_T_PC_PARAMETROS            un valor por clave (generales)
     RO_T_PC_PARAMETROS_COBERTURA  indice de cobertura por rubro o categoria
                                   (categoria NULL = todo el rubro)
     RO_T_PC_PARAMETROS_EXCLUSIONES listas de valores por tipo:
                                   RUBRO / CATEGORIA / ARTICULO   -> excluidos del SP
                                   CANAL_VENTA                     -> canales del calculo
                                   CANAL_INDICE_LP                 -> canales del indice LP
                                   RUBRO_SIN_COBERTURA             -> cobertura por defecto 0
                                   Las listas del SP y la de "sin cobertura" son
                                   la misma forma (tipo + valor + activo): se
                                   descarto una tabla por lista porque la pantalla,
                                   el historial y la auditoria serian iguales.
                                   Una baja deja activo = 0 (no borra), asi el
                                   historial puede decir que habia antes.
     RO_T_PC_PARAMETROS_HIST       historial de cambios de las tres anteriores

   DATOS INICIALES
   Iguales a los valores de hoy, para que sin tocar nada la aplicacion y el SP
   den lo mismo que antes. La unica diferencia la trae el calculo nuevo de la
   cobertura, que no depende de estas tablas sino del codigo.

   COMO CORRERLO
   Los dos bloques estan guardados por las variables @CONFIRMAR_* de abajo.
   En 0 (el valor que viene) NO se escribe nada: cada bloque informa por PRINT
   que haria. Poner en 1 los que se quieran aplicar y volver a ejecutar.
   Reejecutable: lo que ya existe se saltea. No borra ni pisa datos.

   Todo va en un solo lote (sin GO intermedios) para que las variables de
   confirmacion valgan para los dos bloques; los DDL van por sp_executesql.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* =====================================================================
   PONER EN 1 LOS BLOQUES QUE SE QUIERAN APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_TABLAS BIT = 0;   -- bloque 1: crear las cuatro tablas
DECLARE @CONFIRMAR_DATOS  BIT = 0;   -- bloque 2: cargar los valores de hoy

DECLARE @sql NVARCHAR(MAX);
DECLARE @faltantes INT;
DECLARE @usuarioInicial NVARCHAR(100) = N'script 08';

PRINT '=== ' + DB_NAME() + ' ===';

IF DB_NAME() <> N'POWER_BI_CONTROL'
BEGIN
    RAISERROR('Este script es solo para POWER_BI_CONTROL (Argentina). No se aplico nada.', 16, 1);
    RETURN;
END

/* =====================================================================
   BLOQUE 1 - Tablas

   Los textos que se comparan contra RO_VENTAS_COMERCIAL y los servidores
   vinculados van en NVARCHAR(100): el SP los compara con COLLATE
   DATABASE_DEFAULT, igual que hoy normaliza las colaciones del UNION.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS', 'U') IS NULL
    SELECT @sql += N'
CREATE TABLE dbo.RO_T_PC_PARAMETROS (
    clave          VARCHAR(60)    NOT NULL CONSTRAINT PK_RO_T_PC_PARAMETROS PRIMARY KEY,
    valor          NVARCHAR(400)  NOT NULL,
    tipo           VARCHAR(20)    NOT NULL
                   CONSTRAINT CK_RO_T_PC_PARAMETROS_tipo CHECK (tipo IN (''DECIMAL'', ''ENTERO'')),
    descripcion    NVARCHAR(400)  NULL,
    usuario_modif  NVARCHAR(100)  NULL,
    fecha_modif    DATETIME2(0)   NOT NULL CONSTRAINT DF_RO_T_PC_PARAMETROS_fecha DEFAULT (SYSDATETIME())
);' + CHAR(10), @faltantes += 1;

IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_COBERTURA', 'U') IS NULL
    SELECT @sql += N'
CREATE TABLE dbo.RO_T_PC_PARAMETROS_COBERTURA (
    id               INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_RO_T_PC_PARAMETROS_COBERTURA PRIMARY KEY,
    rubro            NVARCHAR(100)  NOT NULL,
    categoria        NVARCHAR(100)  NULL,   -- NULL = todo el rubro
    indice_cobertura DECIMAL(9,4)   NOT NULL
                     CONSTRAINT CK_RO_T_PC_PARAMETROS_COBERTURA_indice CHECK (indice_cobertura >= 0),
    usuario_modif    NVARCHAR(100)  NULL,
    fecha_modif      DATETIME2(0)   NOT NULL CONSTRAINT DF_RO_T_PC_PARAMETROS_COBERTURA_fecha DEFAULT (SYSDATETIME())
);
-- Un indice unico comun (no filtrado) admite un solo NULL por rubro: es justo la
-- regla "una sola fila de rubro entero por rubro".
CREATE UNIQUE INDEX UX_RO_T_PC_PARAMETROS_COBERTURA ON dbo.RO_T_PC_PARAMETROS_COBERTURA (rubro, categoria);' + CHAR(10),
    @faltantes += 1;

IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES', 'U') IS NULL
    SELECT @sql += N'
CREATE TABLE dbo.RO_T_PC_PARAMETROS_EXCLUSIONES (
    id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_RO_T_PC_PARAMETROS_EXCLUSIONES PRIMARY KEY,
    tipo           VARCHAR(30)    NOT NULL
                   CONSTRAINT CK_RO_T_PC_PARAMETROS_EXCLUSIONES_tipo CHECK (tipo IN
                       (''RUBRO'', ''CATEGORIA'', ''ARTICULO'', ''CANAL_VENTA'', ''CANAL_INDICE_LP'', ''RUBRO_SIN_COBERTURA'')),
    valor          NVARCHAR(100)  NOT NULL,
    activo         BIT            NOT NULL CONSTRAINT DF_RO_T_PC_PARAMETROS_EXCLUSIONES_activo DEFAULT (1),
    usuario_modif  NVARCHAR(100)  NULL,
    fecha_modif    DATETIME2(0)   NOT NULL CONSTRAINT DF_RO_T_PC_PARAMETROS_EXCLUSIONES_fecha DEFAULT (SYSDATETIME())
);
CREATE UNIQUE INDEX UX_RO_T_PC_PARAMETROS_EXCLUSIONES ON dbo.RO_T_PC_PARAMETROS_EXCLUSIONES (tipo, valor);' + CHAR(10),
    @faltantes += 1;

IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_HIST', 'U') IS NULL
    SELECT @sql += N'
CREATE TABLE dbo.RO_T_PC_PARAMETROS_HIST (
    id              INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_RO_T_PC_PARAMETROS_HIST PRIMARY KEY,
    tabla           VARCHAR(40)    NOT NULL,   -- GENERAL / COBERTURA / LISTA
    clave           NVARCHAR(250)  NOT NULL,   -- clave, rubro|categoria o tipo:valor
    accion          VARCHAR(10)    NOT NULL
                    CONSTRAINT CK_RO_T_PC_PARAMETROS_HIST_accion CHECK (accion IN (''ALTA'', ''MODIF'', ''BAJA'')),
    valor_anterior  NVARCHAR(400)  NULL,
    valor_nuevo     NVARCHAR(400)  NULL,
    usuario         NVARCHAR(100)  NULL,
    fecha           DATETIME2(0)   NOT NULL CONSTRAINT DF_RO_T_PC_PARAMETROS_HIST_fecha DEFAULT (SYSDATETIME())
);
CREATE INDEX IX_RO_T_PC_PARAMETROS_HIST_fecha ON dbo.RO_T_PC_PARAMETROS_HIST (fecha DESC);' + CHAR(10),
    @faltantes += 1;

IF @faltantes = 0
    PRINT 'BLOQUE 1 - OMITIDO: las cuatro tablas ya existen.';
ELSE IF @CONFIRMAR_TABLAS = 0
BEGIN
    PRINT 'BLOQUE 1 - PENDIENTE: crearia ' + CAST(@faltantes AS VARCHAR(10)) + ' tabla(s):';
    PRINT @sql;
    PRINT '           Poner @CONFIRMAR_TABLAS = 1 para aplicar.';
END
ELSE
BEGIN
    EXEC sp_executesql @sql;
    PRINT 'BLOQUE 1 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' tabla(s) creada(s).';
END

/* =====================================================================
   BLOQUE 2 - Valores de hoy

   Cada fila se inserta solo si no existe: volver a correr el script no pisa lo
   que alguien ya cambio desde la pantalla.

   De donde sale cada valor:
     - Generales: PresupuestoCalculos / IndiceEditor (1,50 meses, 45 dias = 1,50 x 30,
       limites 0-12 y 0-10) y el SP 06 (ventana de 60 dias).
     - Dias minimos (30): regla nueva del calculo de cobertura.
     - Listas: los WHERE del SP 06. La regla de outlets (%OUTLET% salvo SINTETICOS
       OUTLET con articulos O% y no OU%) NO es una lista y queda fija en el SP.
   ===================================================================== */
IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS', 'U') IS NULL
   OR OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES', 'U') IS NULL
    PRINT 'BLOQUE 2 - OMITIDO: faltan las tablas (bloque 1). Volver a correr despues.';
ELSE
BEGIN
    DECLARE @generales TABLE (clave VARCHAR(60), valor NVARCHAR(400), tipo VARCHAR(20), descripcion NVARCHAR(400));
    INSERT INTO @generales VALUES
        ('COBERTURA_INDICE_DEFECTO',       N'1.50', 'DECIMAL', N'Indice de cobertura por defecto, en meses de venta'),
        ('COBERTURA_DIAS_POR_MES',         N'30',   'ENTERO',  N'Dias por mes de cobertura (1,50 x 30 = 45 dias)'),
        ('COBERTURA_DIAS_MINIMOS_VENDIDO', N'30',   'ENTERO',  N'Dias transcurridos minimos para usar lo vendido de la temporada en curso'),
        ('COBERTURA_INDICE_MIN',           N'0',    'DECIMAL', N'Indice de cobertura: minimo al editar'),
        ('COBERTURA_INDICE_MAX',           N'12',   'DECIMAL', N'Indice de cobertura: maximo al editar'),
        ('VARIACION_VENTANA_DIAS',         N'60',   'ENTERO',  N'Indice de variacion: ventana de dias (SP)'),
        ('VARIACION_INDICE_MIN',           N'0',    'DECIMAL', N'Indice de variacion: minimo al editar'),
        ('VARIACION_INDICE_MAX',           N'10',   'DECIMAL', N'Indice de variacion: maximo al editar');

    DECLARE @listas TABLE (tipo VARCHAR(30), valor NVARCHAR(100));
    INSERT INTO @listas VALUES
        ('RUBRO',               N'PACKAGING'),
        ('RUBRO',               N'_KITS'),
        ('CATEGORIA',           N'REMERA'),
        ('ARTICULO',            N'XV00LV00A0001'),
        ('CANAL_VENTA',         N'LOCALES PROPIOS'),
        ('CANAL_VENTA',         N'CENTRAL'),
        ('CANAL_INDICE_LP',     N'LOCALES PROPIOS'),
        ('RUBRO_SIN_COBERTURA', N'CALZADOS'),
        ('RUBRO_SIN_COBERTURA', N'CAMPERAS');

    DECLARE @nuevosGen INT = (SELECT COUNT(*) FROM @generales g
                              WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS p WHERE p.clave = g.clave));
    DECLARE @nuevasLis INT = (SELECT COUNT(*) FROM @listas l
                              WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES e
                                                WHERE e.tipo = l.tipo AND e.valor = l.valor));

    IF @nuevosGen + @nuevasLis = 0
        PRINT 'BLOQUE 2 - OMITIDO: los valores iniciales ya estan cargados.';
    ELSE IF @CONFIRMAR_DATOS = 0
    BEGIN
        PRINT 'BLOQUE 2 - PENDIENTE: cargaria ' + CAST(@nuevosGen AS VARCHAR(10)) + ' parametro(s) general(es) y '
            + CAST(@nuevasLis AS VARCHAR(10)) + ' valor(es) de lista.';
        PRINT '           Poner @CONFIRMAR_DATOS = 1 para aplicar.';
    END
    ELSE
    BEGIN
        BEGIN TRANSACTION;

        INSERT INTO dbo.RO_T_PC_PARAMETROS (clave, valor, tipo, descripcion, usuario_modif)
        SELECT g.clave, g.valor, g.tipo, g.descripcion, @usuarioInicial
        FROM @generales g
        WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS p WHERE p.clave = g.clave);

        INSERT INTO dbo.RO_T_PC_PARAMETROS_EXCLUSIONES (tipo, valor, activo, usuario_modif)
        SELECT l.tipo, l.valor, 1, @usuarioInicial
        FROM @listas l
        WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES e
                          WHERE e.tipo = l.tipo AND e.valor = l.valor);

        -- El historial arranca con la carga inicial, asi la primera modificacion
        -- desde la pantalla ya tiene contra que compararse.
        IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_HIST', 'U') IS NOT NULL
            INSERT INTO dbo.RO_T_PC_PARAMETROS_HIST (tabla, clave, accion, valor_anterior, valor_nuevo, usuario)
            SELECT 'GENERAL', g.clave, 'ALTA', NULL, g.valor, @usuarioInicial FROM @generales g
            WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS_HIST h WHERE h.tabla = 'GENERAL' AND h.clave = g.clave)
            UNION ALL
            SELECT 'LISTA', l.tipo + ':' + l.valor, 'ALTA', NULL, l.valor, @usuarioInicial FROM @listas l
            WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS_HIST h WHERE h.tabla = 'LISTA' AND h.clave = l.tipo + ':' + l.valor);

        COMMIT TRANSACTION;
        PRINT 'BLOQUE 2 - OK: ' + CAST(@nuevosGen AS VARCHAR(10)) + ' parametro(s) y '
            + CAST(@nuevasLis AS VARCHAR(10)) + ' valor(es) de lista cargados.';
    END
END

/* =====================================================================
   VERIFICACION (solo lectura)
   ===================================================================== */
PRINT '';
PRINT '--- Estado ---';
SELECT
    DB_NAME() AS base,
    CASE WHEN OBJECT_ID('dbo.RO_T_PC_PARAMETROS', 'U') IS NULL THEN 'FALTA' ELSE 'OK' END AS parametros,
    CASE WHEN OBJECT_ID('dbo.RO_T_PC_PARAMETROS_COBERTURA', 'U') IS NULL THEN 'FALTA' ELSE 'OK' END AS cobertura,
    CASE WHEN OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES', 'U') IS NULL THEN 'FALTA' ELSE 'OK' END AS listas,
    CASE WHEN OBJECT_ID('dbo.RO_T_PC_PARAMETROS_HIST', 'U') IS NULL THEN 'FALTA' ELSE 'OK' END AS historial;

IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS', 'U') IS NOT NULL
    EXEC sp_executesql N'SELECT clave, valor, tipo, usuario_modif, fecha_modif FROM dbo.RO_T_PC_PARAMETROS ORDER BY clave;';
IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES', 'U') IS NOT NULL
    EXEC sp_executesql N'SELECT tipo, valor, activo, usuario_modif, fecha_modif FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES ORDER BY tipo, valor;';
GO
