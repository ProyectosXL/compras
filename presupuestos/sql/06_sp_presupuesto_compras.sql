/* =====================================================================
   Fase 5 - Indice Total Empresa en el SP del presupuesto de compras

   SOLO ARGENTINA: se corre UNICAMENTE en POWER_BI_CONTROL. Uruguay queda fuera
   de alcance; el script se niega a correr en cualquier otra base.

   BASE DEL SCRIPT
   Es el ALTER PROCEDURE COMPLETO de dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS, armado
   sobre la version del SP vigente en Argentina al 24/10/2026. Si el SP se
   modifico despues de esa fecha (hay un ajuste de stock pendiente), HAY QUE
   MERGEAR ese cambio aca antes de correrlo: este ALTER reemplaza el SP entero y
   pisaria cualquier cosa que no este en este archivo.

   QUE RESUELVE
   La venta proyectada se calcula como venta historica TOTAL EMPRESA (las columnas
   de temporada: LOCALES PROPIOS + CENTRAL) multiplicada por INDICE_VARIACION, que
   mide solo LOCALES PROPIOS. Indice y venta eran de universos distintos. Se agrega
   un indice del mismo universo que la venta y el de Locales Propios queda como
   termometro.

   QUE AGREGA (todas al FINAL de la tabla, despues de ULT_ACTUALIZACION, para no
   correr la posicion de ninguna columna existente)
     VTA_60D_LP                      la suma que hoy calcula VentasUltimos60Dias
     VTA_60D_ANT_LP                  la suma que hoy calcula VentasAnoAnterior
     VTA_60D_TE                      igual que VTA_60D_LP, LOCALES PROPIOS + CENTRAL
     VTA_60D_ANT_TE                  igual que VTA_60D_ANT_LP, LOCALES PROPIOS + CENTRAL
     INDICE_VARIACION_TOTAL_EMPRESA  VTA_60D_TE / VTA_60D_ANT_TE, misma regla que
                                     INDICE_VARIACION cuando el año anterior es 0
     VTA_TEMPORADA_EN_CURSO          venta real de la temporada en curso, desde su
                                     primer dia hasta ayer

   Las sumas TE y la temporada en curso usan EXACTAMENTE las exclusiones de la CTE
   Ventas (incluido el articulo XV00LV00A0001), para que indice y venta historica
   sean el mismo universo. Las sumas LP se copian tal cual estaban, sin agregarles
   esa exclusion: tienen que dar el mismo INDICE_VARIACION de siempre.

   QUE NO CAMBIA
   INDICE_VARIACION no cambia ni de nombre ni de calculo, y ninguna columna existente
   se renombra: hay consumidores fuera de esta aplicacion.

   TEMPORADA EN CURSO
   Se calcula con la misma regla que PresupuestoCalculos::obtenerTemporadaActual():
     mes >= 8  -> desde el 01/08 del año actual  (verano)
     mes =  1  -> desde el 01/08 del año anterior (verano que cierra en enero)
     mes 2..7  -> desde el 01/02 del año actual  (invierno)
   El primer dia de una temporada no hay nada vendido hasta ayer y da 0.

   COMO CORRERLO
   Viene con @CONFIRMAR_SP = 0: asi solo informa que haria. Poner en 1 y volver a
   ejecutar. Es reejecutable (ALTER). Despues hay que correr el SP una vez
   (EXEC dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS) o esperar la corrida programada, y
   recien ahi las columnas nuevas existen en la tabla. La verificacion del final
   es de solo lectura y se puede correr cuantas veces se quiera.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* =====================================================================
   PONER EN 1 PARA APLICAR
   ===================================================================== */
DECLARE @CONFIRMAR_SP BIT = 0;

PRINT '=== ' + DB_NAME() + ' ===';

/* La guarda va con NOEXEC porque un ALTER PROCEDURE tiene que ser la primera
   sentencia de su lote y no se puede envolver en un IF. NOEXEC sobrevive al GO:
   el lote siguiente se compila pero no se ejecuta. */
IF DB_NAME() <> N'POWER_BI_CONTROL'
BEGIN
    RAISERROR('Este script es solo para POWER_BI_CONTROL (Argentina). No se aplico nada.', 16, 1);
    SET NOEXEC ON;
END
ELSE IF @CONFIRMAR_SP = 0
BEGIN
    PRINT 'PENDIENTE: reemplazaria dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS por la version con';
    PRINT '           VTA_60D_LP/_ANT_LP/_TE/_ANT_TE, INDICE_VARIACION_TOTAL_EMPRESA y VTA_TEMPORADA_EN_CURSO.';
    PRINT '           Poner @CONFIRMAR_SP = 1 para aplicar.';
    SET NOEXEC ON;
END
GO

ALTER PROCEDURE dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS
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
              AND RUBRO NOT LIKE ''%OUTLET%''
              AND CATEGORIA != ''REMERA''
              AND FECHA_VENTA IS NOT NULL
              AND RUBRO IS NOT NULL
              AND CATEGORIA_PADRE IS NOT NULL
              AND ARTICULO != ''XV00LV00A0001''
            GROUP BY RUBRO, CATEGORIA_PADRE, T.TEMPORADA
        ),
        StockData AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(ISNULL(CANT_STOCK, 0)) AS CANT_STOCK
            FROM [LAKERBIS].LOCALES_LAKERS.DBO.RO_STOCK_LAKERS_RUBRO_CATEGORIA
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        ComprasData AS (
            SELECT RUBRO, CATEGORIA_PADRE,
                   SUM(ISNULL(CANT_PEND_OC_VERANO, 0)) AS CANT_PEND_OC_VERANO,
                   SUM(ISNULL(CANT_PEND_OC_INVIERNO, 0)) AS CANT_PEND_OC_INVIERNO,
                   SUM(ISNULL(CANT_PEND_OC_ATEMPORAL, 0)) AS CANT_PEND_OC_ATEMPORAL
            FROM [XL-TANGO].LAKER_SA.DBO.RO_V_COMPRAS_PEND_PRESUPUESTO_COMPRAS
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        StockGuardarData AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(ISNULL(CANT_STOCK_GUARDAR, 0)) AS CANT_STOCK_GUARDAR
            FROM [LAKERBIS].LOCALES_LAKERS.DBO.RO_STOCK_LAKERS_RUBRO_CATEGORIA_GUARDAR
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasUltimos60Dias AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_ULT_60_DIAS
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND RUBRO NOT LIKE ''%OUTLET%''
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
              AND RUBRO NOT LIKE ''%OUTLET%''
              AND CATEGORIA != ''REMERA''
              AND FECHA_VENTA >= DATEADD(YEAR, -1, DATEADD(DAY, -60, DATEADD(DAY, -1, CAST(GETDATE() AS DATE))))
              AND FECHA_VENTA <= DATEADD(YEAR, -1, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        -- Mismas ventanas que las dos anteriores, pero sobre el universo de la
        -- venta historica (CTE Ventas): LOCALES PROPIOS + CENTRAL y las mismas
        -- exclusiones, incluido el articulo XV00LV00A0001. Si el indice midiera
        -- otro universo que la venta a la que se aplica, volveria el problema que
        -- motiva este cambio.
        VentasUltimos60DiasTE AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_60D_TE
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND RUBRO NOT LIKE ''%OUTLET%''
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
              AND RUBRO NOT LIKE ''%OUTLET%''
              AND CATEGORIA != ''REMERA''
              AND ARTICULO != ''XV00LV00A0001''
              AND FECHA_VENTA >= DATEADD(YEAR, -1, DATEADD(DAY, -60, DATEADD(DAY, -1, CAST(GETDATE() AS DATE))))
              AND FECHA_VENTA <= DATEADD(YEAR, -1, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        -- Venta real de la temporada en curso, desde su primer dia hasta ayer.
        -- Mismo universo que las columnas historicas: la pantalla la suma con la
        -- venta proyectada del resto de la temporada, y las dos tienen que medir
        -- lo mismo para que el total tenga sentido.
        VentasTemporadaEnCurso AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_TEMPORADA_EN_CURSO
            FROM RO_VENTAS_COMERCIAL
            WHERE CANAL IN (''LOCALES PROPIOS'', ''CENTRAL'')
              AND RUBRO NOT IN (''PACKAGING'', ''_KITS'')
              AND RUBRO NOT LIKE ''%OUTLET%''
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
              AND RUBRO NOT LIKE ''%OUTLET%''
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
            COALESCE(PivotTable.RUBRO, '''') AS RUBRO,
            COALESCE(PivotTable.CATEGORIA_PADRE, '''') AS CATEGORIA_PADRE' +
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
                WHEN PivotTable.RUBRO IN (''CALZADOS'',''CAMPERAS'') THEN 0
                WHEN ISNULL(V360.VTA_ULT_360_DIAS, 0) = 0 THEN 0
                ELSE CAST(ROUND((ISNULL(V360.VTA_ULT_360_DIAS, 0) / 360.0) * 45.0, 0) AS INT)
            END AS STOCK_COBERTURA,
            GETDATE() AS ULT_ACTUALIZACION,
            -- Columnas nuevas, siempre al final: agregarlas en el medio correria la
            -- posicion de las existentes para cualquier lector que las tome por orden.
            ISNULL(VU.VTA_ULT_60_DIAS, 0) AS VTA_60D_LP,
            ISNULL(VA.VTA_ULT_60_DIAS_ANO_ANT, 0) AS VTA_60D_ANT_LP,
            ISNULL(VTE.VTA_60D_TE, 0) AS VTA_60D_TE,
            ISNULL(VATE.VTA_60D_ANT_TE, 0) AS VTA_60D_ANT_TE,
            -- Misma regla que INDICE_VARIACION cuando el año anterior es 0: devuelve 0
            -- y la aplicacion lo trata como 1,00. Se copia la regla y no se "mejora"
            -- para que los dos indices se lean igual uno al lado del otro.
            CASE
            WHEN ISNULL(VATE.VTA_60D_ANT_TE, 0) = 0 THEN
                    CASE WHEN ISNULL(VTE.VTA_60D_TE, 0) > 0 THEN 0 ELSE 0.00 END
                ELSE
                    CAST(ISNULL(VTE.VTA_60D_TE, 0) / NULLIF(CAST(VATE.VTA_60D_ANT_TE AS FLOAT), 0) AS DECIMAL(10,2))
            END AS INDICE_VARIACION_TOTAL_EMPRESA,
            ISNULL(VTC.VTA_TEMPORADA_EN_CURSO, 0) AS VTA_TEMPORADA_EN_CURSO
        INTO RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS
        FROM PivotData
        PIVOT (
          SUM(CANT_VEND)
            FOR TEMPORADA IN (' + @ColumnasPivot + N')
        ) AS PivotTable
        LEFT JOIN StockData S ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = S.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = S.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN ComprasData C ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = C.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = C.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN StockGuardarData SG ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = SG.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = SG.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN VentasUltimos60Dias VU ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = VU.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = VU.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN VentasAnoAnterior VA ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = VA.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = VA.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN VentasUltimos60DiasTE VTE ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = VTE.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = VTE.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN VentasAnoAnteriorTE VATE ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = VATE.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = VATE.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN VentasTemporadaEnCurso VTC ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = VTC.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = VTC.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        LEFT JOIN Ventas360Dias V360 ON PivotTable.RUBRO COLLATE DATABASE_DEFAULT = V360.RUBRO COLLATE DATABASE_DEFAULT
            AND PivotTable.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT = V360.CATEGORIA_PADRE COLLATE DATABASE_DEFAULT
        ORDER BY PivotTable.RUBRO, PivotTable.CATEGORIA_PADRE;';

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

   Correr DESPUES de ejecutar el SP. Si la tabla todavia no tiene las columnas
   nuevas, lo informa y no hace nada.
     1. INDICE_VARIACION tiene que seguir saliendo de las mismas sumas LP: la
        cantidad de filas que no cierran tiene que dar 0.
     2. Lo mismo para el indice TE contra sus propias sumas.
     3. El indice general de cada universo, como referencia.
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
