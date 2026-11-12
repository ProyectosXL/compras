<?php
require_once __DIR__ . '/../class/parametros.php';

/**
 * Pantalla de Parámetros (solo Argentina).
 *
 * Lo puede editar cualquier usuario de la app: cada cambio queda con usuario y fecha
 * en la fila y en RO_T_PC_PARAMETROS_HIST. Con el switch en Uruguay todas las acciones
 * se rechazan: las tablas existen solo en POWER_BI_CONTROL y escribir en la base de
 * Argentina desde una sesión de Uruguay sería un error difícil de ver.
 */
class ParametrosController {

    public function procesar($accion) {
        try {
            if (!Parametros::actual()->esArgentina()) {
                $this->responder(['success' => false, 'message' => 'Los parámetros son solo de Argentina.'], 400);
            }

            $repo = new ParametrosRepositorio();
            $entrada = json_decode(file_get_contents('php://input'), true) ?: [];

            switch ($accion) {
                case 'parametros':
                    $this->responder(['success' => true] + $repo->listarParaPantalla());
                    break;
                case 'guardar-parametro':
                    $repo->guardarGeneral((string)($entrada['clave'] ?? ''), $entrada['valor'] ?? null);
                    break;
                case 'guardar-cobertura-parametro':
                    $repo->guardarCobertura($entrada['rubro'] ?? '', $entrada['categoria'] ?? '', $entrada['indice'] ?? null);
                    break;
                case 'borrar-cobertura-parametro':
                    $repo->borrarCobertura($entrada['id'] ?? 0);
                    break;
                case 'agregar-lista-parametro':
                    $repo->agregarALista($entrada['tipo'] ?? '', $entrada['valor'] ?? '');
                    break;
                case 'quitar-lista-parametro':
                    $repo->quitarDeLista($entrada['id'] ?? 0);
                    break;
                default:
                    $this->responder(['success' => false, 'message' => 'Acción no válida'], 400);
            }

            // Después de escribir se devuelve el estado completo, así la pantalla
            // muestra el "Modificado por" nuevo sin pedir otra vez.
            Parametros::reiniciar();
            $this->responder(['success' => true] + $repo->listarParaPantalla());

        } catch (InvalidArgumentException $e) {
            $this->responder(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            $this->responder(['success' => false, 'message' => 'Error en parámetros: ' . $e->getMessage()], 500);
        }
    }

    private function responder($datos, $codigo = 200) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
