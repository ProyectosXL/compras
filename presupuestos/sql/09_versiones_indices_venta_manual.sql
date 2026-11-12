/* =====================================================================
   Fase 7 - Indices exactos, venta manual e indice de cobertura en versiones

   SOLO ARGENTINA: se corre UNICAMENTE en POWER_BI_CONTROL, igual que el 06 a 08.
   En Uruguay la aplicacion detecta que faltan estas columnas y guarda como hoy.

   QUE RESUELVE
   Desde que la venta proyectada se puede cargar a mano, el indice de la fila es
   el que despeja esa venta (por ejemplo 1,234719) y no un numero redondo. Con
   DECIMAL(18,2) la version guardaba 1,23 y al retomarla la venta volvia distinta.
   Ademas el indice de cobertura no se guardaba: al retomar volvia a 1,50.

   DETALLE (RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO)
     indice_verano_variacion     DECIMAL(18,2) -> DECIMAL(18,6)
     indice_invierno_variacion   DECIMAL(18,2) -> DECIMAL(18,6)
     venta_manual_verano         INT NULL   venta cargada a mano (NULL = la calculo el indice)
     venta_manual_invierno       INT NULL   idem invierno
     indice_cobertura            DECIMAL(18,4) NULL  indice de cobertura de la fila

   stock_cobertura ya existe (script 01) y sigue guardando la cobertura CALCULADA.
   Ampliar decimales no cambia ningun valor guardado: 1,23 sigue siendo 1,230000.

   COMO CORRERLO
   Los dos bloques estan guardados por las variables @CONFIRMAR_* de abajo.
   En 0 (el valor que viene) NO se escribe nada: cada bloque informa por PRINT
   que haria. Poner en 1 los que se quieran aplicar y volver a ejecutar.
   Reejecutable: lo ya aplicado se saltea. No borra ni pisa datos.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* =====================================================================
   PONER EN 1 LOS BLOQUES QUE SE QUIERAN APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_DECIMALES BIT = 0;   -- bloque 1: indices a DECIMAL(18,6)
DECLARE @CONFIRMAR_COLUMNAS  BIT = 0;   -- bloque 2: venta manual e indice de cobertura

DECLARE @tablaDet SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO';
DECLARE @sql NVARCHAR(MAX);
DECLARE @faltantes INT;

PRINT '=== ' + DB_NAME() + ' ===';

IF DB_NAME() <> N'POWER_BI_CONTROL'
BEGIN
    RAISERROR('Este script es solo para POWER_BI_CONTROL (Argentina). No se aplico nada.', 16, 1);
    RETURN;
END

IF OBJECT_ID(@tablaDet, 'U') IS NULL
BEGIN
    RAISERROR('No existe el detalle de versiones. No se aplico nada.', 16, 1);
    RETURN;
END

/* =====================================================================
   BLOQUE 1 - Indices editables a DECIMAL(18,6)

   El ALTER COLUMN conserva la nulabilidad que tenga hoy cada columna (se lee de
   sys.columns): cambiarla de paso seria otro cambio, no este. Si alguna columna
   estuviera en un indice, el ALTER falla y no se aplica nada (va en transaccion).
   Despues se refrescan las vistas que leen el detalle (la del script 05), para
   que su metadata tome el tipo nuevo.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ALTER COLUMN ' + QUOTENAME(c.name) + N' DECIMAL(18,6) '
             + CASE WHEN c.is_nullable = 1 THEN N'NULL' ELSE N'NOT NULL' END + N';' + CHAR(10),
       @faltantes += 1
FROM sys.columns c
WHERE c.object_id = OBJECT_ID(@tablaDet)
  AND c.name IN ('indice_verano_variacion', 'indice_invierno_variacion')
  AND NOT (TYPE_NAME(c.user_type_id) = 'decimal' AND c.scale >= 6);

IF @faltantes = 0
    PRINT 'BLOQUE 1 - OMITIDO: los indices ya tienen 6 decimales.';
ELSE IF @CONFIRMAR_DECIMALES = 0
BEGIN
    PRINT 'BLOQUE 1 - PENDIENTE: ampliaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
    PRINT @sql;
    PRINT '           Poner @CONFIRMAR_DECIMALES = 1 para aplicar.';
END
ELSE
BEGIN
    BEGIN TRANSACTION;
    EXEC sp_executesql @sql;

    DECLARE @vista NVARCHAR(400);
    DECLARE vistas CURSOR LOCAL FAST_FORWARD FOR
        SELECT DISTINCT QUOTENAME(OBJECT_SCHEMA_NAME(d.referencing_id)) + '.' + QUOTENAME(OBJECT_NAME(d.referencing_id))
        FROM sys.sql_expression_dependencies d
        JOIN sys.views v ON v.object_id = d.referencing_id
        WHERE d.referenced_id = OBJECT_ID(@tablaDet);
    OPEN vistas;
    FETCH NEXT FROM vistas INTO @vista;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        EXEC sp_refreshview @vista;
        PRINT '           vista refrescada: ' + @vista;
        FETCH NEXT FROM vistas INTO @vista;
    END
    CLOSE vistas;
    DEALLOCATE vistas;

    COMMIT TRANSACTION;
    PRINT 'BLOQUE 1 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) ampliada(s).';
END

/* =====================================================================
   BLOQUE 2 - Venta manual e indice de cobertura

   venta_manual_* en NULL quiere decir "la venta salio del indice", que es lo que
   valen todas las versiones anteriores a este script. indice_cobertura en NULL
   es una version anterior: al retomarla se usa el indice por defecto de hoy.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF COL_LENGTH(@tablaDet, 'venta_manual_verano') IS NULL
    SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD venta_manual_verano INT NULL;' + CHAR(10), @faltantes += 1;
IF COL_LENGTH(@tablaDet, 'venta_manual_invierno') IS NULL
    SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD venta_manual_invierno INT NULL;' + CHAR(10), @faltantes += 1;
IF COL_LENGTH(@tablaDet, 'indice_cobertura') IS NULL
    SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD indice_cobertura DECIMAL(18,4) NULL;' + CHAR(10), @faltantes += 1;

IF @faltantes = 0
    PRINT 'BLOQUE 2 - OMITIDO: el detalle ya tiene las columnas.';
ELSE IF @CONFIRMAR_COLUMNAS = 0
BEGIN
    PRINT 'BLOQUE 2 - PENDIENTE: agregaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
    PRINT @sql;
    PRINT '           Poner @CONFIRMAR_COLUMNAS = 1 para aplicar.';
END
ELSE
BEGIN
    EXEC sp_executesql @sql;
    PRINT 'BLOQUE 2 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) agregada(s).';
END

/* =====================================================================
   VERIFICACION (solo lectura)
   ===================================================================== */
PRINT '';
PRINT '--- Estado ---';
SELECT c.name AS columna, TYPE_NAME(c.user_type_id) AS tipo, c.precision, c.scale, c.is_nullable
FROM sys.columns c
WHERE c.object_id = OBJECT_ID('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO')
  AND c.name IN ('indice_verano_variacion', 'indice_invierno_variacion',
                 'venta_manual_verano', 'venta_manual_invierno', 'indice_cobertura', 'stock_cobertura')
ORDER BY c.name;
GO
