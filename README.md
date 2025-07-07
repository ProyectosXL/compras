
# Sistema de Presupuesto de Compras

## 📋 Descripción
Sistema web para gestionar y visualizar datos de presupuesto de compras basado en el procedimiento almacenado `RO_SP_VENTAS_PRESUPUESTO_COMPRAS`.

## 🏗️ Estructura del Proyecto

```
presupuestos/
├── class/
│   └── presupuesto.php          # Clase modelo para interactuar con la BD
├── controller/
│   └── PresupuestoController.php # Controlador con lógica de negocio
├── css/
│   └── obtener-presupuesto.css  # Estilos personalizados
├── js/
│   └── obtener-presupuesto.js   # JavaScript del frontend
├── api.php                      # API REST endpoints
├── index.php                    # Interfaz principal
├── test_web.php                 # Página de pruebas
└── README.md                    # Esta documentación
```

## 🚀 Instalación y Configuración

### 1. Requisitos Previos
- PHP 7.4 o superior
- SQL Server con extensión `sqlsrv`
- Servidor web (Apache/Nginx)
- Acceso a las bases de datos configuradas

### 2. Configuración del Entorno
Asegúrate de que tu archivo `.env` en la raíz del proyecto tenga configuradas las variables:

```env
HOST_APPS=tu_servidor_apps
DATABASE_APPS=tu_base_de_datos_apps
USER=tu_usuario
PASS=tu_contraseña
CHARACTER=UTF-8
ENV=PROD
```

### 3. Verificación de la Instalación
1. Accede a `presupuestos/test_web.php` para verificar la conexión
2. Si todo funciona correctamente, accede a `presupuestos/index.php`

## 🎯 Funcionalidades

### Principales Características:
- ✅ **Carga de Presupuesto**: Ejecuta el SP y muestra todos los datos
- ✅ **Estadísticas en Tiempo Real**: Resumen de registros, rubros, stock
- ✅ **Filtrado por Rubro**: Filtra datos por rubro específico
- ✅ **Exportación CSV**: Descarga datos en formato CSV
- ✅ **Prueba de Conexión**: Verifica conectividad con la base de datos
- ✅ **Visualización de Temporadas**: Muestra temporadas configuradas
- ✅ **Interfaz Responsiva**: Compatible con dispositivos móviles

### API Endpoints:

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `api.php?accion=presupuesto` | GET | Obtener todos los datos del presupuesto |
| `api.php?accion=conexion` | GET | Probar conexión a la base de datos |
| `api.php?accion=temporadas` | GET | Obtener temporadas actuales |
| `api.php?accion=estadisticas` | GET | Obtener estadísticas resumidas |
| `api.php?accion=rubros` | GET | Obtener lista de rubros únicos |
| `api.php?accion=rubro&rubro=NOMBRE` | GET | Filtrar por rubro específico |

## 💻 Uso del Sistema

### Interfaz Web
1. **Cargar Presupuesto**: Haz clic en "Cargar Presupuesto" para obtener todos los datos
2. **Ver Estadísticas**: Usa "Estadísticas" para ver un resumen de los datos
3. **Filtrar**: Selecciona un rubro del dropdown y aplica el filtro
4. **Exportar**: Descarga los datos en formato CSV
5. **Monitoreo**: El indicador de conexión muestra el estado en tiempo real

### Uso de la API
```javascript
// Ejemplo: Obtener presupuesto completo
fetch('presupuestos/api.php?accion=presupuesto')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Datos:', data.data);
        }
    });

// Ejemplo: Filtrar por rubro
fetch('presupuestos/api.php?accion=rubro&rubro=CALZADO')
    .then(response => response.json())
    .then(data => console.log(data));
```

## 🏛️ Arquitectura

### Patrón MVC Implementado:
- **Model** (`class/presupuesto.php`): Maneja la conexión y consultas a la BD
- **View** (`index.php`): Interfaz de usuario con Bootstrap 5
- **Controller** (`controller/PresupuestoController.php`): Lógica de negocio y API

### Flujo de Datos:
```
Frontend (JS) → API (api.php) → Controller → Model → Database → SP
                                    ↓
Frontend ← JSON Response ← Controller ← Model ← Database Results
```

## 🔧 Clases y Métodos Principales

### Clase `Presupuesto`
- `obtenerPresupuestoCompras()`: Ejecuta el procedimiento almacenado principal
- `probarConexion()`: Verifica la conectividad
- `obtenerTemporadasActuales()`: Obtiene temporadas configuradas
- `getArray($sql)`: Método genérico para consultas

### Clase `PresupuestoController`
- `obtenerPresupuesto()`: Endpoint para datos completos
- `obtenerEstadisticas()`: Calcula estadísticas de los datos
- `obtenerPorRubro($rubro)`: Filtra datos por rubro
- `procesarSolicitud()`: Enrutador principal de la API

## 📊 Datos del Procedimiento Almacenado

### Columnas Principales:
- `RUBRO`: Categoría principal del producto
- `CATEGORIA_PADRE`: Subcategoría
- `CANT_STOCK`: Cantidad en stock actual
- `CANT_STOCK_GUARDAR`: Stock reservado
- `INDICE_VARIACION`: Índice de variación vs año anterior
- `CANT_PEND_OC_VERANO/INVIERNO/ATEMPORAL`: Compras pendientes por temporada
- Columnas dinámicas de temporadas (ej: `VERANO 25`, `INVIERNO 25`)

### Estadísticas Calculadas:
- Total de registros
- Rubros únicos
- Stock total
- Compras pendientes por temporada
- Top 5 rubros por stock

## 🎨 Características de UI/UX

### Diseño Responsivo:
- ✅ Bootstrap 5 con componentes modernos
- ✅ Cards con efectos hover
- ✅ Alertas animadas
- ✅ Indicadores de estado en tiempo real
- ✅ Tablas con scroll horizontal en móviles

### Accesibilidad:
- ✅ Contraste de colores adecuado
- ✅ Navegación por teclado
- ✅ Indicadores visuales claros
- ✅ Textos descriptivos

## 🔍 Troubleshooting

### Problemas Comunes:

1. **Error de Conexión**:
   - Verificar variables del `.env`
   - Comprobar extensión `sqlsrv` de PHP
   - Validar permisos de usuario en SQL Server

2. **Procedimiento no Encontrado**:
   - Verificar que `RO_SP_VENTAS_PRESUPUESTO_COMPRAS` existe en la BD
   - Comprobar permisos de ejecución

3. **Datos Vacíos**:
   - Verificar tabla `RO_TABLA_TEMPORADAS_ACTUALES`
   - Comprobar datos en `RO_VENTAS_COMERCIAL`

4. **Errores de JavaScript**:
   - Verificar consola del navegador
   - Comprobar que Bootstrap y FontAwesome cargan correctamente

### Logs y Debug:
```php
// Habilitar logs de error en PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Los errores se registran automáticamente en error_log
```

## 📈 Próximas Mejoras

- [ ] Sistema de cache para mejorar performance
- [ ] Filtros adicionales (categoría, rango de fechas)
- [ ] Gráficos y visualizaciones
- [ ] Exportación a Excel
- [ ] Sistema de notificaciones
- [ ] Modo offline con localStorage

## 👥 Soporte

Para soporte técnico o reportar bugs, revisar:
1. Logs de PHP y servidor web
2. Consola del navegador
3. Conexión a base de datos
4. Permisos del procedimiento almacenado

---

**Versión**: 1