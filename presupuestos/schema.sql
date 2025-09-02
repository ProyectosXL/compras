-- SQL script to create the table for storing projected purchase history
-- Table name: RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO
-- Version: 2.0 - Corrected data types

-- Drop the table if it already exists to apply changes
IF OBJECT_ID('RO.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO', 'U') IS NOT NULL
    DROP TABLE RO.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO;
GO

CREATE TABLE RO.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (
    id INT IDENTITY(1,1) PRIMARY KEY,
    nombre_presupuesto VARCHAR(255) NOT NULL,
    fecha_guardado SMALLDATETIME NOT NULL, -- Stored with minute precision
    temporada VARCHAR(50),
    pais VARCHAR(50),
    rubro VARCHAR(255),
    categoria_padre VARCHAR(255),

    -- Integer columns for quantities
    stock_proyectado INT,
    venta_verano_anterior INT,
    venta_proyectada_verano INT,
    venta_invierno_anterior INT,
    venta_proyectada_invierno INT,
    compra_proyectada INT,

    -- Decimal columns for indexes
    indice_variacion_original DECIMAL(18, 2),
    indice_verano_variacion DECIMAL(18, 2),
    indice_invierno_variacion DECIMAL(18, 2)
);
GO

-- Add an index for faster lookups by budget name and date
CREATE INDEX idx_nombre_fecha ON RO.RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (nombre_presupuesto, fecha_guardado);
GO
