
# Sistema de Presupuesto de Compras

## 📋 Descripción

Sistema web para gestionar y visualizar datos de presupuesto de compras basado en el procedimiento almacenado `RO_SP_VENTAS_PRESUPUESTO_COMPRAS`.

## 🏗️ Estructura del Proyecto

```
compra/
├── class/
│   ├── classEnv.php              # Envio de emails (si aplica)
│   └── conexion.php              # Conexión a base de datos
├── presupuestos/
│   ├── class/
│   │   ├── compras.php
│   │   ├── ExcelExporter.php
│   │   ├── presupuesto.php       # Clase modelo para interactuar con la BD
│   │   └── presupuestoCalculos.php
│   ├── components/
│   │   ├── tabs/
│   │   ├── alert-container.php
│   │   ├── header.php
│   │   ├── loading.php
│   │   ├── modals.php
│   │   ├── scripts.php
│   │   ├── styles.php
│   │   ├── tabs-container.php
│   │   └── temporada-info.php
│   ├── controller/
│   │   ├── BusquedaController.php
│   │   ├── ComprasController.php
│   │   ├── ExportacionController.php
│   │   ├── IndiceController.php
│   │   ├── MainController.php
│   │   └── ProcesadorDatos.php
│   ├── css/
│   │   ├── components.css
│   │   ├── dark-mode.css
│   │   ├── layout.css
│   │   ├── obtener-presupuesto.css
│   │   ├── tables.css
│   │   └── variables.css
│   ├── js/
│   │   ├── api-client.js
│   │   ├── busqueda-manager.js
│   │   ├── compras-manager.js
│   │   ├── indice-editor.js
│   │   ├── main.js
│   │   ├── tabla-renderer.js
│   │   └── utils.js
│   ├── api.php                    # API REST endpoints
│   ├── index.php                  # Interfaz principal
│   └── test_web.php               # Test de conexión y despliegue
├── .env                          # Configuración de entorno
└── README.md                  # Esta documentación
```

## 🚀 Instalación y Configuración

### 1. Requisitos Previos

* PHP 7.4 o superior
* SQL Server con extensión `sqlsrv`
* Servidor web (Apache/Nginx)
* Acceso a las bases de datos configuradas

### 2. Configuración del Entorno

Archivo `.env` con:

```env
HOST_APPS=tu_servidor_apps
DATABASE_APPS=tu_base_de_datos_apps
USER=tu_usuario
PASS=tu_contraseña
CHARACTER=UTF-8
ENV=PROD
```

### 3. Verificación

1. Acceder a `presupuestos/test_web.php` para verificar la conexión
2. Si todo está correcto, ingresar a `presupuestos/index.php`

## 🎯 Funcionalidades

* ✅ Ejecuta el SP y muestra todos los datos
* ✅ Estadísticas en tiempo real
* ✅ Filtrado por rubro
* ✅ Exportación CSV
* ✅ Visualización de temporadas
* ✅ Interfaz responsiva y adaptada a móviles
* ✅ **Guardado de Presupuestos**: Permite guardar un histórico de los presupuestos proyectados.

## 💻 API Endpoints

| Endpoint                            | Método | Descripción                             |
| ----------------------------------- | ------ | --------------------------------------- |
| `api.php?accion=presupuesto`        | GET    | Obtener todos los datos del presupuesto |
| `api.php?accion=conexion`           | GET    | Probar conexión a la base de datos      |
| `api.php?accion=temporadas`         | GET    | Obtener temporadas actuales             |
| `api.php?accion=estadisticas`       | GET    | Obtener estadísticas resumidas          |
| `api.php?accion=rubros`             | GET    | Obtener lista de rubros                 |
| `api.php?accion=rubro&rubro=NOMBRE` | GET    | Filtrar por rubro                       |
| `api.php?accion=guardar-presupuesto` | POST  | Guarda el presupuesto proyectado visible en la BD. |

## 💻 Uso del Sistema

### Interfaz Web

* "Cargar Presupuesto" ejecuta el SP
* "Ver Estadísticas" muestra el resumen
* Filtrar por rubro en dropdown
* Exportar como CSV

### API JavaScript

```js
fetch('presupuestos/api.php?accion=presupuesto')
  .then(r => r.json())
  .then(data => console.log(data));
```

## 🏠 Arquitectura MVC

* **Modelo**: `presupuesto.php`
* **Vista**: `index.php`, componentes
* **Controlador**: `PresupuestoController.php` y otros

### Flujo:

```
Frontend JS → API → Controller → Modelo → BD (SP)
        ↓                        ↑
    Vista (HTML/JSON) ← Respuesta
```

## 📊 Procedimiento Almacenado: Columnas

* `RUBRO`, `CATEGORIA_PADRE`, `CANT_STOCK`, `INDICE_VARIACION`
* Pendientes: `CANT_PEND_OC_VERANO`, `...INVIERNO`, `...ATEMPORAL`
* Columnas de temporadas: `VERANO 25`, `INVIERNO 25`, etc.

## 🎨 UI/UX

* Bootstrap 5, efectos hover, alertas animadas
* Scroll horizontal en tablas
* Accesibilidad y navegación por teclado

## 🔎 Troubleshooting

1. **Error conexión**: Revisar `.env` y `sqlsrv`
2. **SP no encontrado**: Revisar existencia en BD
3. **Datos vacíos**: Chequear `RO_TABLA_TEMPORADAS_ACTUALES`
4. **Errores JS**: Revisar consola y dependencias

```php
// Habilitar errores en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📊 Mejoras Futuras

* [ ] Cache
* [ ] Filtros avanzados
* [ ] Exportar a Excel
* [ ] Gráficos interactivos
* [ ] Notificaciones

## 👥 Soporte

* Revisar logs, consola navegador y configuración

---

**Versión**: 2.1
