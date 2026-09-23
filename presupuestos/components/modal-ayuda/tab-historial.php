<div class="tab-pane fade" id="historial-ayuda" role="tabpanel">
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-save me-2"></i>Funcionalidad de Guardado y Historial</h6>
                </div>
                <div class="card-body">
                    <p>
                        El sistema ahora permite guardar "fotografías" de sus proyecciones de compra para consultarlas en el futuro. Esto es útil para comparar diferentes escenarios, archivar proyecciones al final de una temporada o compartir los resultados con otros equipos.
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title text-primary">
                        <i class="fas fa-save me-2"></i>
                        ¿Cómo Guardar una Proyección?
                    </h5>
                    <ol>
                        <li>Navegue a la solapa <strong>Compra Proy. Verano</strong> o <strong>Compra Proy. Invierno</strong>.</li>
                        <li>
                            Haga clic en el botón <button class="btn btn-primary btn-sm disabled"><i class="fas fa-save me-1"></i> Guardar</button> que se encuentra en la esquina superior derecha.
                        </li>
                        <li>
                            <strong>Se guarda el presupuesto completo</strong>, aunque tenga filtros aplicados.
                            Si hay filtros, el sistema le pregunta si quiere guardar todo o solo lo que está
                            viendo.
                        </li>
                        <li>Se generará un nombre automático que incluye la fecha y la hora (ej: <code>Presupuesto_2026-09-22_10-30_verano</code>).</li>
                        <li>Recibirá una notificación con el resultado y la temporada objetivo de la versión.</li>
                    </ol>

                    <div class="alert alert-warning">
                        <i class="fas fa-filter me-2"></i>
                        <strong>Versiones parciales:</strong> guardar solo lo filtrado sirve para sacar una
                        foto de lo que está mirando, pero esa versión queda marcada como <em>parcial</em> y
                        <strong>no puede marcarse como oficial</strong>, porque no representa el presupuesto
                        completo de la temporada.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-info">
                        <i class="fas fa-history me-2"></i>
                        Consultar el Historial
                    </h5>
                    <p>
                        Para ver los presupuestos que ha guardado, diríjase a la nueva solapa <strong>Historial Compras Proyectadas</strong>.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-tags me-2"></i>
                        <strong>Solapa y Temporada objetivo:</strong> la columna <em>Solapa</em> indica desde
                        dónde se guardó (verano o invierno) y <em>Temporada objetivo</em> la temporada que esa
                        compra tenía que cubrir, en la convención <code>VER AA-AA</code> / <code>INV AA</code>
                        (por ejemplo <code>VER 27-28</code>). Es la temporada en la que los contenedores
                        tienen que estar. Un asterisco (<code>*</code>) al lado del código significa que esa
                        versión es anterior a esta funcionalidad y la temporada se dedujo de la fecha de
                        guardado.
                    </div>
                    <div class="alert alert-success">
                        <i class="fas fa-layer-group me-2"></i>
                        <strong>Compra por tramo:</strong> la última columna abre la compra de cada fila
                        por temporada, que es lo que necesita el cashflow: lo de cada temporada llega en
                        contenedores distintos y se paga en meses distintos. Va en una sola columna con
                        etiquetas porque el historial mezcla versiones de distintas solapas y fechas, y
                        cada una puede tener otros tramos.
                        <ul class="small mb-0 mt-2">
                            <li><span class="badge bg-success">verde</span> la temporada objetivo: lo que
                                esa versión le aporta al cashflow.</li>
                            <li><span class="badge bg-light text-dark border">gris</span> un tramo
                                intermedio, que queda como control.</li>
                            <li><span class="badge bg-danger-subtle text-danger-emphasis">rojo</span> el
                                resto de la temporada que estaba en curso: venta que quedó sin cubrir,
                                no mercadería a comprar.</li>
                            <li><span class="badge bg-secondary">sin reparto</span> no se pudo
                                reconstruir el reparto de esa versión. El motivo está en el tooltip; no
                                se inventó ningún número.</li>
                            <li><code>—</code> la versión es anterior al reparto por tramo, o todavía no
                                se corrieron los scripts en esa base.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title text-success">
                        <i class="fas fa-flag me-2"></i>
                        La versión oficial
                    </h5>
                    <p>
                        El panel <strong>Versiones guardadas</strong>, arriba de la tabla de historial, lista
                        una fila por versión. Ahí se marca cuál es la <strong>oficial</strong>: la versión
                        vigente de cada país y temporada objetivo, que es la que leen los sistemas que
                        proyectan las compras del exterior.
                    </p>
                    <ul>
                        <li>Hay <strong>una sola oficial</strong> por país y temporada objetivo.</li>
                        <li>Marcar una versión como oficial <strong>desmarca la anterior</strong>. El sistema
                            le muestra cuál va a reemplazar antes de hacerlo.</li>
                        <li>Queda registrado <strong>quién marcó qué y cuándo</strong>, incluido el desmarcado
                            de la versión anterior.</li>
                        <li>Una versión <strong>parcial no puede ser oficial</strong>. Si necesita oficializarla,
                            vuelva a guardar el presupuesto completo.</li>
                        <li>Cada oficial aporta <strong>solo la compra de su temporada objetivo</strong>.
                            Los tramos intermedios quedan como control: si no, una temporada quedaría
                            cubierta dos veces, por su propia versión y por el tramo intermedio de otra.</li>
                        <li>La versión oficial <strong>no se puede eliminar</strong>. Si de verdad hay que
                            sacarla, primero se la <strong>desmarca</strong> con el botón de la columna
                            <em>Oficial</em>, y eso deja la temporada sin ninguna versión vigente.</li>
                    </ul>
                    <div class="alert alert-danger">
                        <i class="fas fa-flag-checkered me-2"></i>
                        <strong>Desmarcar sin reemplazo:</strong> deja esa temporada <strong>sin versión
                        vigente</strong>, así que los sistemas que proyectan las compras del exterior van
                        a dejar de encontrar presupuesto para ella. El sistema lo avisa antes y el
                        desmarcado queda en el historial de oficiales como cualquier otro.
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Aviso al marcar:</strong> antes de confirmar, el sistema compara los tramos
                        que esta versión comparte con las otras oficiales vigentes del mismo país.
                        Calculadas el mismo día y sin tocar nada tienen que dar idéntico; si no dan, lo más
                        común es que se haya editado el índice de un rubro en <strong>una sola de las dos
                        solapas</strong>. El aviso muestra qué tramos difieren, en cuántas filas, por
                        cuántas unidades y qué rubros.
                        <p class="mb-0 mt-1">
                            <strong>Avisa, no bloquea.</strong> La diferencia puede ser deliberada; la
                            decisión es suya. Lo que no puede pasar es que el cashflow reciba dos números
                            para la misma temporada sin que nadie se entere.
                        </p>
                    </div>
                    <div class="alert alert-secondary small">
                        <i class="fas fa-database me-1"></i>
                        Cada versión guarda además el costo FOB y el porcentaje de nacionalización con los
                        que se calculó, y las unidades de OC pendientes que entraron al stock proyectado.
                        Sin eso la versión no se podría releer más adelante: los costos se pisan en el lugar
                        y las OC pendientes terminan ingresando.
                    </div>

                    <h5 class="card-title text-danger mt-3">
                        <i class="fas fa-trash me-2"></i>
                        Eliminar una versión
                    </h5>
                    <p>
                        El botón <i class="fas fa-trash text-danger"></i> de cada fila borra esa versión
                        completa: su cabecera, todas sus filas de detalle y su compra por tramo. Antes de
                        borrar, el sistema muestra cuántas filas se va a llevar.
                    </p>
                    <p><strong>Hay dos casos en los que el sistema se niega a borrar:</strong></p>
                    <ul>
                        <li>
                            <strong>Es la versión oficial.</strong> Borrarla dejaría una temporada sin
                            presupuesto sin que nadie se entere: el cashflow simplemente dejaría de
                            encontrarla. Hay que <strong>desmarcarla primero</strong>, y esa decisión
                            queda registrada.
                        </li>
                        <li>
                            <strong>Tiene historial de marcado.</strong> Si alguna vez fue marcada o
                            desmarcada como oficial, tiene filas en el historial de oficiales. Ese
                            historial es auditoría y <strong>no se borra</strong>, así que la versión
                            tampoco: borrarla se lo llevaría puesto.
                        </li>
                    </ul>
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Lo que sí se borra, no se puede deshacer.</strong> Una versión que nunca
                        fue oficial se elimina por completo y no hay forma de recuperarla.
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota Importante:</strong> La tabla de historial no carga datos automáticamente. Debe utilizar los filtros y hacer clic en "Buscar" para ver los resultados.
                    </div>
                    <h6>Filtros Disponibles:</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Presupuesto guardado:</strong> el primer desplegable. Es el que
                            sirve para <strong>ver una sola versión</strong> en lugar de todo el
                            historial mezclado. También se llega desde el botón
                            <i class="fas fa-filter"></i> de cada fila del panel de versiones.
                            Cambiarlo busca solo.
                        </li>
                        <li class="list-group-item"><strong>Búsqueda Rápida:</strong> Busque por texto en las columnas de rubro o categoría.</li>
                        <li class="list-group-item"><strong>Filtrar por Rubro:</strong> Escriba el nombre exacto de un rubro para acotar los resultados.</li>
                        <li class="list-group-item"><strong>Rango de Fechas:</strong> Seleccione una fecha de inicio y/o fin para ver los presupuestos guardados en ese período.</li>
                        <li class="list-group-item">
                            El botón <i class="fas fa-eraser"></i> limpia todos los filtros de una vez.
                        </li>
                    </ul>
                    <p class="mt-3">
                        Una vez que haya configurado sus filtros, presione el botón <button class="btn btn-primary btn-sm disabled"><i class="fas fa-search me-1"></i> Buscar</button> para cargar los datos en la tabla.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
