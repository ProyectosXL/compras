<?php
/**
 * Stock de cobertura (Bloque D): fórmula, regla de días mínimos, rubros sin
 * cobertura y prioridad del índice.
 *
 * Escenario de la especificación: hoy 12/11/2026, SP corrido ese día.
 *   Temporada en curso VER 26-27 (01/08/2026 a 31/01/2027, 184 días):
 *   103 días transcurridos (01/08 a 11/11) y 81 restantes (12/11 a 31/01).
 *   Próxima del otro tipo: INV 27 (01/02/2027 a 31/07/2027, 181 días).
 */
require __DIR__ . '/bootstrap.php';

echo "== Calendario del escenario ==\n";
$ctx = contexto('verano', '2026-11-12', '2026-11-12 08:30');
verificar('días totales 184', $ctx['dias_totales'] === 184, $ctx['dias_totales']);
verificar('días restantes 81', $ctx['dias_restantes'] === 81, $ctx['dias_restantes']);
verificar('días transcurridos 103', $ctx['dias_transcurridos'] === 103, $ctx['dias_transcurridos']);
verificar('vendido vigente', $ctx['vendido_vigente'] === true);
verificar('INV 27 tiene 181 días', PresupuestoCalculos::construirTemporada('INVIERNO', 2027)['dias'] === 181);

echo "\n== Fórmula ==\n";
// Fila: base verano 1000 × 1,10, base invierno 800 × 0,90, vendido 500.
//   VP_resto  (Resto VER 26-27) = round(1000 × 1,10 × 81/184) = 484
//   VP_próxima (INV 27)         = round(800 × 0,90)            = 720
//   ritmo = (500 + 484 + 720) / (103 + 81 + 181) = 1704 / 365 = 4,6685
//   cobertura = round(4,6685 × 1,50 × 30) = round(210,08) = 210
$r = PresupuestoCalculos::calcularFila(fila(), $ctx);
verificar('VP resto 484', $r['tramos'][0]['venta_proyectada'] === 484, $r['tramos'][0]);
verificar('ritmo 4,6685', abs($r['cobertura']['ritmo_diario'] - 4.6685) < 0.0001, $r['cobertura']['ritmo_diario']);
verificar('usa lo vendido', $r['cobertura']['usa_vendido'] === true);
verificar('cobertura 210', $r['stock_cobertura'] === 210, $r['stock_cobertura']);
verificar('stock proyectado = componentes - cobertura', $r['stock_proyectado'] == (2000 + 100 + 300 + 50 - 210), $r['stock_proyectado']);
verificar('compra = stock proy - ventas', $r['compra_proyectada'] === (int)(2240 - $r['venta_proy_verano'] - $r['venta_proy_invierno']));

// Misma fila desde la solapa Invierno: sin ediciones la cobertura da igual.
$ri = PresupuestoCalculos::calcularFila(fila(), contexto('invierno', '2026-11-12', '2026-11-12 08:30'));
verificar('Verano e Invierno dan la misma cobertura sin ediciones', $ri['stock_cobertura'] === 210, $ri['stock_cobertura']);

// Editar el índice de verano mueve la cobertura (VP_resto cambia).
$re = PresupuestoCalculos::calcularFila(fila(['indice_verano' => 2.0]), $ctx);
// resto = round(1000 × 2 × 81/184) = 880 → (500+880+720)/365 × 45 = 258,9 → 259
verificar('editar índice de verano recalcula la cobertura', $re['stock_cobertura'] === 259, $re['stock_cobertura']);

// Una venta manual se usa tal cual en la cobertura.
$rm = PresupuestoCalculos::calcularFila(fila(['venta_manual_invierno' => 1000]), $ctx);
// próxima = 1000 → (500+484+1000)/365 × 45 = 244,6 → 245
verificar('venta manual entra en la cobertura', $rm['stock_cobertura'] === 245, $rm['stock_cobertura']);

echo "\n== Regla de días mínimos ==\n";
// 15/08/2026, SP corrido ese día: 14 días transcurridos < 30 -> no suma lo vendido.
$ctx15 = contexto('verano', '2026-08-15', '2026-08-15 07:00');
verificar('14 días transcurridos', $ctx15['dias_transcurridos'] === 14, $ctx15['dias_transcurridos']);
verificar('170 días restantes', $ctx15['dias_restantes'] === 170, $ctx15['dias_restantes']);
$r15 = PresupuestoCalculos::calcularFila(fila(), $ctx15);
//   resto = round(1000 × 1,10 × 170/184) = 1016 ; próxima 720
//   ritmo = (1016 + 720) / (170 + 181) = 4,9459 -> × 45 = 222,56 -> 223
verificar('no usa lo vendido', $r15['cobertura']['usa_vendido'] === false);
verificar('cobertura 223 solo con proyección', $r15['stock_cobertura'] === 223, $r15['stock_cobertura']);

// Exactamente en el mínimo sí suma (>=). 31/08: 30 días transcurridos.
$ctx31 = contexto('verano', '2026-08-31', '2026-08-31 07:00');
verificar('30 días transcurridos', $ctx31['dias_transcurridos'] === 30, $ctx31['dias_transcurridos']);
verificar('con 30 días sí usa lo vendido', PresupuestoCalculos::calcularFila(fila(), $ctx31)['cobertura']['usa_vendido'] === true);

// Parámetro de días mínimos distinto.
$ctx10 = PresupuestoCalculos::contextoCalculo('verano', '2026-08-15', '2026-08-15 07:00', true,
    Parametros::desdeValores(['COBERTURA_DIAS_MINIMOS_VENDIDO' => 10]));
verificar('con mínimo 10, 14 días sí usan lo vendido', PresupuestoCalculos::calcularFila(fila(), $ctx10)['cobertura']['usa_vendido'] === true);

// SP corrido en la temporada anterior: lo vendido no es de esta temporada.
$ctxViejo = contexto('verano', '2026-11-12', '2026-07-30 07:00');
verificar('SP de otra temporada: no vigente', $ctxViejo['vendido_vigente'] === false && $ctxViejo['dias_transcurridos'] === 0);
verificar('SP de otra temporada: no usa lo vendido', PresupuestoCalculos::calcularFila(fila(), $ctxViejo)['cobertura']['usa_vendido'] === false);

// Sin la columna del SP (antes del 06).
$ctxSin = PresupuestoCalculos::contextoCalculo('verano', '2026-11-12', '2026-11-12', false, Parametros::desdeValores());
verificar('sin columna VTA_TEMPORADA_EN_CURSO no usa lo vendido', PresupuestoCalculos::calcularFila(fila(), $ctxSin)['cobertura']['usa_vendido'] === false);

echo "\n== Sin venta ==\n";
$r0 = PresupuestoCalculos::calcularFila(fila(['venta_verano_anterior' => 0, 'venta_invierno_anterior' => 0, 'vta_temporada_en_curso' => 0]), $ctx);
verificar('sin venta proyectada ni vendida -> 0', $r0['stock_cobertura'] === 0, $r0['stock_cobertura']);
verificar('índice 0 -> 0', PresupuestoCalculos::calcularFila(fila(['indice_cobertura' => 0]), $ctx)['stock_cobertura'] === 0);

echo "\n== Rubros sin cobertura y prioridad del índice ==\n";
$p = Parametros::desdeValores([], [
    ['rubro' => 'CARTERAS DE CUERO', 'categoria' => null, 'indice_cobertura' => 2.0],
    ['rubro' => 'CARTERAS DE CUERO', 'categoria' => 'MALETIN', 'indice_cobertura' => 0.5],
    ['rubro' => 'CALZADOS', 'categoria' => 'BOTA', 'indice_cobertura' => 1.0],
]);
verificar('general 1,50', $p->indiceCoberturaDefecto('CHALINAS', 'GORRO')['indice'] == 1.5);
verificar('rubro > general', $p->indiceCoberturaDefecto('CARTERAS DE CUERO', 'CARTERA')['indice'] == 2.0);
verificar('categoría > rubro', $p->indiceCoberturaDefecto('CARTERAS DE CUERO', 'MALETIN')['indice'] == 0.5);
verificar('rubro sin cobertura -> 0', $p->indiceCoberturaDefecto('CALZADOS', 'ZAPATILLA')['indice'] == 0.0
    && $p->indiceCoberturaDefecto('CAMPERAS', 'CAMPERA')['origen'] === 'sin_cobertura');
verificar('categoría pisa a "sin cobertura"', $p->indiceCoberturaDefecto('CALZADOS', 'BOTA')['indice'] == 1.0);
verificar('sin distinguir mayúsculas ni espacios', $p->indiceCoberturaDefecto(' carteras de cuero ', 'maletin')['indice'] == 0.5);

$pr = Parametros::desdeValores([], [['rubro' => 'CALZADOS', 'categoria' => null, 'indice_cobertura' => 0.8]]);
verificar('rubro pisa a "sin cobertura"', $pr->indiceCoberturaDefecto('CALZADOS', 'BOTA')['indice'] == 0.8);

// Rubro sin cobertura con edición en pantalla: el índice editado es el que llega a
// calcularFila, que no sabe de rubros; lo pisa sin más.
$fCalzado = fila(['indice_cobertura' => $p->indiceCoberturaDefecto('CALZADOS', 'ZAPATILLA')['indice']]);
verificar('calzados sin editar -> cobertura 0', PresupuestoCalculos::calcularFila($fCalzado, $ctx)['stock_cobertura'] === 0);
verificar('calzados editado en pantalla a 1,50 -> 210', PresupuestoCalculos::calcularFila(fila(['indice_cobertura' => 1.5]), $ctx)['stock_cobertura'] === 210);

$pd = Parametros::desdeValores(['COBERTURA_INDICE_DEFECTO' => 2.0, 'COBERTURA_DIAS_POR_MES' => 31]);
verificar('general parametrizado', $pd->indiceCoberturaDefecto('X', 'Y')['indice'] == 2.0);
$ctx31d = PresupuestoCalculos::contextoCalculo('verano', '2026-11-12', '2026-11-12', true, $pd);
// 4,6685 × 1,5 × 31 = 217,08 -> 217
verificar('días por mes parametrizado', PresupuestoCalculos::calcularFila(fila(), $ctx31d)['stock_cobertura'] === 217);

terminar();
