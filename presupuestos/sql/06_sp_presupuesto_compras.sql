/* =====================================================================
   dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS
   Base de datos: POWER_BI_CONTROL (Argentina) - Servidor XL-APPS1

   HISTORIAL DE CAMBIOS:
   - 24/10/2026: Agregado de columnas VTA_60D_LP, VTA_60D_ANT_LP, VTA_60D_TE,
                 VTA_60D_ANT_TE, INDICE_VARIACION_TOTAL_EMPRESA y VTA_TEMPORADA_EN_CURSO.
   - 06/11/2026: Ampliación a UniversoItems (FULL OUTER / UNION de Ventas,
                 StockData, ComprasData y StockGuardarData).
                 Permite que categorías con órdenes de compra pendientes o stock
                 (ej: BANDOLERA, CARTERA y TOTE en SINTETICOS OUTLET; GORRO/SOMBRERO
                 en CHALINAS; MALETIN en CARTERAS DE CUERO) aparezcan en la tabla
                 y en las solapas de Compra Proyectada y Stock Proyectado,
                 garantizando que el total de OCs coincida exactamente con
                 el módulo de Contenedores (32.550 OCs para SINTETICOS OUTLET).
                 Se normaliza la colación de servidores vinculados (Latin1_General_BIN
                 vs Modern_Spanish_CI_AS) para evitar errores de colación en UNION.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

DECLARE @CONFIRMAR_SP BIT = 1;

PRINT '=== ' + DB_NAME() + ' ===';

IF DB_NAME() <> N'POWER_BI_CONTROL'
BEGIN
    RAISERROR('Este script es solo para POWER_BI_CONTROL (Argentina). No se aplico nada.', 16, 1);
    SET NOEXEC ON;
END
ELSE IF @CONFIRMAR_SP = 0
BEGIN
    PRINT 'PENDIENTE: Poner @CONFIRMAR_SP = 1 para aplicar el ALTER PROCEDURE.';
    SET NOEXEC ON;
END
GO

CREATE OR ALTER PROCEDURE dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        -- Ejecutar procedimiento para cargar datos previos
        EXEC PrepararDatosTemporadas;

        DECLARE @SQL NVARCHAR(MAX) = '';
        DECLARE @ColumnasPivot NVARCHAR(MAX) = '';
        DECLARE @ColumnasIsnull NVARCHAR(MAX) = '';

        -- Primer dia de la temporada en curso. Misma regla que
        -- PresupuestoCalculos::obtenerTemporadaActual(); se calcula aca y no con
        -- una fecha fija para que el SP no haya que tocarlo cada temporada.
        DECLARE @Hoy DATE = CAST(GETDATE() AS DATE);
        DECLARE @InicioTemporadaEnCurso DATE =
            CASE
                WHEN MONTH(@Hoy) >= 8 THEN DATEFROMPARTS(YEAR(@Hoy), 8, 1)
                WHEN MONTH(@Hoy) = 1  THEN DATEFROMPARTS(YEAR(@Hoy) - 1, 8, 1)
                ELSE                       DATEFROMPARTS(YEAR(@Hoy), 2, 1)
            END;

        -- Obtener columnas dinámicas para el PIVOT
        SELECT @ColumnasPivot = STUFF((
            SELECT ', ' + QUOTENAME(TEMPORADA)
            FROM dbo.RO_TABLA_TEMPORADAS_ACTUALES
            ORDER BY TEMPORADA
            FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

        -- Validar que existan temporadas
        IF @ColumnasPivot IS NULL OR @ColumnasPivot = ''
        BEGIN
            RAISERROR('No se encontraron temporadas en RO_TABLA_TEMPORADAS_ACTUALES', 16, 1);
            RETURN;
        END

        -- Obtener columnas con ISNULL para evitar NULLs
        SELECT @ColumnasIsnull = STUFF((
            SELECT ', ISNULL(' + QUOTENAME(TEMPORADA) + ', 0) AS ' + QUOTENAME(COLUMNA_NOMBRE)
            FROM dbo.RO_TABLA_TEMPORADAS_ACTUALES
            ORDER BY TEMPORADA
            FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

        -- Armar SQL dinámico
        SET @SQL = N'
        IF OBJECT_ID(''dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS'', ''U'') IS NOT NULL
            DROP TABLE dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS;

        WITH Ventas AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS CANT_VEND, T.TEMPORADA
            FROM RO_VENTAS_COMERCIAL V
            INNER JOIN dbo.RO_TABLA_TEMPORADAS_ACTUALES T
                ON V.FECHA_VENTA >= T.DESDE_FECHA AND V.FECHA_VENTA <= T.HASTA_FECHA
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND FECHA_VENTA IS NOT NULL
              AND RUBRO IS NOT NULL
              AND CATEGORIA_PADRE IS NOT NULL
              AND ARTICULO != ''XV00LV00A0001''
            GROUP BY RUBRO, CATEGORIA_PADRE, T.TEMPORADA
        ),
        StockData AS (
            SELECT CAST(RUBRO AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS RUBRO,
                   CAST(CATEGORIA_PADRE AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS CATEGORIA_PADRE,
                   SUM(ISNULL(CANT_STOCK, 0)) AS CANT_STOCK
            FROM [LAKERBIS].LOCALES_LAKERS.DBO.RO_STOCK_LAKERS_RUBRO_CATEGORIA
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
              AND CATEGORIA_PADRE != ''REMERA''
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%'' OR RUBRO = ''SINTETICOS OUTLET'')
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        ComprasData AS (
            SELECT CAST(RUBRO AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS RUBRO,
                   CAST(CATEGORIA_PADRE AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS CATEGORIA_PADRE,
                   SUM(ISNULL(CANT_PEND_OC_VERANO, 0)) AS CANT_PEND_OC_VERANO,
                   SUM(ISNULL(CANT_PEND_OC_INVIERNO, 0)) AS CANT_PEND_OC_INVIERNO,
                   SUM(ISNULL(CANT_PEND_OC_ATEMPORAL, 0)) AS CANT_PEND_OC_ATEMPORAL
            FROM [XL-TANGO].LAKER_SA.DBO.RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
              AND CATEGORIA_PADRE != ''REMERA''
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%'' OR RUBRO = ''SINTETICOS OUTLET'')
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        StockGuardarData AS (
            SELECT CAST(RUBRO AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS RUBRO,
                   CAST(CATEGORIA_PADRE AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS CATEGORIA_PADRE,
                   SUM(ISNULL(CANT_STOCK_GUARDAR, 0)) AS CANT_STOCK_GUARDAR
            FROM [LAKERBIS].LOCALES_LAKERS.DBO.RO_STOCK_LAKERS_RUBRO_CATEGORIA_GUARDAR
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
              AND CATEGORIA_PADRE != ''REMERA''
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%'' OR RUBRO = ''SINTETICOS OUTLET'')
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        UniversoItems AS (
            SELECT DISTINCT RUBRO, CATEGORIA_PADRE FROM Ventas
            UNION
            SELECT DISTINCT RUBRO, CATEGORIA_PADRE FROM StockData WHERE CANT_STOCK > 0
            UNION
            SELECT DISTINCT RUBRO, CATEGORIA_PADRE FROM ComprasData WHERE CANT_PEND_OC_VERANO > 0 OR CANT_PEND_OC_INVIERNO > 0 OR CANT_PEND_OC_ATEMPORAL > 0
            UNION
            SELECT DISTINCT RUBRO, CATEGORIA_PADRE FROM StockGuardarData WHERE CANT_STOCK_GUARDAR > 0
        ),
        VentasUltimos60Dias AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_ULT_60_DIAS
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND FECHA_VENTA >= DATEADD(DAY, -60, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND FECHA_VENTA <= DATEADD(DAY, -1, CAST(GETDATE() AS DATE))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasAnoAnterior AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_ULT_60_DIAS_ANO_ANT
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND FECHA_VENTA >= DATEADD(YEAR, -1, DATEADD(DAY, -60, DATEADD(DAY, -1, CAST(GETDATE() AS DATE))))
              AND FECHA_VENTA <= DATEADD(YEAR, -1, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasUltimos60DiasTE AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_60D_TE
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND ARTICULO != ''XV00LV00A0001''
              AND FECHA_VENTA >= DATEADD(DAY, -60, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND FECHA_VENTA <= DATEADD(DAY, -1, CAST(GETDATE() AS DATE))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasAnoAnteriorTE AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_60D_ANT_TE
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND ARTICULO != ''XV00LV00A0001''
              AND FECHA_VENTA >= DATEADD(YEAR, -1, DATEADD(DAY, -60, DATEADD(DAY, -1, CAST(GETDATE() AS DATE))))
              AND FECHA_VENTA <= DATEADD(YEAR, -1, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasTemporadaEnCurso AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_TEMPORADA_EN_CURSO
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND ARTICULO != ''XV00LV00A0001''
              AND FECHA_VENTA >= @InicioTemporadaEnCurso
              AND FECHA_VENTA <= DATEADD(DAY, -1, CAST(GETDATE() AS DATE))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        Ventas360Dias AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_ULT_360_DIAS
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND (RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))
              AND CATEGORIA != ''REMERA''
              AND FECHA_VENTA >= DATEADD(DAY, -360, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND FECHA_VENTA <= DATEADD(DAY, -1, CAST(GETDATE() AS DATE))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        PivotData AS (
            SELECT RUBRO, CATEGORIA_PADRE, TEMPORADA, CANT_VEND
            FROM Ventas
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL AND TEMPORADA IS NOT NULL
        )
        SELECT
            COALESCE(U.RUBRO, '''') AS RUBRO,
            COALESCE(U.CATEGORIA_PADRE, '''') AS CATEGORIA_PADRE' +
            ISNULL(', ' + @ColumnasIsnull, '') + ',
            ISNULL(S.CANT_STOCK, 0) AS CANT_STOCK,
            ISNULL(SG.CANT_STOCK_GUARDAR, 0) AS CANT_STOCK_GUARDAR,
            ISNULL(C.CANT_PEND_OC_VERANO, 0) AS CANT_PEND_OC_VERANO,
            ISNULL(C.CANT_PEND_OC_INVIERNO, 0) AS CANT_PEND_OC_INVIERNO,
            ISNULL(C.CANT_PEND_OC_ATEMPORAL, 0) AS CANT_PEND_OC_ATEMPORAL,
            CASE
            WHEN ISNULL(VA.VTA_ULT_60_DIAS_ANO_ANT, 0) = 0 THEN
                    CASE WHEN ISNULL(VU.VTA_ULT_60_DIAS, 0) > 0 THEN 0 ELSE 0.00 END
                ELSE
                    CAST(ISNULL(VU.VTA_ULT_60_DIAS, 0) / NULLIF(CAST(VA.VTA_ULT_60_DIAS_ANO_ANT AS FLOAT), 0) AS DECIMAL(10,2))
            END AS INDICE_VARIACION,
            CASE
                WHEN U.RUBRO IN (''CALZADOS'',''CAMPERAS'') THEN 0
                WHEN ISNULL(V360.VTA_ULT_360_DIAS, 0) = 0 THEN 0
                ELSE CAST(ROUND((ISNULL(V360.VTA_ULT_360_DIAS, 0) / 360.0) * 45.0, 0) AS INT)
            END AS STOCK_COBERTURA,
            GETDATE() AS ULT_ACTUALIZACION,
            -- Columnas adicionales
            ISNULL(VU.VTA_ULT_60_DIAS, 0) AS VTA_60D_LP,
            ISNULL(VA.VTA_ULT_60_DIAS_ANO_ANT, 0) AS VTA_60D_ANT_LP,
            ISNULL(VTE.VTA_60D_TE, 0) AS VTA_60D_TE,
            ISNULL(VATE.VTA_60D_ANT_TE, 0) AS VTA_60D_ANT_TE,
            CASE
            WHEN ISNULL(VATE.VTA_60D_ANT_TE, 0) = 0 THEN
                    CASE WHEN ISNULL(VTE.VTA_60D_TE, 0) > 0 THEN 0 ELSE 0.00 END
                ELSE
                    CAST(ISNULL(VTE.VTA_60D_TE, 0) / NULLIF(CAST(VATE.VTA_60D_ANT_TE AS FLOAT), 0) AS DECIMAL(10,2))
            END AS INDICE_VARIACION_TOTAL_EMPRESA,
            ISNULL(VTC.VTA_TEMPORADA_EN_CURSO, 0) AS VTA_TEMPORADA_EN_CURSO
        INTO RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS
        FROM UniversoItems U
        LEFT JOIN (
            SELECT * FROM PivotData
            PIVOT (
              SUM(CANT_VEND)
                FOR TEMPORADA IN (' + @ColumnasPivot + N')
            ) AS P
        ) AS PivotTable
            ON U.RUBRO = PivotTable.RUBRO
            AND U.CATEGORIA_PADRE = PivotTable.CATEGORIA_PADRE
        LEFT JOIN StockData S ON U.RUBRO = S.RUBRO
            AND U.CATEGORIA_PADRE = S.CATEGORIA_PADRE
        LEFT JOIN ComprasData C ON U.RUBRO = C.RUBRO
            AND U.CATEGORIA_PADRE = C.CATEGORIA_PADRE
        LEFT JOIN StockGuardarData SG ON U.RUBRO = SG.RUBRO
            AND U.CATEGORIA_PADRE = SG.CATEGORIA_PADRE
        LEFT JOIN VentasUltimos60Dias VU ON U.RUBRO = VU.RUBRO
            AND U.CATEGORIA_PADRE = VU.CATEGORIA_PADRE
        LEFT JOIN VentasAnoAnterior VA ON U.RUBRO = VA.RUBRO
            AND U.CATEGORIA_PADRE = VA.CATEGORIA_PADRE
        LEFT JOIN VentasUltimos60DiasTE VTE ON U.RUBRO = VTE.RUBRO
            AND U.CATEGORIA_PADRE = VTE.CATEGORIA_PADRE
        LEFT JOIN VentasAnoAnteriorTE VATE ON U.RUBRO = VATE.RUBRO
            AND U.CATEGORIA_PADRE = VATE.CATEGORIA_PADRE
        LEFT JOIN VentasTemporadaEnCurso VTC ON U.RUBRO = VTC.RUBRO
            AND U.CATEGORIA_PADRE = VTC.CATEGORIA_PADRE
        LEFT JOIN Ventas360Dias V360 ON U.RUBRO = V360.RUBRO
            AND U.CATEGORIA_PADRE = V360.CATEGORIA_PADRE
        ORDER BY U.RUBRO, U.CATEGORIA_PADRE;';

        -- Ejecutar. La fecha de inicio entra como parametro y no concatenada: asi
        -- el texto del SQL dinamico no depende de la configuracion regional de fechas.
        EXEC sp_executesql @SQL,
             N'@InicioTemporadaEnCurso DATE',
             @InicioTemporadaEnCurso = @InicioTemporadaEnCurso;

    END TRY
    BEGIN CATCH
        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();
        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END;
GO

SET NOEXEC OFF;
GO

/* =====================================================================
   VERIFICACION (solo lectura)
   ===================================================================== */
IF COL_LENGTH('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS', 'INDICE_VARIACION_TOTAL_EMPRESA') IS NULL
    PRINT 'VERIFICACION - OMITIDA: la tabla todavia no tiene las columnas nuevas (correr el SP).';
ELSE
    EXEC sp_executesql N'
    SELECT
        COUNT(*) AS filas,
        SUM(CASE WHEN INDICE_VARIACION <>
                 CASE WHEN VTA_60D_ANT_LP = 0 THEN 0
                      ELSE CAST(VTA_60D_LP / CAST(VTA_60D_ANT_LP AS FLOAT) AS DECIMAL(10,2)) END
                 THEN 1 ELSE 0 END) AS filas_lp_que_no_cierran,
        SUM(CASE WHEN INDICE_VARIACION_TOTAL_EMPRESA <>
                 CASE WHEN VTA_60D_ANT_TE = 0 THEN 0
                      ELSE CAST(VTA_60D_TE / CAST(VTA_60D_ANT_TE AS FLOAT) AS DECIMAL(10,2)) END
                 THEN 1 ELSE 0 END) AS filas_te_que_no_cierran,
        CAST(SUM(VTA_60D_LP) / NULLIF(CAST(SUM(VTA_60D_ANT_LP) AS FLOAT), 0) AS DECIMAL(10,4)) AS indice_general_lp,
        CAST(SUM(VTA_60D_TE) / NULLIF(CAST(SUM(VTA_60D_ANT_TE) AS FLOAT), 0) AS DECIMAL(10,4)) AS indice_general_te,
        SUM(VTA_TEMPORADA_EN_CURSO) AS vendido_temporada_en_curso,
        MAX(ULT_ACTUALIZACION) AS ult_actualizacion
    FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS;';
GO
