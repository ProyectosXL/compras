
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
│   ├── sql/
│   │   ├── 01_cabecera_versiones.sql      # DDL de la cabecera de versiones
│   │   └── 02_migracion_versiones.php     # Migración de las versiones existentes
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

### 4. Scripts de base de datos

En `presupuestos/sql/`, en orden. Hay que correrlos **en las dos bases**:
`POWER_BI_CONTROL` (Argentina) y `POWER_BI_CONTROL_URUGUAY` (Uruguay).

```
01_cabecera_versiones.sql     Crea la cabecera de versiones, el log de oficial y
                              las columnas nuevas del detalle.
                              Los 4 bloques vienen en @CONFIRMAR_* = 0: así solo
                              informan qué harían. Poner en 1 para aplicar.

02_migracion_versiones.php    Migra las versiones ya guardadas a la cabecera.
                              php 02_migracion_versiones.php                 -> preview
                              php 02_migracion_versiones.php --aplicar       -> escribe
                              php 02_migracion_versiones.php --pais=uruguay

03_compra_por_tramo.sql       Crea la tabla de tramos (compra abierta por temporada)
                              y agrega tramos_estado a la cabecera.
                              Los 3 bloques vienen en @CONFIRMAR_* = 0.

04_migracion_tramos.php       Reconstruye el reparto por tramo de las versiones
                              que ya tienen cabecera. Requiere el 03 aplicado.
                              php 04_migracion_tramos.php                 -> preview
                              php 04_migracion_tramos.php --aplicar       -> escribe
                              php 04_migracion_tramos.php --pais=uruguay

05_baja_logica_versiones.sql  Baja lógica: eliminar una versión deja de borrarla.
                              Los 4 bloques vienen en @CONFIRMAR_* = 0.
                              El bloque 4 (la vista para el consumidor externo)
                              es OPCIONAL y se puede dejar para después.
```

Los cinco son **reejecutables** y ninguno borra datos. La aplicación funciona con o sin
ellos aplicados: mientras falten, el panel de versiones avisa que hay que correrlos y
el resto sigue andando igual (sin el 03, se guarda y se muestra todo salvo el reparto
por tramo; sin el 05, eliminar una versión sigue borrándola físicamente).

El `04` **no inventa nada**: solo reconstruye las versiones cuyo reparto se puede
reproducir exacto, y verifica fila por fila antes de escribir. La que no cierra queda
marcada `SIN_REPARTO` con el motivo.

## 🎯 Funcionalidades

* ✅ Ejecuta el SP y muestra todos los datos
* ✅ Estadísticas en tiempo real
* ✅ Filtrado por rubro
* ✅ Exportación CSV
* ✅ Visualización de temporadas
* ✅ Interfaz responsiva y adaptada a móviles
* ✅ **Guardado de Presupuestos**: Permite guardar un histórico de los presupuestos proyectados.

## 🗂️ Versiones del presupuesto guardado

Una versión guardada son **dos** cosas:

| Tabla | Grano | Qué guarda |
| ----- | ----- | ---------- |
| `RO_T_HISTORIAL_COMPRAS_PROYECTADAS_CABECERA` | una fila por versión | cuándo y quién la guardó, desde qué solapa, con qué fecha se calculó, **temporada objetivo** con desde/hasta, período que cubre cada venta proyectada, si es completa o parcial, y si es la **oficial** |
| `RO_T_HISTORIAL_COMPRAS_PROYECTADAS_PRESUPUESTO` | una fila por rubro/categoría | el detalle, más los componentes del stock proyectado, el costo con el que se calculó y la temporada base de cada venta anterior |
| `RO_T_HISTORIAL_COMPRAS_PROYECTADAS_TRAMO` | una fila por rubro/categoría **y tramo** | la compra abierta por temporada: cuánto se vende en ese tramo, cuánto cubre el stock y cuánto hay que comprar |
| `RO_T_HISTORIAL_COMPRAS_PROYECTADAS_OFICIAL_LOG` | una fila por marcado | quién marcó o desmarcó qué versión y cuándo |

El detalle **conserva** `nombre_presupuesto` y `temporada`, así que cualquier lector que hoy
consulte solo esa tabla sigue funcionando igual. `compra_proyectada` y
`venta_proyectada_verano/invierno` tampoco cambiaron de significado: siguen siendo el total.

## 📦 La compra, abierta por tramo

La compra proyectada era **un** número por fila para toda la ventana de la solapa.
Transitando verano, la solapa verano calcula:

```
compra = stock proyectado − (resto VER 26-27 + VER 27-28) − INV 27
```

Sirve para decidir cuánto comprar, pero no para el cashflow: lo de INV 27 y lo de
VER 27-28 llegan en **contenedores distintos** y en **meses distintos**, así que se pagan
en meses distintos. La tabla de tramos abre ese número por temporada.

### Cómo se reparte

El stock proyectado es un **pozo único** que se consume en **orden cronológico**: cada
tramo toma lo que puede del stock que quedó, y la compra de ese tramo es lo que el stock
no alcanzó a cubrir. Se descartó prorratear el stock entre tramos en proporción a su
venta: el stock que hay hoy cubre primero lo que se vende primero, no una fracción de
cada temporada futura.

Las OC pendientes ya vienen dentro del stock proyectado y **no** se afectan a la
temporada de su oleada: entran al pozo común como cualquier unidad. Es una simplificación
deliberada — las fechas reales de esas OC las administra Comercio Exterior y esta app no
las tiene — y está medida: sobre la versión oficial de Argentina, afectarlas movería
**1.619 unidades del tramo objetivo, el 0,23 %, en 1 fila de 72**.

Todo el reparto vive en una función pura, `PresupuestoCalculos::repartirCompraPorTramo()`:
no lee el reloj, la sesión ni la base, así que el mismo reparto se puede reconstruir meses
después desde una versión guardada y da idéntico. El JS **no** lo duplica: al editar un
índice le pide al servidor el reparto de esa fila (`recalcular-tramos`).

### Los tres tipos de tramo

| | Qué es | Lo lee el consumidor externo |
| --- | --- | --- |
| **Objetivo** (`es_objetivo = 1`) | La temporada que esta versión tiene que cubrir | **Sí.** Es lo que la versión aporta |
| **Intermedio** | Una temporada que queda en el medio del horizonte | No. Queda como **control** |
| **No comprable** (`es_comprable = 0`) | El resto de la temporada en curso | No. Ya no llega a tiempo un contenedor nuevo |

Cada versión oficial aporta **solo la compra de su temporada objetivo**. Si no, INV 27
quedaría cubierta dos veces: por su propia oficial y por el tramo intermedio de la oficial
de VER 27-28. Los dos números coinciden por construcción — el stock se consume en el mismo
orden y las dos solapas usan las mismas bases — y eso se **verifica al marcar oficial**
(ver más abajo).

El tramo no comprable conserva su compra calculada, pero en pantalla se rotula
**"Sin cubrir"** y no "Compra": es venta que va a quedar sin cobertura, no mercadería a
comprar. Se lo deja dentro de `compra` para que el invariante siga siendo literal.

### El déficit de stock de cobertura

Cuando el stock de seguridad supera a todo lo disponible, el stock proyectado arranca
negativo (`ACCESORIO DE CUERO: 0 − 309 = −309`). En Argentina eso pasa en **22 de 72
filas, 13.986 unidades**; en Uruguay, en 2 de 36.

Ese déficit **no** se le carga al primer tramo cronológico, que es el resto de la
temporada en curso: ahí desaparecería del presupuesto, porque ese tramo no se puede
comprar y nadie lo lee. Va al **primer tramo comprable**, que es el contenedor más cercano
sobre el que todavía se puede actuar, y queda separado en `compra_deficit_cobertura` para
que finanzas pueda tratarlo distinto de una compra por venta. Se descartó mandarlo al
tramo objetivo: lo habría atrasado hasta un año sin motivo.

### El invariante

Por cada fila de detalle:

```
SUM(tramo.compra) = MAX(0, −compra_proyectada)
```

Las filas con excedente dan **0 en todos los tramos**. Se cumple exacto porque el pozo se
redondea a unidades enteras igual que `compra_proyectada`, y la venta de cada tramo se
redondea **por tramo** y no sobre la suma.

### La versión oficial

Hay **una sola versión oficial por país y temporada objetivo**, garantizado por un índice
único filtrado (`WHERE es_oficial = 1`). Se marca desde el panel *Versiones guardadas* del
historial; marcar una desmarca la anterior en la misma transacción, previa confirmación.

Una versión **parcial no puede ser oficial**: lo impide un `CHECK` en la base además de la
aplicación, porque es la regla que protege al consumidor externo.

Al marcar, se comparan los **tramos compartidos** con las otras oficiales vigentes del
mismo país. Calculadas el mismo día y sin tocar nada tienen que dar idéntico; si no dan,
lo más común es que se haya editado el índice de un rubro en **una sola de las dos
solapas** (el editor toca únicamente los datos de la solapa abierta). El modal muestra
por tramo cuántas filas difieren, los dos totales, la diferencia y **qué rubros**.

Avisa, no bloquea: la diferencia puede ser deliberada. Lo que no puede pasar es que el
cashflow reciba dos números para la misma temporada sin que nadie se entere.

### Eliminar una versión es una baja lógica

Eliminar **no borra nada**: marca `eliminada = 1` en la cabecera. La versión deja de
listarse en el historial y en el panel de versiones, pero el detalle, la compra por tramo
y el log siguen enteros.

Se hizo así porque con borrado físico dos reglas no podían cumplirse a la vez: *el log de
oficial no se borra nunca* y *la oficial se puede eliminar después de desmarcarla*. El log
referencia a la cabecera por clave foránea, así que conservarlo obliga a conservarla — y
una versión que alguna vez fue oficial no se podía eliminar nunca más. Con baja lógica las
dos se cumplen.

**No hay restaurar**, por decisión explícita: desde la aplicación la baja sigue siendo
definitiva. Lo que cambia es que los datos siguen ahí, así que una baja por error se
revierte con un `UPDATE` puntual y no con un backup.

Sigue habiendo un solo caso bloqueado:

| Caso | Por qué | Salida |
| --- | --- | --- |
| Es la **oficial** | Darla de baja deja una temporada sin presupuesto sin que nadie se entere: el cashflow deja de encontrarla | Desmarcarla primero (`desmarcar-oficial`), lo que queda registrado |

**Desmarcar sin reemplazo** deja la temporada sin ninguna versión vigente. Es una decisión
fuerte y va en dos pasos, pero hace falta: sin ella, la regla de arriba dejaba a la oficial
atrapada sin salida, porque hasta ahora desmarcar solo ocurría como efecto secundario de
marcar otra.

Queda borrado **físico** en dos casos residuales: las versiones anteriores a la fase 2, que
no tienen cabecera donde marcar la baja, y las bases donde todavía no se corrió el `05`.

### ⚠️ El consumidor externo y las versiones dadas de baja

Una versión dada de baja **sigue en las tablas**. Cualquier consulta que no filtre
`eliminada = 0` la va a seguir viendo.

El riesgo está acotado: una versión dada de baja **no puede ser oficial** —lo garantiza
`CK_RO_T_HCP_CAB_oficial_no_eliminada`, en la base— así que todo lo que filtre
`es_oficial = 1`, que es lo que corresponde para el cashflow, queda cubierto solo.

Para lo demás está la vista **`RO_V_COMPRA_PROYECTADA_VIGENTE`** (bloque 4 del script
`05`, opcional): devuelve únicamente la compra por tramo de las versiones oficiales
vigentes, con el costo de cada fila, y no hay que conocer ni `eliminada` ni `es_oficial`.
Se prefirió darles una vista antes que pedirles que agreguen un `WHERE`: un filtro que hay
que acordarse de escribir es un filtro que alguna vez no se escribe, y el error sería
silencioso.

### Guardado completo

Por defecto se guarda el **presupuesto completo**, ignorando los filtros de la vista. Si hay
filtros aplicados, el sistema pregunta; elegir "solo lo filtrado" marca la versión como
parcial.

### Qué guarda el detalle y por qué

| Columnas | Por qué |
| -------- | ------- |
| `cant_stock`, `cant_stock_guardar`, `cant_pend_oc_verano/invierno/atemporal`, `stock_cobertura` | Descomponen `stock_proyectado` y la fila se audita sola. Las OC pendientes son las clave: entran al stock proyectado, así que la compra es **neta de lo ya pedido**, y meses después esas OC ya ingresaron y no habría forma de saber cuánto había pendiente |
| `costo_prom`, `inc_fob`, `vcosto` | Copiados de `FP_T_COSTOS_PARAMETROS` al guardar. Esa tabla **no tiene versión ni fecha**: se pisa en el lugar, así que sin copiarlos la versión deja de ser reproducible. Mismos nombres que usa el circuito de distribución |
| `temporada_base_verano`, `temporada_base_invierno` | De qué temporada histórica salió cada venta anterior. **Varía por fila**: se toma la última temporada con ventas, y un rubro sin movimiento reciente cae a una más vieja que el de al lado |

Se descartaron a propósito: los `markup_*` de `FP_T_COSTOS_PARAMETROS` (son margen de venta
por canal, pertenecen al circuito de distribución y tienen otro grano), el tipo de cambio
(depende de la fecha de **pago**, que esta tabla no conoce: lo aplica el cashflow) y el
importe FOB calculado (es derivable de dos columnas ya guardadas y guardarlo abre la puerta
a que queden desincronizados).

## 💻 API Endpoints

| Endpoint                            | Método | Descripción                             |
| ----------------------------------- | ------ | --------------------------------------- |
| `api.php?accion=presupuesto`        | GET    | Obtener todos los datos del presupuesto |
| `api.php?accion=conexion`           | GET    | Probar conexión a la base de datos      |
| `api.php?accion=temporadas`         | GET    | Obtener temporadas actuales             |
| `api.php?accion=estadisticas`       | GET    | Obtener estadísticas resumidas          |
| `api.php?accion=rubros`             | GET    | Obtener lista de rubros                 |
| `api.php?accion=rubro&rubro=NOMBRE` | GET    | Filtrar por rubro                       |
| `api.php?accion=guardar-presupuesto` | POST  | Guarda una versión del presupuesto proyectado. |
| `api.php?accion=buscar-historial`   | POST   | Busca en el historial de presupuestos guardados. |
| `api.php?accion=versiones-presupuesto` | GET | Lista las versiones guardadas (una fila por versión). |
| `api.php?accion=marcar-oficial`     | POST   | Marca una versión como oficial. Sin `confirmado` no escribe: devuelve cuál reemplazaría y, en `discrepancias`, los tramos que contradicen a otra oficial vigente. |
| `api.php?accion=desmarcar-oficial`  | POST   | Desmarca la oficial **sin poner otra**: deja la temporada sin vigente. Sin `confirmado` no escribe. |
| `api.php?accion=recalcular-tramos`  | POST   | Reparto por tramo de **una** fila, tras editar un índice. El navegador no lo recalcula: hay una sola implementación. |
| `api.php?accion=eliminar-version-presupuesto` | POST | Elimina una versión (cabecera + detalle + log). Sin `confirmado` no borra: devuelve cuántas filas se llevaría y si es la oficial. |
| `api.php?accion=historial-oficial`  | GET    | Quién marcó qué versión como oficial y cuándo. |

`buscar-historial` acepta además `id_cabecera` o `nombre_presupuesto` para ver **una sola
versión** en lugar de todo el historial.

> ⚠️ **Zona horaria.** El `php.ini` de XAMPP viene con `Europe/Berlin`, cinco horas
> adelante de Argentina: a partir de las 19:00 hora local PHP ya estaba en el día
> siguiente, lo que corría la fecha de cálculo y con ella los días restantes de temporada.
> `presupuestos/api.php`, `index.php` y los scripts de migración de `sql/` fijan
> `America/Argentina/Buenos_Aires`. Si se agrega otro punto de entrada al módulo, tiene
> que hacer lo mismo: no se toca el `php.ini`, que lo comparten todas las apps del
> servidor. Argentina y Uruguay están en el mismo huso, así que alcanza con uno.

Los endpoints de compra proyectada devuelven, además de `data`:

| Clave | Contenido |
| ----- | --------- |
| `columnas_venta` | Columnas históricas en orden cronológico |
| `etiquetas_historicas` | `{VTA_VERANO_26: "VER 25-26", ...}` |
| `periodos` | Qué período cubre cada columna proyectada en esa solapa, más la temporada objetivo |

Y cada fila de `data` trae `TRAMOS`: la compra ya repartida por temporada, con
`es_objetivo`, `es_comprable`, `venta_proyectada`, `stock_aplicado`, `compra` y
`compra_deficit_cobertura`. La pantalla, el Excel y el guardado usan **ese** reparto, para
que ninguno lo rehaga por su cuenta.

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
