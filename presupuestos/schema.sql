-- SQL script to create the table for storing projected purchase history
-- Table name: RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO

CREATE TABLE RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (
    id INT IDENTITY(1,1) PRIMARY KEY,
    nombre_presupuesto VARCHAR(255) NOT NULL,
    fecha_guardado DATETIME NOT NULL,
    temporada VARCHAR(50),
    pais VARCHAR(50),
    rubro VARCHAR(255),
    categoria_padre VARCHAR(255),
    stock_proyectado DECIMAL(18, 2),
    indice_variacion_original DECIMAL(18, 2),
    indice_verano_variacion DECIMAL(18, 2),
    venta_verano_anterior DECIMAL(18, 2),
    venta_proyectada_verano DECIMAL(18, 2),
    indice_invierno_variacion DECIMAL(18, 2),
    venta_invierno_anterior DECIMAL(18, 2),
    venta_proyectada_invierno DECIMAL(18, 2),
    compra_proyectada DECIMAL(18, 2)
);

-- Add an index for faster lookups by budget name
CREATE INDEX idx_nombre_presupuesto ON RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO (nombre_presupuesto);
