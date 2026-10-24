/* =====================================================================
   Fase 5 - Indices LP/TE, notas y comentarios en las versiones guardadas

   SOLO ARGENTINA: se corre UNICAMENTE en POWER_BI_CONTROL, igual que el 06.
   En Uruguay la aplicacion detecta que faltan estas columnas y guarda como hoy
   (sin notas, sin comentario y sin version de origen).

   QUE RESUELVE
   Desde el 06 la proyeccion se mueve con el indice Total Empresa y el de Locales
   Propios queda como termometro. Una version guardada tiene que poder decir los
   dos, y ademas por que se toco cada indice y de que version salio.

   DETALLE (RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO)
     indice_lp                       indice de Locales Propios del SP al guardar
     indice_total_empresa_original   indice TE del SP, antes de editarlo
     nota                            nota de la fila (por que se cambio el indice)

   CABECERA (RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA)
     comentario                      comentario general de la version
     id_version_origen               la version que se retomo para armar esta

   QUE NO CAMBIA
   indice_verano_variacion e indice_invierno_variacion conservan su significado:
   el indice que EFECTIVAMENTE movio la proyeccion, el editable. Antes de este
   cambio era el LP editado; desde ahora es el TE editado. Asi una version vieja y
   una nueva se leen con la misma consulta. indice_variacion_original tambien
   sigue siendo el LP original del SP. Una version sin indice_total_empresa_original
   es, por definicion, anterior al indice Total Empresa.

   COMO CORRERLO
   Los tres bloques estan guardados por las variables @CONFIRMAR_* de abajo.
   En 0 (el valor que viene) NO se escribe nada: cada bloque informa por PRINT
   que haria. Poner en 1 los que se quieran aplicar y volver a ejecutar.
   El script es reejecutable: lo ya aplicado se saltea solo. Todas las columnas
   son nullable: no se toca ninguna fila existente.

   Todo va en un solo lote (sin GO intermedios) para que las variables de
   confirmacion valgan para los tres bloques; los DDL van por sp_executesql.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* =====================================================================
   PONER EN 1 LOS BLOQUES QUE SE QUIERAN APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_DETALLE  BIT = 0;   -- bloque 1: indices y nota en el detalle
DECLARE @CONFIRMAR_CABECERA BIT = 0;   -- bloque 2: comentario y version de origen
DECLARE @CONFIRMAR_FK       BIT = 0;   -- bloque 3: FK de la version de origen

DECLARE @tablaDet SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO';
DECLARE @tablaCab SYSNAME = 'dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA';
DECLARE @sql NVARCHAR(MAX);
DECLARE @faltantes INT;

PRINT '=== ' + DB_NAME() + ' ===';

IF DB_NAME() <> N'POWER_BI_CONTROL'
BEGIN
    RAISERROR('Este script es solo para POWER_BI_CONTROL (Argentina). No se aplico nada.', 16, 1);
    RETURN;
END

/* =====================================================================
   BLOQUE 1 - Indices y nota en el detalle

   Mismo tipo que los indices que ya tiene la tabla (DECIMAL(18,2)), para que
   se comparen sin conversiones. La nota es NVARCHAR porque la escribe una
   persona y lleva acentos.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF OBJECT_ID(@tablaDet, 'U') IS NULL
    PRINT 'BLOQUE 1 - ERROR: no existe ' + @tablaDet + '.';
ELSE
BEGIN
    IF COL_LENGTH(@tablaDet, 'indice_lp') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD indice_lp DECIMAL(18,2) NULL;' + CHAR(10),
               @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'indice_total_empresa_original') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD indice_total_empresa_original DECIMAL(18,2) NULL;' + CHAR(10),
               @faltantes += 1;
    IF COL_LENGTH(@tablaDet, 'nota') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaDet + N' ADD nota NVARCHAR(500) NULL;' + CHAR(10),
               @faltantes += 1;

    IF @faltantes = 0
        PRINT 'BLOQUE 1 - OMITIDO: el detalle ya tiene las columnas.';
    ELSE IF @CONFIRMAR_DETALLE = 0
    BEGIN
        PRINT 'BLOQUE 1 - PENDIENTE: agregaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_DETALLE = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 1 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) agregada(s).';
    END
END

/* =====================================================================
   BLOQUE 2 - Comentario y version de origen en la cabecera

   id_version_origen queda NULL en las versiones armadas desde cero: no haber
   retomado nada es una respuesta valida, no un dato faltante.
   ===================================================================== */
SET @sql = N'';
SET @faltantes = 0;

IF OBJECT_ID(@tablaCab, 'U') IS NULL
    PRINT 'BLOQUE 2 - ERROR: falta la cabecera. Correr antes 01_cabecera_versiones.sql.';
ELSE
BEGIN
    IF COL_LENGTH(@tablaCab, 'comentario') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD comentario NVARCHAR(1000) NULL;' + CHAR(10),
               @faltantes += 1;
    IF COL_LENGTH(@tablaCab, 'id_version_origen') IS NULL
        SELECT @sql += N'ALTER TABLE ' + @tablaCab + N' ADD id_version_origen INT NULL;' + CHAR(10),
               @faltantes += 1;

    IF @faltantes = 0
        PRINT 'BLOQUE 2 - OMITIDO: la cabecera ya tiene las columnas.';
    ELSE IF @CONFIRMAR_CABECERA = 0
    BEGIN
        PRINT 'BLOQUE 2 - PENDIENTE: agregaria ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s):';
        PRINT @sql;
        PRINT '           Poner @CONFIRMAR_CABECERA = 1 para aplicar.';
    END
    ELSE
    BEGIN
        EXEC sp_executesql @sql;
        PRINT 'BLOQUE 2 - OK: ' + CAST(@faltantes AS VARCHAR(10)) + ' columna(s) agregada(s).';
    END
END

/* =====================================================================
   BLOQUE 3 - FK de la version de origen a la misma cabecera

   Con baja logica (script 05) la cabecera no se borra nunca desde la
   aplicacion, asi que la FK no bloquea ninguna operacion: solo impide apuntar
   a una version que no existe. En una base sin el 05, eliminar una version que
   fue retomada por otra va a fallar por esta FK; la aplicacion lo informa.
   ===================================================================== */
IF OBJECT_ID(@tablaCab, 'U') IS NULL OR COL_LENGTH(@tablaCab, 'id_version_origen') IS NULL
    PRINT 'BLOQUE 3 - OMITIDO: falta la columna id_version_origen (bloque 2). Volver a correr despues.';
ELSE IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_RO_T_HCP_CAB_version_origen')
    PRINT 'BLOQUE 3 - OMITIDO: la FK ya existe.';
ELSE IF @CONFIRMAR_FK = 0
    PRINT 'BLOQUE 3 - PENDIENTE: crearia FK_RO_T_HCP_CAB_version_origen. Poner @CONFIRMAR_FK = 1.';
ELSE
BEGIN
    SET @sql = N'ALTER TABLE ' + @tablaCab + N' ADD CONSTRAINT FK_RO_T_HCP_CAB_version_origen
    FOREIGN KEY (id_version_origen) REFERENCES ' + @tablaCab + N' (id);';
    EXEC sp_executesql @sql;
    PRINT 'BLOQUE 3 - OK: FK creada.';
END

PRINT '';
PRINT '--- Estado ---';
SELECT
    DB_NAME() AS base,
    CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO','indice_lp') IS NULL
          OR COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO','indice_total_empresa_original') IS NULL
          OR COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO','nota') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS columnas_detalle,
    CASE WHEN COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','comentario') IS NULL
          OR COL_LENGTH('dbo.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA','id_version_origen') IS NULL
         THEN 'FALTA' ELSE 'OK' END AS columnas_cabecera,
    CASE WHEN NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_RO_T_HCP_CAB_version_origen')
         THEN 'FALTA' ELSE 'OK' END AS fk_version_origen;
GO
