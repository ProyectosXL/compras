<?php
require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/presupuesto.php';

/**
 * Parámetros del presupuesto de compras (solo Argentina, script 08).
 *
 * Se leen UNA vez por request y se usan desde acá: el cálculo (PresupuestoCalculos)
 * no lee la base, recibe los valores ya resueltos. Si la tabla todavía no existe
 * (antes de correr el 08) o la solicitud es de Uruguay, valen los DEFECTOS, que son
 * exactamente los números que hasta ahora estaban escritos en el código y en el SP:
 * así la app no se rompe ni cambia de resultado antes de correr el script.
 *
 * Esta clase es pura: se puede construir con valores a mano (desdeValores) y por eso
 * los tests prueban la prioridad del índice de cobertura sin base de datos.
 */
class Parametros {

    /** Valores de hoy. Mismos que carga el bloque 2 del script 08. */
    const DEFECTOS = [
        'COBERTURA_INDICE_DEFECTO'       => 1.50,
        'COBERTURA_DIAS_POR_MES'         => 30,
        'COBERTURA_DIAS_MINIMOS_VENDIDO' => 30,
        'COBERTURA_INDICE_MIN'           => 0,
        'COBERTURA_INDICE_MAX'           => 12,
        'VARIACION_VENTANA_DIAS'         => 60,
        'VARIACION_INDICE_MIN'           => 0,
        'VARIACION_INDICE_MAX'           => 10,
    ];

    const LISTAS_DEFECTO = [
        'RUBRO'               => ['PACKAGING', '_KITS'],
        'CATEGORIA'           => ['REMERA'],
        'ARTICULO'            => ['XV00LV00A0001'],
        'CANAL_VENTA'         => ['LOCALES PROPIOS', 'CENTRAL'],
        'CANAL_INDICE_LP'     => ['LOCALES PROPIOS'],
        'RUBRO_SIN_COBERTURA' => ['CALZADOS', 'CAMPERAS'],
    ];

    private static $instancia = null;

    private $esArgentina;
    private $desdeTablas;
    private $generales;
    private $cobertura;   // 'RUBRO|CATEGORIA' o 'RUBRO|' => índice
    private $listas;      // tipo => [valores]

    private function __construct($esArgentina, $desdeTablas, array $generales, array $cobertura, array $listas) {
        $this->esArgentina = (bool)$esArgentina;
        $this->desdeTablas = (bool)$desdeTablas;
        $this->generales = array_merge(self::DEFECTOS, $generales);
        $this->listas = array_merge(self::LISTAS_DEFECTO, $listas);

        $this->cobertura = [];
        foreach ($cobertura as $fila) {
            $this->cobertura[self::claveCobertura($fila['rubro'], $fila['categoria'] ?? null)] = (float)$fila['indice_cobertura'];
        }
    }

    /**
     * Los parámetros de la solicitud en curso, leídos una sola vez.
     */
    public static function actual() {
        if (self::$instancia === null) {
            $esArgentina = Presupuesto::paisDeLaSolicitud() === 'argentina';
            $datos = null;
            if ($esArgentina) {
                $datos = (new ParametrosRepositorio())->cargar();
            }
            self::$instancia = $datos
                ? new self(true, true, $datos['generales'], $datos['cobertura'], $datos['listas'])
                : new self($esArgentina, false, [], [], []);
        }
        return self::$instancia;
    }

    /** Para tests: parámetros armados a mano, sin base. */
    public static function desdeValores(array $generales = [], array $cobertura = [], array $listas = [], $esArgentina = true) {
        return new self($esArgentina, false, $generales, $cobertura, $listas);
    }

    /** Para tests y para después de guardar desde la pantalla. */
    public static function reiniciar() {
        self::$instancia = null;
    }

    public static function normalizar($texto) {
        return mb_strtoupper(trim((string)$texto), 'UTF-8');
    }

    private static function claveCobertura($rubro, $categoria) {
        return self::normalizar($rubro) . '|' . ($categoria === null || $categoria === '' ? '' : self::normalizar($categoria));
    }

    public function esArgentina() {
        return $this->esArgentina;
    }

    /** Si los valores salen de las tablas del 08 o son los de fábrica. */
    public function desdeTablas() {
        return $this->desdeTablas;
    }

    public function numero($clave) {
        return (float)($this->generales[$clave] ?? self::DEFECTOS[$clave] ?? 0);
    }

    public function lista($tipo) {
        return $this->listas[$tipo] ?? [];
    }

    public function esRubroSinCobertura($rubro) {
        $r = self::normalizar($rubro);
        foreach ($this->lista('RUBRO_SIN_COBERTURA') as $v) {
            if (self::normalizar($v) === $r) return true;
        }
        return false;
    }

    /**
     * Índice de cobertura POR DEFECTO de una categoría, antes de cualquier edición
     * en pantalla. Prioridad: categoría > rubro > rubro sin cobertura (0) > general.
     *
     * "Sin cobertura" es un valor por defecto y no un 0 fijo: una fila de la grilla
     * para ese rubro o categoría, o la edición en pantalla, lo pisan.
     *
     * @return array ['indice' => float, 'origen' => 'categoria'|'rubro'|'sin_cobertura'|'general']
     */
    public function indiceCoberturaDefecto($rubro, $categoria) {
        $porCategoria = self::claveCobertura($rubro, $categoria);
        if (isset($this->cobertura[$porCategoria])) {
            return ['indice' => $this->cobertura[$porCategoria], 'origen' => 'categoria'];
        }
        $porRubro = self::claveCobertura($rubro, null);
        if (isset($this->cobertura[$porRubro])) {
            return ['indice' => $this->cobertura[$porRubro], 'origen' => 'rubro'];
        }
        if ($this->esRubroSinCobertura($rubro)) {
            return ['indice' => 0.0, 'origen' => 'sin_cobertura'];
        }
        return ['indice' => $this->numero('COBERTURA_INDICE_DEFECTO'), 'origen' => 'general'];
    }

    /**
     * Lo que necesita el navegador: límites de edición y si hay pantalla de
     * parámetros. Viaja con la carga de datos.
     */
    public function paraCliente() {
        return [
            'es_argentina'   => $this->esArgentina,
            'desde_tablas'   => $this->desdeTablas,
            'cobertura_min'  => $this->numero('COBERTURA_INDICE_MIN'),
            'cobertura_max'  => $this->numero('COBERTURA_INDICE_MAX'),
            'variacion_min'  => $this->numero('VARIACION_INDICE_MIN'),
            'variacion_max'  => $this->numero('VARIACION_INDICE_MAX'),
            'dias_por_mes'   => $this->numero('COBERTURA_DIAS_POR_MES'),
            'dias_minimos'   => $this->numero('COBERTURA_DIAS_MINIMOS_VENDIDO'),
        ];
    }
}

/**
 * Acceso a las tablas del script 08: lectura, escritura con auditoría e historial.
 * Separado de Parametros para que el cálculo nunca dependa de una conexión.
 */
class ParametrosRepositorio {

    const TIPOS_LISTA = ['RUBRO', 'CATEGORIA', 'ARTICULO', 'CANAL_VENTA', 'CANAL_INDICE_LP', 'RUBRO_SIN_COBERTURA'];

    // Listas que no pueden quedar vacías: el SP sumaría venta de ningún canal y
    // todos los índices darían 0.
    const LISTAS_NO_VACIAS = ['CANAL_VENTA', 'CANAL_INDICE_LP'];

    /** Rango válido de cada parámetro general [mínimo, máximo, entero]. */
    const RANGOS = [
        'COBERTURA_INDICE_DEFECTO'       => [0, 12, false],
        'COBERTURA_DIAS_POR_MES'         => [1, 31, true],
        'COBERTURA_DIAS_MINIMOS_VENDIDO' => [0, 366, true],
        'COBERTURA_INDICE_MIN'           => [0, 100, false],
        'COBERTURA_INDICE_MAX'           => [0, 100, false],
        'VARIACION_VENTANA_DIAS'         => [1, 365, true],
        'VARIACION_INDICE_MIN'           => [0, 100, false],
        'VARIACION_INDICE_MAX'           => [0, 100, false],
    ];

    private $cid;

    public function __construct() {
        // Siempre la base de Argentina: los parámetros son solo de Argentina y esta
        // clase no se usa para Uruguay (Parametros::actual() ni la instancia).
        $this->cid = (new Conexion())->conectar('apps_power');
    }

    private function usuario() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['usuario_dns']) && $_SESSION['usuario_dns'] !== '' ? $_SESSION['usuario_dns'] : null;
    }

    public function hayTablas() {
        if (!$this->cid) return false;
        $fila = $this->primera("SELECT CASE WHEN OBJECT_ID('dbo.RO_T_PC_PARAMETROS','U') IS NULL
                                              OR OBJECT_ID('dbo.RO_T_PC_PARAMETROS_COBERTURA','U') IS NULL
                                              OR OBJECT_ID('dbo.RO_T_PC_PARAMETROS_EXCLUSIONES','U') IS NULL
                                            THEN 0 ELSE 1 END AS existe");
        return $fila && (int)$fila['existe'] === 1;
    }

    /**
     * Valores vigentes para el cálculo, o null si las tablas no existen o no se
     * pudieron leer (se usan los defectos).
     */
    public function cargar() {
        try {
            if (!$this->hayTablas()) return null;

            $generales = [];
            foreach ($this->todas("SELECT clave, valor FROM dbo.RO_T_PC_PARAMETROS") as $f) {
                if (is_numeric($f['valor'])) $generales[$f['clave']] = (float)$f['valor'];
            }

            $cobertura = $this->todas("SELECT rubro, categoria, indice_cobertura FROM dbo.RO_T_PC_PARAMETROS_COBERTURA");

            // Una lista ausente en la tabla queda con su defecto; una lista presente
            // con todos sus valores dados de baja queda vacía, que es lo que se pidió.
            $listas = [];
            foreach ($this->todas("SELECT tipo, valor, activo FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES") as $f) {
                if (!isset($listas[$f['tipo']])) $listas[$f['tipo']] = [];
                if ((int)$f['activo'] === 1) $listas[$f['tipo']][] = $f['valor'];
            }

            return ['generales' => $generales, 'cobertura' => $cobertura, 'listas' => $listas];
        } catch (Throwable $e) {
            error_log('Parametros: se usan los valores por defecto. ' . $e->getMessage());
            return null;
        }
    }

    /** Todo lo que muestra la pantalla, con quién y cuándo modificó cada valor. */
    public function listarParaPantalla() {
        $ultAct = $this->primera("SELECT MAX(ULT_ACTUALIZACION) AS ult FROM dbo.RO_PC_T_VENTAS_PRESUPUESTO_COMPRAS");
        $ult = $ultAct && $ultAct['ult'] instanceof DateTime ? $ultAct['ult']->format('Y-m-d H:i') : null;

        if (!$this->hayTablas()) {
            return ['tablas' => false, 'ult_actualizacion' => $ult];
        }

        $fmt = function ($filas) {
            foreach ($filas as &$f) {
                if (isset($f['fecha_modif']) && $f['fecha_modif'] instanceof DateTime) {
                    $f['fecha_modif'] = $f['fecha_modif']->format('Y-m-d H:i');
                }
            }
            return $filas;
        };

        return [
            'tablas' => true,
            'ult_actualizacion' => $ult,
            'generales' => $fmt($this->todas("SELECT clave, valor, tipo, descripcion, usuario_modif, fecha_modif
                                               FROM dbo.RO_T_PC_PARAMETROS ORDER BY clave")),
            'cobertura' => $fmt($this->todas("SELECT id, rubro, categoria, indice_cobertura, usuario_modif, fecha_modif
                                               FROM dbo.RO_T_PC_PARAMETROS_COBERTURA ORDER BY rubro, categoria")),
            'listas' => $fmt($this->todas("SELECT id, tipo, valor, activo, usuario_modif, fecha_modif
                                            FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES ORDER BY tipo, valor")),
            'historial' => $fmt(array_map(function ($f) {
                if ($f['fecha'] instanceof DateTime) $f['fecha'] = $f['fecha']->format('Y-m-d H:i');
                return $f;
            }, $this->todas("SELECT TOP 200 tabla, clave, accion, valor_anterior, valor_nuevo, usuario, fecha
                             FROM dbo.RO_T_PC_PARAMETROS_HIST ORDER BY fecha DESC, id DESC")))
        ];
    }

    // ------------------------------------------------------------------
    // Escritura. Cada cambio va en su transacción junto con su fila de historial.
    // ------------------------------------------------------------------

    public function guardarGeneral($clave, $valor) {
        if (!isset(self::RANGOS[$clave])) {
            throw new InvalidArgumentException("Parámetro desconocido: $clave");
        }
        list($min, $max, $entero) = self::RANGOS[$clave];
        if (!is_numeric($valor) || $valor < $min || $valor > $max || ($entero && floor($valor) != $valor)) {
            throw new InvalidArgumentException("Valor fuera de rango para $clave: debe ser " . ($entero ? 'entero ' : '') . "entre $min y $max.");
        }

        $actual = $this->primera("SELECT valor FROM dbo.RO_T_PC_PARAMETROS WHERE clave = ?", [$clave]);
        if (!$actual) {
            throw new RuntimeException("El parámetro $clave no existe en la tabla (correr el bloque 2 del script 08).");
        }

        // Mínimo y máximo de un mismo índice tienen que seguir siendo un rango.
        $pares = ['COBERTURA_INDICE_MIN' => 'COBERTURA_INDICE_MAX', 'VARIACION_INDICE_MIN' => 'VARIACION_INDICE_MAX'];
        foreach ($pares as $cmin => $cmax) {
            if ($clave === $cmin || $clave === $cmax) {
                $otro = $this->primera("SELECT valor FROM dbo.RO_T_PC_PARAMETROS WHERE clave = ?", [$clave === $cmin ? $cmax : $cmin]);
                $vmin = $clave === $cmin ? (float)$valor : (float)($otro['valor'] ?? 0);
                $vmax = $clave === $cmax ? (float)$valor : (float)($otro['valor'] ?? 0);
                if ($vmin >= $vmax) {
                    throw new InvalidArgumentException('El mínimo tiene que ser menor que el máximo.');
                }
            }
        }

        $nuevo = $entero ? (string)(int)$valor : rtrim(rtrim(number_format((float)$valor, 4, '.', ''), '0'), '.');
        if ((string)$actual['valor'] === $nuevo) return;

        $this->enTransaccion(function () use ($clave, $nuevo, $actual) {
            $this->ejecutar("UPDATE dbo.RO_T_PC_PARAMETROS SET valor = ?, usuario_modif = ?, fecha_modif = SYSDATETIME() WHERE clave = ?",
                            [$nuevo, $this->usuario(), $clave]);
            $this->historial('GENERAL', $clave, 'MODIF', $actual['valor'], $nuevo);
        });
    }

    /** Alta o modificación de un índice de cobertura por rubro (categoría vacía) o categoría. */
    public function guardarCobertura($rubro, $categoria, $indice) {
        $rubro = trim((string)$rubro);
        $categoria = trim((string)$categoria);
        $categoria = $categoria === '' ? null : $categoria;
        if ($rubro === '') throw new InvalidArgumentException('Falta el rubro.');

        $p = Parametros::actual();
        $min = $p->numero('COBERTURA_INDICE_MIN');
        $max = $p->numero('COBERTURA_INDICE_MAX');
        if (!is_numeric($indice) || $indice < $min || $indice > $max) {
            throw new InvalidArgumentException("El índice de cobertura tiene que estar entre $min y $max.");
        }
        $indice = round((float)$indice, 4);
        $clave = $rubro . '|' . ($categoria ?? '');

        $actual = $categoria === null
            ? $this->primera("SELECT id, indice_cobertura FROM dbo.RO_T_PC_PARAMETROS_COBERTURA WHERE rubro = ? AND categoria IS NULL", [$rubro])
            : $this->primera("SELECT id, indice_cobertura FROM dbo.RO_T_PC_PARAMETROS_COBERTURA WHERE rubro = ? AND categoria = ?", [$rubro, $categoria]);

        $this->enTransaccion(function () use ($actual, $rubro, $categoria, $indice, $clave) {
            if ($actual) {
                $this->ejecutar("UPDATE dbo.RO_T_PC_PARAMETROS_COBERTURA SET indice_cobertura = ?, usuario_modif = ?, fecha_modif = SYSDATETIME() WHERE id = ?",
                                [$indice, $this->usuario(), $actual['id']]);
                $this->historial('COBERTURA', $clave, 'MODIF', (string)(float)$actual['indice_cobertura'], (string)$indice);
            } else {
                $this->ejecutar("INSERT INTO dbo.RO_T_PC_PARAMETROS_COBERTURA (rubro, categoria, indice_cobertura, usuario_modif) VALUES (?, ?, ?, ?)",
                                [$rubro, $categoria, $indice, $this->usuario()]);
                $this->historial('COBERTURA', $clave, 'ALTA', null, (string)$indice);
            }
        });
    }

    public function borrarCobertura($id) {
        $actual = $this->primera("SELECT rubro, categoria, indice_cobertura FROM dbo.RO_T_PC_PARAMETROS_COBERTURA WHERE id = ?", [(int)$id]);
        if (!$actual) throw new RuntimeException('La fila ya no existe.');

        $this->enTransaccion(function () use ($id, $actual) {
            $this->ejecutar("DELETE FROM dbo.RO_T_PC_PARAMETROS_COBERTURA WHERE id = ?", [(int)$id]);
            $this->historial('COBERTURA', $actual['rubro'] . '|' . ($actual['categoria'] ?? ''), 'BAJA',
                             (string)(float)$actual['indice_cobertura'], null);
        });
    }

    /** Agrega un valor a una lista (o lo reactiva si estaba dado de baja). */
    public function agregarALista($tipo, $valor) {
        $tipo = strtoupper(trim((string)$tipo));
        $valor = trim((string)$valor);
        if (!in_array($tipo, self::TIPOS_LISTA, true)) throw new InvalidArgumentException("Lista desconocida: $tipo");
        if ($valor === '' || mb_strlen($valor) > 100) throw new InvalidArgumentException('Valor vacío o demasiado largo.');

        $actual = $this->primera("SELECT id, activo FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES WHERE tipo = ? AND valor = ?", [$tipo, $valor]);
        if ($actual && (int)$actual['activo'] === 1) return;

        $this->enTransaccion(function () use ($actual, $tipo, $valor) {
            if ($actual) {
                $this->ejecutar("UPDATE dbo.RO_T_PC_PARAMETROS_EXCLUSIONES SET activo = 1, usuario_modif = ?, fecha_modif = SYSDATETIME() WHERE id = ?",
                                [$this->usuario(), $actual['id']]);
            } else {
                $this->ejecutar("INSERT INTO dbo.RO_T_PC_PARAMETROS_EXCLUSIONES (tipo, valor, activo, usuario_modif) VALUES (?, ?, 1, ?)",
                                [$tipo, $valor, $this->usuario()]);
            }
            $this->historial('LISTA', "$tipo:$valor", 'ALTA', null, $valor);
        });
    }

    public function quitarDeLista($id) {
        $actual = $this->primera("SELECT tipo, valor, activo FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES WHERE id = ?", [(int)$id]);
        if (!$actual || (int)$actual['activo'] === 0) return;

        if (in_array($actual['tipo'], self::LISTAS_NO_VACIAS, true)) {
            $quedan = $this->primera("SELECT COUNT(*) AS n FROM dbo.RO_T_PC_PARAMETROS_EXCLUSIONES WHERE tipo = ? AND activo = 1", [$actual['tipo']]);
            if ((int)$quedan['n'] <= 1) {
                throw new InvalidArgumentException('Esta lista no puede quedar vacía: el proceso no tendría canales para sumar venta.');
            }
        }

        $this->enTransaccion(function () use ($id, $actual) {
            $this->ejecutar("UPDATE dbo.RO_T_PC_PARAMETROS_EXCLUSIONES SET activo = 0, usuario_modif = ?, fecha_modif = SYSDATETIME() WHERE id = ?",
                            [$this->usuario(), (int)$id]);
            $this->historial('LISTA', $actual['tipo'] . ':' . $actual['valor'], 'BAJA', $actual['valor'], null);
        });
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private function historial($tabla, $clave, $accion, $anterior, $nuevo) {
        $this->ejecutar("INSERT INTO dbo.RO_T_PC_PARAMETROS_HIST (tabla, clave, accion, valor_anterior, valor_nuevo, usuario) VALUES (?, ?, ?, ?, ?, ?)",
                        [$tabla, $clave, $accion, $anterior, $nuevo, $this->usuario()]);
    }

    private function enTransaccion(callable $fn) {
        if (!$this->hayTablas()) {
            throw new RuntimeException('Faltan las tablas de parámetros: correr el script 08.');
        }
        if (sqlsrv_begin_transaction($this->cid) === false) {
            throw new RuntimeException('No se pudo iniciar la transacción.');
        }
        try {
            $fn();
            sqlsrv_commit($this->cid);
        } catch (Throwable $e) {
            sqlsrv_rollback($this->cid);
            throw $e;
        }
    }

    private function ejecutar($sql, $params = []) {
        $stmt = sqlsrv_query($this->cid, $sql, $params);
        if ($stmt === false) {
            throw new RuntimeException('Error de base: ' . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stmt);
    }

    private function primera($sql, $params = []) {
        $filas = $this->todas($sql, $params);
        return $filas[0] ?? null;
    }

    private function todas($sql, $params = []) {
        if (!$this->cid) return [];
        $stmt = sqlsrv_query($this->cid, $sql, $params);
        if ($stmt === false) {
            throw new RuntimeException('Error de base: ' . print_r(sqlsrv_errors(), true));
        }
        $filas = [];
        while ($f = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $filas[] = $f;
        }
        sqlsrv_free_stmt($stmt);
        return $filas;
    }
}
