/* =====================================================================
   dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS
   Base de datos: POWER_BI_CONTROL (Argentina) - Servidor XL-APPS1

   SOLO ARGENTINA. Reemplaza al 06 (que queda como historial). Requiere el 08
   (parametros): si sus tablas no estan, el SP usa los valores de siempre.

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
   - 12/11/2026: (script 10)
                 * Exclusiones y canales salen de RO_T_PC_PARAMETROS_EXCLUSIONES
                   (pantalla Parametros): rubros, categorias, articulos, canales del
                   calculo y canales del indice Locales Propios.
                 * La ventana del indice de variacion sale de RO_T_PC_PARAMETROS
                   (VARIACION_VENTANA_DIAS, 60 por defecto).
                 * Se deja de calcular STOCK_COBERTURA (venta de 360 dias / 360 x 45)
                   y se saca Ventas360Dias: la cobertura la calcula la aplicacion
                   (PresupuestoCalculos::calcularCobertura) a partir de la venta
                   proyectada y de VTA_TEMPORADA_EN_CURSO, que se mantiene.
                 * La regla de outlets queda FIJA (ver comentario en el SQL).

   COMO SE APLICAN LAS LISTAS (para que con los valores iniciales de igual que antes)
   Cada lista se aplica EXACTAMENTE donde el 06 tenia el valor escrito, aunque eso
   no sea uniforme entre CTEs:
     - ARTICULO excluido: solo en Ventas, indice TE (60 dias y año anterior) y venta
       de la temporada en curso. El indice LP nunca lo excluyo y sigue sin hacerlo.
     - CATEGORIA excluida: en las ventas filtra la columna CATEGORIA; en stock, OC y
       stock a guardar filtra CATEGORIA_PADRE. Asi estaba REMERA.
     - CANAL_VENTA: Ventas, indice TE y temporada en curso. CANAL_INDICE_LP: indice LP.
   Ventana: el filtro va de (ayer - N dias) a ayer inclusive, o sea N+1 dias. Con
   N = 60 es exactamente lo de antes; no se corrige para no mover los indices.

   Las listas entran al SQL dinamico como literales ('PACKAGING', '_KITS'), igual que
   estaban escritas: asi la comparacion usa la misma colacion de cada columna (los
   servidores vinculados son Latin1_General_BIN) y el filtro se sigue resolviendo
   del lado remoto. Una subconsulta contra una tabla local habria cambiado las dos cosas.

   COMO CORRERLO (verificacion contra el resultado de hoy)
     1. Con el SP vigente (06), correrlo una vez:  EXEC dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS
     2. Poner @CONFIRMAR_COPIA = 1 y correr este script: copia la tabla actual a
        RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10 (no aplica el SP todavia si
        @CONFIRMAR_SP sigue en 0).
     3. Poner @CONFIRMAR_SP = 1 y volver a correr: aplica el SP nuevo.
     4. Correr el SP nuevo:  EXEC dbo.RO_SP_VENTAS_PRESUPUESTO_COMPRAS
     5. Volver a correr este script (o solo el bloque VERIFICACION): compara.
   Hacer 1 a 5 seguidos: las ventas son hasta ayer y no cambian en el dia, pero el
   stock y las OC salen de servidores vinculados en vivo y pueden moverse.
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

DECLARE @CONFIRMAR_COPIA BIT = 0;   -- copia de la tabla actual para comparar
DECLARE @CONFIRMAR_SP    BIT = 0;   -- CREATE OR ALTER del SP

PRINT '=== ' + DB_NAME() + ' ===';

IF DB_NAME() <> N'POWER_BI_CONTROL'
BEGIN
    RAISERROR('Este script es solo para POWER_BI_CONTROL (Argentina). No se aplico nada.', 16, 1);
    SET NOEXEC ON;
END
ELSE
BEGIN
    IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES', 'U') IS NULL
        PRINT 'AVISO: no estan las tablas del script 08. El SP va a usar los valores de siempre.';

    /* Copia para la verificacion. No se pisa una copia existente: si se quiere
       rehacer, borrarla a mano (DROP TABLE dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10). */
    IF OBJECT_ID('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10', 'U') IS NOT NULL
        PRINT 'COPIA - OMITIDA: ya existe RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10.';
    ELSE IF @CONFIRMAR_COPIA = 0
        PRINT 'COPIA - PENDIENTE: poner @CONFIRMAR_COPIA = 1 (despues de correr el SP vigente).';
    ELSE
    BEGIN
        SELECT * INTO dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10 FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS;
        PRINT 'COPIA - OK: ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' filas copiadas.';
    END

    IF @CONFIRMAR_SP = 0
    BEGIN
        PRINT 'SP - PENDIENTE: poner @CONFIRMAR_SP = 1 para aplicar el CREATE OR ALTER PROCEDURE.';
        SET NOEXEC ON;
    END
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

        /* ---------------------------------------------------------------
           Parametros (script 08), con los valores de siempre si faltan.
           --------------------------------------------------------------- */
        DECLARE @DiasVentana INT = 60;
        IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS', 'U') IS NOT NULL
            SELECT @DiasVentana = TRY_CAST(valor AS INT)
            FROM dbo.RO_T_PC_PARAMETROS WHERE clave = 'VARIACION_VENTANA_DIAS';
        IF @DiasVentana IS NULL OR @DiasVentana < 1 SET @DiasVentana = 60;

        DECLARE @Listas TABLE (tipo VARCHAR(30), valor NVARCHAR(100));
        DECLARE @Defectos TABLE (tipo VARCHAR(30), valor NVARCHAR(100));
        INSERT INTO @Defectos VALUES
            ('RUBRO', N'PACKAGING'), ('RUBRO', N'_KITS'), ('CATEGORIA', N'REMERA'),
            ('ARTICULO', N'XV00LV00A0001'), ('CANAL_VENTA', N'LOCALES PROPIOS'),
            ('CANAL_VENTA', N'CENTRAL'), ('CANAL_INDICE_LP', N'LOCALES PROPIOS');

        -- Las dos ramas van separadas: una sentencia que nombra la tabla de parametros
        -- no compila si la tabla no existe, aunque la condicion la saltee.
        IF OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES', 'U') IS NULL
            INSERT INTO @Listas (tipo, valor) SELECT tipo, valor FROM @Defectos;
        ELSE
        BEGIN
            INSERT INTO @Listas (tipo, valor)
            SELECT tipo, valor FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES WHERE activo = 1;
            -- Una lista que no aparece en la tabla (ni activa ni dada de baja) vale lo
            -- de siempre. Una lista con todos sus valores dados de baja queda vacia.
            INSERT INTO @Listas (tipo, valor)
            SELECT d.tipo, d.valor FROM @Defectos d
            WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES e WHERE e.tipo = d.tipo);
        END

        -- Cada lista como literales separados por coma: 'PACKAGING', '_KITS'
        DECLARE @Rubros NVARCHAR(MAX), @Categorias NVARCHAR(MAX), @Articulos NVARCHAR(MAX),
                @CanalesVenta NVARCHAR(MAX), @CanalesLP NVARCHAR(MAX);
        SELECT @Rubros = STUFF((SELECT ', ' + QUOTENAME(valor, '''') FROM @Listas WHERE tipo = 'RUBRO' ORDER BY valor
                                FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');
        SELECT @Categorias = STUFF((SELECT ', ' + QUOTENAME(valor, '''') FROM @Listas WHERE tipo = 'CATEGORIA' ORDER BY valor
                                    FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');
        SELECT @Articulos = STUFF((SELECT ', ' + QUOTENAME(valor, '''') FROM @Listas WHERE tipo = 'ARTICULO' ORDER BY valor
                                   FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');
        SELECT @CanalesVenta = STUFF((SELECT ', ' + QUOTENAME(valor, '''') FROM @Listas WHERE tipo = 'CANAL_VENTA' ORDER BY valor
                                      FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');
        SELECT @CanalesLP = STUFF((SELECT ', ' + QUOTENAME(valor, '''') FROM @Listas WHERE tipo = 'CANAL_INDICE_LP' ORDER BY valor
                                   FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

        -- Sin canales no hay venta que sumar: se corta antes de dejar la tabla vacia.
        IF @CanalesVenta IS NULL OR @CanalesLP IS NULL
        BEGIN
            RAISERROR('Parametros: las listas de canales no pueden quedar vacias.', 16, 1);
            RETURN;
        END

        -- Condiciones armadas. Una lista de exclusion vacia no excluye nada (1 = 1).
        DECLARE @FiltroRubro NVARCHAR(MAX)     = CASE WHEN @Rubros IS NULL THEN N'1 = 1' ELSE N'RUBRO NOT IN (' + @Rubros + N')' END;
        DECLARE @FiltroCategoria NVARCHAR(MAX) = CASE WHEN @Categorias IS NULL THEN N'1 = 1' ELSE N'CATEGORIA NOT IN (' + @Categorias + N')' END;
        DECLARE @FiltroCatPadre NVARCHAR(MAX)  = CASE WHEN @Categorias IS NULL THEN N'1 = 1' ELSE N'CATEGORIA_PADRE NOT IN (' + @Categorias + N')' END;
        DECLARE @FiltroArticulo NVARCHAR(MAX)  = CASE WHEN @Articulos IS NULL THEN N'1 = 1' ELSE N'ARTICULO NOT IN (' + @Articulos + N')' END;
        DECLARE @FiltroCanal NVARCHAR(MAX)     = N'CANAL IN (' + @CanalesVenta + N')';
        DECLARE @FiltroCanalLP NVARCHAR(MAX)   = N'CANAL IN (' + @CanalesLP + N')';

        /* Regla de outlets: FIJA, no se parametriza. Se excluyen los rubros con OUTLET
           salvo SINTETICOS OUTLET, y de ese solo los articulos que empiezan con O y no
           con OU (los OU son otra linea). En stock, OC y stock a guardar no hay
           articulo, asi que ahi entra SINTETICOS OUTLET completo. */
        DECLARE @OutletVentas NVARCHAR(MAX) = N'(RUBRO NOT LIKE ''%OUTLET%''
                   OR (RUBRO = ''SINTETICOS OUTLET''
                       AND ARTICULO LIKE ''O%''
                       AND ARTICULO NOT LIKE ''OU%''))';
        DECLARE @OutletStock NVARCHAR(MAX) = N'(RUBRO NOT LIKE ''%OUTLET%'' OR RUBRO = ''SINTETICOS OUTLET'')';

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
            WHERE ' + @FiltroCanal + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletVentas + N'
              AND ' + @FiltroCategoria + N'
              AND FECHA_VENTA IS NOT NULL
              AND RUBRO IS NOT NULL
              AND CATEGORIA_PADRE IS NOT NULL
              AND ' + @FiltroArticulo + N'
            GROUP BY RUBRO, CATEGORIA_PADRE, T.TEMPORADA
        ),
        StockData AS (
            SELECT CAST(RUBRO AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS RUBRO,
                   CAST(CATEGORIA_PADRE AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS CATEGORIA_PADRE,
                   SUM(ISNULL(CANT_STOCK, 0)) AS CANT_STOCK
            FROM [LAKERBIS].LOCALES_LAKERS.DBO.RO_STOCK_LAKERS_RUBRO_CATEGORIA
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
              AND ' + @FiltroCatPadre + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletStock + N'
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
              AND ' + @FiltroCatPadre + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletStock + N'
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        StockGuardarData AS (
            SELECT CAST(RUBRO AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS RUBRO,
                   CAST(CATEGORIA_PADRE AS VARCHAR(100)) COLLATE Modern_Spanish_CI_AS AS CATEGORIA_PADRE,
                   SUM(ISNULL(CANT_STOCK_GUARDAR, 0)) AS CANT_STOCK_GUARDAR
            FROM [LAKERBIS].LOCALES_LAKERS.DBO.RO_STOCK_LAKERS_RUBRO_CATEGORIA_GUARDAR
            WHERE RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
              AND ' + @FiltroCatPadre + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletStock + N'
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
        -- Indice Locales Propios: sin exclusion de articulo (como en el 06).
        VentasUltimos60Dias AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_ULT_60_DIAS
            FROM RO_VENTAS_COMERCIAL
            WHERE ' + @FiltroCanalLP + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletVentas + N'
              AND ' + @FiltroCategoria + N'
              AND FECHA_VENTA >= DATEADD(DAY, -@DiasVentana, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND FECHA_VENTA <= DATEADD(DAY, -1, CAST(GETDATE() AS DATE))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasAnoAnterior AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_ULT_60_DIAS_ANO_ANT
            FROM RO_VENTAS_COMERCIAL
            WHERE ' + @FiltroCanalLP + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletVentas + N'
              AND ' + @FiltroCategoria + N'
              AND FECHA_VENTA >= DATEADD(YEAR, -1, DATEADD(DAY, -@DiasVentana, DATEADD(DAY, -1, CAST(GETDATE() AS DATE))))
              AND FECHA_VENTA <= DATEADD(YEAR, -1, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasUltimos60DiasTE AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_60D_TE
            FROM RO_VENTAS_COMERCIAL
            WHERE ' + @FiltroCanal + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletVentas + N'
              AND ' + @FiltroCategoria + N'
              AND ' + @FiltroArticulo + N'
              AND FECHA_VENTA >= DATEADD(DAY, -@DiasVentana, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND FECHA_VENTA <= DATEADD(DAY, -1, CAST(GETDATE() AS DATE))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasAnoAnteriorTE AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_60D_ANT_TE
            FROM RO_VENTAS_COMERCIAL
            WHERE ' + @FiltroCanal + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletVentas + N'
              AND ' + @FiltroCategoria + N'
              AND ' + @FiltroArticulo + N'
              AND FECHA_VENTA >= DATEADD(YEAR, -1, DATEADD(DAY, -@DiasVentana, DATEADD(DAY, -1, CAST(GETDATE() AS DATE))))
              AND FECHA_VENTA <= DATEADD(YEAR, -1, DATEADD(DAY, -1, CAST(GETDATE() AS DATE)))
              AND RUBRO IS NOT NULL AND CATEGORIA_PADRE IS NOT NULL
            GROUP BY RUBRO, CATEGORIA_PADRE
        ),
        VentasTemporadaEnCurso AS (
            SELECT RUBRO, CATEGORIA_PADRE, SUM(CANT_VEND) AS VTA_TEMPORADA_EN_CURSO
            FROM RO_VENTAS_COMERCIAL
            WHERE ' + @FiltroCanal + N'
              AND ' + @FiltroRubro + N'
              AND ' + @OutletVentas + N'
              AND ' + @FiltroCategoria + N'
              AND ' + @FiltroArticulo + N'
              AND FECHA_VENTA >= @InicioTemporadaEnCurso
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
            -- STOCK_COBERTURA ya no sale de aca: la calcula la aplicacion (script 10).
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
        ORDER BY U.RUBRO, U.CATEGORIA_PADRE;';

        -- Fecha de inicio y ventana entran como parametros y no concatenadas: el texto
        -- del SQL dinamico no depende de la configuracion regional de fechas.
        EXEC sp_executesql @SQL,
             N'@InicioTemporadaEnCurso DATE, @DiasVentana INT',
             @InicioTemporadaEnCurso = @InicioTemporadaEnCurso,
             @DiasVentana = @DiasVentana;

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

   Compara la tabla que genero el SP nuevo contra la copia tomada con el SP
   anterior (RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10), fila por fila
   (RUBRO + CATEGORIA_PADRE) y columna por columna, en todas las columnas que
   tienen las dos salvo ULT_ACTUALIZACION y STOCK_COBERTURA (que el SP nuevo ya
   no trae a proposito).

   Con los parametros iniciales tienen que dar 0 todas las columnas de venta e
   indices (VTA_*, INDICE_*). En CANT_STOCK / CANT_STOCK_GUARDAR / CANT_PEND_OC_*
   puede haber diferencias si entre una corrida y la otra se movio el stock o las
   OC en los servidores vinculados: no dependen de ningun parametro.
   ===================================================================== */
IF OBJECT_ID('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10', 'U') IS NULL
    PRINT 'VERIFICACION - OMITIDA: falta la copia (ver pasos 1 y 2 del encabezado).';
ELSE IF COL_LENGTH('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS', 'STOCK_COBERTURA') IS NOT NULL
    PRINT 'VERIFICACION - OMITIDA: la tabla todavia es la del SP anterior (correr el SP nuevo).';
ELSE
BEGIN
    DECLARE @cols NVARCHAR(MAX), @sumas NVARCHAR(MAX), @sqlv NVARCHAR(MAX);

    SELECT @sumas = STUFF((
        SELECT ', SUM(CASE WHEN ISNULL(CAST(n.' + QUOTENAME(c.name) + ' AS SQL_VARIANT), '''') <> ISNULL(CAST(v.' + QUOTENAME(c.name) + ' AS SQL_VARIANT), '''') THEN 1 ELSE 0 END) AS ' + QUOTENAME(c.name)
        FROM sys.columns c
        WHERE c.object_id = OBJECT_ID('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS')
          AND c.name NOT IN ('RUBRO', 'CATEGORIA_PADRE', 'ULT_ACTUALIZACION', 'STOCK_COBERTURA')
          AND EXISTS (SELECT 1 FROM sys.columns b
                      WHERE b.object_id = OBJECT_ID('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10') AND b.name = c.name)
        ORDER BY c.column_id
        FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

    -- Una fila por columna con la cantidad de filas distintas: se lee de un vistazo.
    SET @sqlv = N'
    SELECT u.columna, u.filas_distintas,
           CASE WHEN u.columna LIKE ''VTA[_]%'' OR u.columna LIKE ''INDICE[_]%'' THEN ''venta / indice: tiene que dar 0''
                ELSE ''stock / OC: puede moverse entre corridas'' END AS que_esperar
    FROM (
        SELECT ' + @sumas + N'
        FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS n
        JOIN dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10 v
          ON v.RUBRO = n.RUBRO AND v.CATEGORIA_PADRE = n.CATEGORIA_PADRE
    ) t
    UNPIVOT (filas_distintas FOR columna IN (' + (
        SELECT STUFF((SELECT ', ' + QUOTENAME(c.name)
                      FROM sys.columns c
                      WHERE c.object_id = OBJECT_ID('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS')
                        AND c.name NOT IN ('RUBRO', 'CATEGORIA_PADRE', 'ULT_ACTUALIZACION', 'STOCK_COBERTURA')
                        AND EXISTS (SELECT 1 FROM sys.columns b
                                    WHERE b.object_id = OBJECT_ID('dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10') AND b.name = c.name)
                      ORDER BY c.column_id
                      FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, '')) + N')) u
    ORDER BY CASE WHEN u.filas_distintas > 0 THEN 0 ELSE 1 END, u.columna;';
    EXEC sp_executesql @sqlv;

    -- Filas que estan en una sola de las dos tablas (con los parametros iniciales: 0 y 0).
    SELECT
        (SELECT COUNT(*) FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS n
          WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10 v
                            WHERE v.RUBRO = n.RUBRO AND v.CATEGORIA_PADRE = n.CATEGORIA_PADRE)) AS filas_solo_en_nueva,
        (SELECT COUNT(*) FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10 v
          WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS n
                            WHERE v.RUBRO = n.RUBRO AND v.CATEGORIA_PADRE = n.CATEGORIA_PADRE)) AS filas_solo_en_copia,
        (SELECT COUNT(*) FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS) AS filas_nueva,
        (SELECT COUNT(*) FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10) AS filas_copia,
        (SELECT MAX(ULT_ACTUALIZACION) FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS) AS corrida_nueva,
        (SELECT MAX(ULT_ACTUALIZACION) FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS_BK_PRE10) AS corrida_copia;
END
GO
