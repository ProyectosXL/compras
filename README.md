
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
│   │   ├── calculadora-invierno.js
│   │   ├── calculadora-verano.js
│   │   ├── compras-manager.js
│   │   ├── excel-exporter.js
│   │   ├── historial-manager.js
│   │   ├── indice-editor.js
│   │   ├── main.js
│   │   ├── tabla-renderer.js
│   │   ├── temporada-servidor.js  # Temporada/días que calcula el servidor
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
| `api.php?accion=buscar-historial`   | POST   | Busca en el historial de presupuestos guardados. |

Los endpoints de compra proyectada devuelven, además de `data`:

| Clave | Contenido |
| ----- | --------- |
| `columnas_venta` | Columnas históricas en orden cronológico |
| `etiquetas_historicas` | `{VTA_VERANO_26: "VER 25-26", ...}` |
| `periodos` | Qué período cubre cada columna proyectada en esa solapa, más la temporada objetivo |

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

El SP `RO_SP_VENTAS_PRESUPUESTO_COMPRAS` **materializa** la tabla
`RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS` (la dropea y la vuelve a crear). La aplicación
lee esa tabla, no ejecuta el SP.

* `RUBRO`, `CATEGORIA_PADRE`, `CANT_STOCK`, `CANT_STOCK_GUARDAR`, `INDICE_VARIACION`
* Pendientes de ingreso: `CANT_PEND_OC_VERANO`, `...INVIERNO`, `...ATEMPORAL`
* Cobertura: `STOCK_COBERTURA` (45 días de venta; 0 para `CALZADOS` y `CAMPERAS`)
* Columnas de temporadas: `VTA_VERANO_26`, `VTA_INVIERNO_26`, etc. Las define
  `PrepararDatosTemporadas` en `RO_TABLA_TEMPORADAS_ACTUALES`, con las últimas 3
  temporadas **completas** de cada tipo.

> Los nombres de columna del SP numeran el verano por el año en que **termina**
> (`VTA_VERANO_26` = 01/08/2025 al 31/01/2026). La aplicación no los toca: los traduce
> a la convención de pantalla (ver abajo). Renombrarlos rompería a cualquier consumidor.

## 🗓️ Convención de temporadas

Una sola convención en toda la aplicación: encabezado, columnas históricas, columnas
proyectadas, historial, Excel y ayuda.

| Temporada | Formato | Ejemplo | Período |
| --------- | ------- | ------- | ------- |
| Verano    | `VER AA-AA` | `VER 26-27` | 01/08/2026 al 31/01/2027 |
| Invierno  | `INV AA`    | `INV 27`    | 01/02/2027 al 31/07/2027 |

Coincide con los códigos de oleada de Comercio Exterior (`VER01-26`, `INV01-27`), que
numeran el verano por el año en que **arranca**.

Una columna que cubre solo los días que faltan de una temporada lo dice: `Resto VER 26-27`.
Si la etiqueta suma dos tramos (`Resto VER 26-27 + VER 27-28`), el valor de la celda es
la suma de los dos. El tooltip del encabezado muestra las fechas exactas.

**El cálculo de temporada vive en un solo lugar**: `PresupuestoCalculos` (PHP). El
navegador lo recibe en `info_temporada` y lo lee desde `js/temporada-servidor.js`; no
vuelve a deducir temporadas ni a contar días con su propio reloj.

## 🎯 Para qué temporada es la compra

`compra_proyectada = stock_proyectado − venta_proy_verano − venta_proy_invierno`

Las dos columnas proyectadas cubren juntas un período continuo desde hoy, así que la
compra es lo que falta para llegar al final de ese horizonte. Cada solapa compra para la
próxima temporada de su tipo:

| Solapa | Temporada en curso | Venta Proy. Verano | Venta Proy. Invierno | Temporada objetivo |
| ------ | ------------------ | ------------------ | -------------------- | ------------------ |
| Verano | `VER 26-27` | `Resto VER 26-27` + `VER 27-28` | `INV 27` | `VER 27-28` |
| Verano | `INV 27` | `VER 27-28` | `Resto INV 27` | `VER 27-28` |
| Invierno | `VER 26-27` | `Resto VER 26-27` | `INV 27` | `INV 27` |
| Invierno | `INV 27` | `VER 27-28` | `Resto INV 27` + `INV 28` | `INV 28` |

Signo: **resultado negativo = necesidad de compra**.

> ⚠️ `STOCK_PROYECTADO` **incluye las OC pendientes de ingreso**
> (`CANT_PEND_OC_VERANO` + `INVIERNO` + `ATEMPORAL`), así que la compra proyectada es
> **neta de lo ya pedido**: son las unidades que todavía hay que ordenar, no la compra
> total de la temporada.

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
