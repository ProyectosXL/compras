/* =====================================================================
   Tabla de Mark-Up real mensual por temporada (mismo mes año anterior)
   Base: apps ($this->cid_sistemas en CostoProyeccion.php)

   Aisla INVIERNO y VERANO por separado y conserva los 6 meses individuales
   de la temporada anterior cerrada para respetar la estacionalidad mensual
   sin alterar FP_T_COSTOS_PARAMETROS (que sigue guardando vcosto y el
   mark-up base de respaldo).
   ===================================================================== */

IF OBJECT_ID('dbo.FP_T_COSTOS_MARKUP_MENSUAL', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.FP_T_COSTOS_MARKUP_MENSUAL (
        id                  INT IDENTITY(1,1) PRIMARY KEY,
        temporada           VARCHAR(20)     NOT NULL, -- 'INVIERNO' | 'VERANO'
        rubro               VARCHAR(100)    NOT NULL,
        categoria_padre     VARCHAR(100)    NOT NULL,
        canal               VARCHAR(50)     NOT NULL, -- 'LOCALES PROPIOS' | 'FRANQUICIAS' | 'MAYORISTAS' | 'ECOMMERCE'
        mes                 TINYINT         NOT NULL, -- 1..12
        anio_base           SMALLINT        NOT NULL,
        markup              DECIMAL(10, 4)  NOT NULL,
        origen              VARCHAR(30)     NOT NULL, -- 'CAT_MES' | 'RUBRO_MES' | 'CAT_TEMP' | 'BASE'
        unidades_base       INT             NOT NULL DEFAULT 0,
        venta_usd_base      DECIMAL(18, 2)  NOT NULL DEFAULT 0,
        fecha_actualizacion DATETIME        NOT NULL DEFAULT GETDATE(),
        CONSTRAINT UQ_FP_COSTOS_MK_MENSUAL UNIQUE (temporada, rubro, categoria_padre, canal, mes)
    );

    CREATE INDEX IX_FP_COSTOS_MK_MENSUAL_TEMP
        ON dbo.FP_T_COSTOS_MARKUP_MENSUAL (temporada, rubro, categoria_padre);
END
GO
