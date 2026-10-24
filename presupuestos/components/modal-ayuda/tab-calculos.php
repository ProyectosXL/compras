
<div class="tab-pane fade" id="calculos" role="tabpanel">
    <div class="accordion" id="calculosAccordion">
        <!-- Convención de nombres -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConvencion">
                    <i class="fas fa-tags me-2"></i>
                    Cómo se nombran las temporadas
                </button>
            </h2>
            <div id="collapseConvencion" class="accordion-collapse collapse show" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <p class="small">
                        Toda la aplicación usa <strong>una sola convención</strong>: el encabezado, las columnas
                        históricas, las columnas proyectadas, el historial y el Excel dicen lo mismo.
                        Es la misma numeración que usan los códigos de oleada de Comercio Exterior
                        (<code>VER01-26</code>, <code>INV01-27</code>).
                    </p>
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div class="card bg-warning-subtle h-100">
                                <div class="card-body p-2">
                                    <h6 class="small"><i class="fas fa-sun text-warning me-2"></i>Verano: <code>VER AA-AA</code></h6>
                                    <p class="small mb-1">Lleva los dos años porque la temporada cruza el año calendario.</p>
                                    <p class="small mb-0"><code>VER 26-27</code> = 01/08/2026 al 31/01/2027</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card bg-info-subtle h-100">
                                <div class="card-body p-2">
                                    <h6 class="small"><i class="fas fa-snowflake text-info me-2"></i>Invierno: <code>INV AA</code></h6>
                                    <p class="small mb-1">Un solo año, porque empieza y termina dentro del mismo.</p>
                                    <p class="small mb-0"><code>INV 27</code> = 01/02/2027 al 31/07/2027</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-2 p-2 mb-0">
                        <strong class="small">⚠️ "Resto":</strong>
                        <span class="small">
                            Si una columna dice <code>Resto VER 26-27</code>, cubre solo los días que faltan
                            de esa temporada, no la temporada entera. Cuando la etiqueta suma dos tramos
                            (<code>Resto VER 26-27 + VER 27-28</code>) el número de la celda es la suma de los dos.
                            Pasando el mouse por el encabezado se ven las fechas exactas.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Índice LP vs. Total Empresa -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIndices">
                    <i class="fas fa-percent me-2"></i>
                    Índice Locales Propios e índice Total Empresa
                </button>
            </h2>
            <div id="collapseIndices" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <p class="small">
                        La venta proyectada es la <strong>venta histórica</strong> de la temporada multiplicada
                        por un <strong>índice de variación</strong>. Los dos índices comparan los últimos 60 días
                        (hasta ayer) contra los mismos 60 días del año anterior; cambia el universo:
                    </p>
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-6">
                            <div class="card bg-secondary-subtle h-100">
                                <div class="card-body p-2">
                                    <h6 class="small">Índice LP <span class="text-muted">(Locales Propios)</span></h6>
                                    <p class="small mb-0">Solo el canal Locales Propios. Queda como <strong>termómetro</strong>:
                                       se ve pero no mueve la proyección y no se edita.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card bg-warning-subtle h-100">
                                <div class="card-body p-2">
                                    <h6 class="small">Índice Total Empresa</h6>
                                    <p class="small mb-0">Locales Propios + Central, el <strong>mismo universo que la venta
                                       histórica</strong>. Es el que mueve la venta proyectada, la compra y los tramos, y el que se edita.
                                       Verano e invierno arrancan con el mismo valor y se editan por separado.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="small">
                        <strong>Índice Var. Original</strong> es el valor del índice editable tal como vino, antes de
                        tocarlo: es el valor al que vuelve "restaurar" (la ×). Arranca oculta; se muestra desde el
                        botón <em>Columnas</em>. Cuando el año anterior no tuvo venta el índice viene en 0 y la
                        proyección usa 1,00.
                    </p>
                    <h6 class="small"><i class="fas fa-calculator me-2"></i>Índice general</h6>
                    <p class="small">
                        En la fila TOTALES, debajo de cada columna de índice, va el índice <strong>general</strong> de
                        las filas visibles: <code>suma venta 60 días ÷ suma venta mismos 60 días del año anterior</code>.
                        Es ponderado por venta (no un promedio de índices), se recalcula al filtrar o buscar y
                        <strong>no cambia al editar índices</strong>: refleja la venta real y sirve de referencia para
                        comparar lo que se editó.
                    </p>
                    <div class="alert alert-info p-2 mb-0">
                        <span class="small">
                            En <strong>Uruguay</strong> (y en Argentina mientras no se haya corrido el script 06 del SP)
                            no hay índice Total Empresa: la columna muestra "—", el índice LP sigue siendo el editable y
                            mueve la proyección como siempre.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Temporada en curso: real + proyectada -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEnCurso">
                    <i class="fas fa-hourglass-half me-2"></i>
                    Venta de la temporada en curso: real + proyectada
                </button>
            </h2>
            <div id="collapseEnCurso" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <p class="small">
                        Al lado de la <em>Venta Proy.</em> del tipo de la temporada que se está transitando hay tres columnas:
                    </p>
                    <ul class="small">
                        <li><strong>Vendido</strong> (celeste): venta <strong>real</strong> desde el primer día de la temporada
                            hasta la fecha que dice el encabezado ("al dd/mm"), que es el día anterior a la última corrida del SP.</li>
                        <li><strong>Resto</strong> (azul): venta <strong>proyectada</strong> desde hoy hasta el fin de la temporada,
                            con el índice de esa temporada. Es el mismo tramo "Resto" que ya está dentro de la venta proyectada y de la compra.</li>
                        <li><strong>Total</strong>: Vendido + Resto.</li>
                    </ul>
                    <p class="small mb-2">
                        Es <strong>informativo</strong>: no entra en la compra ni en los tramos. Se actualiza al editar el índice de esa temporada.
                    </p>
                    <div class="alert alert-warning p-2 mb-0">
                        <span class="small">
                            <i class="fas fa-exclamation-triangle text-warning"></i> Si el SP no corrió hoy, entre la fecha de corte
                            y hoy quedan días que no están ni en Vendido ni en Resto: el encabezado lo avisa con la cantidad de días.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- OC pendientes y columnas -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseColumnas">
                    <i class="fas fa-columns me-2"></i>
                    OC pendientes, ordenar y ocultar columnas
                </button>
            </h2>
            <div id="collapseColumnas" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <p class="small">
                        Las <strong>OC pendientes</strong> se ven a la derecha del Stock Proyectado. El botón <em>OC</em> alterna
                        entre una sola columna (la suma) y una por temporada (verano, invierno, atemporal). Ya están
                        <strong>dentro</strong> del Stock Proyectado: la compra proyectada es neta de lo pedido.
                    </p>
                    <p class="small mb-0">
                        Clic en un encabezado ordena: ascendente, descendente y vuelta al orden original (▲ ▼). La fila
                        TOTALES queda siempre al final. El botón <em>Columnas</em> permite ocultar columnas; la elección se
                        recuerda en este navegador. El <strong>Excel exporta siempre todas</strong>, ocultas o no.
                    </p>
                </div>
            </div>
        </div>

        <!-- Stock Proyectado -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStock">
                    <i class="fas fa-boxes me-2"></i>
                    Stock Proyectado
                </button>
            </h2>
            <div id="collapseStock" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>Fórmula:</strong>
                        <code class="d-block small">STOCK + COMPRAS VERANO + COMPRAS INVIERNO + COMPRA ATEMPORAL + STOCK GUARDAR - STOCK IDEAL</code>
                    </div>
                    <p><strong>Descripción:</strong> Calcula el stock total proyectado considerando todas las entradas y salidas previstas.</p>
                    <ul class="small">
                        <li><strong>Stock:</strong> Unidades disponibles (total - comprometido)</li>
                        <li><strong>Compras por temporada:</strong> Órdenes pendientes de ingreso</li>
                        <li><strong>Stock guardar:</strong> Unidades reservadas en central</li>
                        <li><strong>Stock ideal:</strong> Cantidad de reserva necesaria</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Stock Reserva -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReserva">
                    <i class="fas fa-shield-alt me-2"></i>
                    Stock Reserva
                </button>
            </h2>
            <div id="collapseReserva" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>Fórmula:</strong>
                        <code>Promedio venta últimos 12 meses × 1.5</code>
                    </div>
                    <p><strong>Descripción:</strong> Calcula el stock de seguridad necesario para cubrir mes y medio de ventas.</p>
                    <div class="row">
                        <div class="col-lg-6 mb-2">
                            <h6 class="text-primary">Propósito:</h6>
                            <ul class="small">
                                <li>Evitar agotamiento de stock</li>
                                <li>Cubrir variaciones de demanda</li>
                                <li>Garantizar disponibilidad</li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="text-warning">Factor 1.5:</h6>
                            <p class="small">Representa 1.5 meses de cobertura basado en el promedio mensual de ventas del último año.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ventas Proyectadas -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVentas">
                    <i class="fas fa-chart-line me-2"></i>
                    Cálculo de Ventas Proyectadas
                </button>
            </h2>
            <div id="collapseVentas" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>📊 Lógica de Cálculo por Solapa:</strong><br>
                        <small>El sistema aplica diferentes métodos de cálculo según la solapa actual y la temporada en curso.</small>
                    </div>
                    
                    <p class="small">
                        Qué período cubre cada columna, según la solapa y la temporada en curso.
                        Los ejemplos están tomados parándose en <strong>septiembre de 2026</strong>
                        (transitando <code>VER 26-27</code>) y en <strong>marzo de 2027</strong>
                        (transitando <code>INV 27</code>):
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Solapa</th>
                                    <th>Temporada en curso</th>
                                    <th>Columna Venta Proy. Verano</th>
                                    <th>Columna Venta Proy. Invierno</th>
                                    <th>La compra cubre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td rowspan="2"><i class="fas fa-sun text-warning me-1"></i>Verano</td>
                                    <td><code>VER 26-27</code></td>
                                    <td><code>Resto VER 26-27</code> + <code>VER 27-28</code></td>
                                    <td><code>INV 27</code></td>
                                    <td><span class="badge bg-warning text-dark">VER 27-28</span></td>
                                </tr>
                                <tr>
                                    <td><code>INV 27</code></td>
                                    <td><code>VER 27-28</code></td>
                                    <td><code>Resto INV 27</code></td>
                                    <td><span class="badge bg-warning text-dark">VER 27-28</span></td>
                                </tr>
                                <tr>
                                    <td rowspan="2"><i class="fas fa-snowflake text-info me-1"></i>Invierno</td>
                                    <td><code>VER 26-27</code></td>
                                    <td><code>Resto VER 26-27</code></td>
                                    <td><code>INV 27</code></td>
                                    <td><span class="badge bg-info text-dark">INV 27</span></td>
                                </tr>
                                <tr>
                                    <td><code>INV 27</code></td>
                                    <td><code>VER 27-28</code></td>
                                    <td><code>Resto INV 27</code> + <code>INV 28</code></td>
                                    <td><span class="badge bg-info text-dark">INV 28</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-success p-2">
                        <strong class="small">🎯 Para qué temporada es la compra:</strong>
                        <span class="small">
                            Cada solapa compra para la <strong>próxima temporada de su tipo</strong>.
                            Las dos columnas proyectadas cubren juntas un período continuo desde hoy
                            hasta que esa temporada termina, y la compra es lo que falta para llegar
                            hasta ahí. Es la temporada en la que tienen que estar los contenedores.
                        </span>
                    </div>

                    <h6 class="mt-2 small"><i class="fas fa-calculator me-2"></i>Fórmulas de Cálculo:</h6>
                    
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <strong class="small">Temporada completa:</strong>
                            <code class="d-block bg-light p-1 mt-1 small">
                                Venta Anterior × Índice Variación
                            </code>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong class="small">Resto de temporada:</strong>
                            <code class="d-block bg-light p-1 mt-1 small">
                                Venta Anterior × Índice × (Días Restantes / Días Totales)
                            </code>
                        </div>
                    </div>

                    <p class="small mt-2 mb-1">
                        <strong>Venta Anterior</strong> es la última temporada del mismo tipo con ventas.
                        El encabezado de cada columna "Anterior" dice cuál es.
                    </p>

                    <div class="mt-2">
                        <strong class="small">Temporadas:</strong>
                        <ul class="small mb-1">
                            <li><strong>Verano:</strong> 1° de agosto al 31 de enero (<code>VER AA-AA</code>)</li>
                            <li><strong>Invierno:</strong> 1° de febrero al 31 de julio (<code>INV AA</code>)</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning mt-2 p-2">
                        <strong class="small">⚠️ Importante:</strong>
                        <span class="small">
                            Los cálculos se ajustan según la fecha y la solapa. Los días restantes los
                            calcula el servidor: dan lo mismo a cualquier hora del día y no dependen del
                            reloj de la computadora.
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Compra Proyectada -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCompra">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Compra Proyectada
                </button>
            </h2>
            <div id="collapseCompra" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <strong>Fórmula:</strong>
                        <code class="small">Stock proyectado - Venta proyectada verano - Venta proyectada invierno</code>
                    </div>
                    <p class="small"><strong>Descripción:</strong> Determina la cantidad adicional que debe comprarse para cubrir la demanda proyectada.</p>
                    
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center p-2">
                                    <h6 class="small">Resultado > 0</h6>
                                    <p class="mb-0 small">Exceso de stock</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center p-2">
                                    <h6 class="small">Resultado = 0</h6>
                                    <p class="mb-0 small">Stock equilibrado</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center p-2">
                                    <h6 class="small">Resultado < 0</h6>
                                    <p class="mb-0 small">Necesidad de compra</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-2 p-2">
                        <strong class="small">📊 Los dos totales de la pantalla no son lo mismo</strong>
                        <ul class="small mb-0 mt-1">
                            <li>
                                <strong>Unidades a comprar</strong> (barra de arriba): suma
                                <strong>solo los faltantes</strong>, o sea los rubros con compra
                                proyectada negativa. Es lo que hay que pedir.
                            </li>
                            <li>
                                <strong>Neto de la columna</strong> (fila TOTALES al pie de la tabla):
                                faltantes <strong>menos</strong> excedentes. Es siempre menor, porque
                                los rubros que sobran compensan a los que faltan.
                            </li>
                        </ul>
                        <p class="small mb-0 mt-1">
                            No se compensan entre sí para comprar: que sobren camperas no evita
                            tener que comprar ojotas.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compra por tramo -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTramos">
                    <i class="fas fa-layer-group me-2"></i>
                    Compra por tramo
                </button>
            </h2>
            <div id="collapseTramos" class="accordion-collapse collapse" data-bs-parent="#calculosAccordion">
                <div class="accordion-body">

                    <div class="alert alert-info p-2">
                        <strong class="small">Por qué un solo número no alcanza</strong>
                        <p class="small mb-0 mt-1">
                            La compra proyectada cubre <strong>toda la ventana de la solapa</strong>.
                            Transitando verano, la solapa verano cubre el resto de VER 26-27,
                            INV 27 y VER 27-28 juntas. Eso sirve para decidir cuánto comprar, pero
                            lo de cada temporada llega en <strong>contenedores distintos y en meses
                            distintos</strong>, así que se paga en meses distintos. Las columnas de
                            tramo abren ese total por temporada.
                        </p>
                    </div>

                    <h6 class="small mt-3"><i class="fas fa-arrow-right-long me-2"></i>Cómo se reparte</h6>
                    <p class="small">
                        El stock proyectado es un <strong>pozo único</strong> que se consume en
                        <strong>orden cronológico</strong>: cada tramo toma lo que puede del stock
                        que quedó, y lo que no alcanza a cubrirse es la compra de ese tramo. El
                        stock que hay hoy cubre primero lo que se vende primero.
                    </p>
                    <div class="alert alert-secondary p-2">
                        <code class="small">
                            suma de las compras por tramo = lo que muestra "Unidades a comprar"
                        </code>
                        <p class="small mb-0 mt-1">
                            Los rubros con excedente dan <strong>cero en todos los tramos</strong>:
                            un sobrante no se reparte.
                        </p>
                    </div>

                    <h6 class="small mt-3"><i class="fas fa-tags me-2"></i>Los tres tipos de columna</h6>
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body p-2">
                                    <h6 class="small mb-1">★ Compra (objetivo)</h6>
                                    <p class="mb-0 small">
                                        La temporada que esta solapa tiene que cubrir. Es lo único
                                        que esta versión le aporta al cashflow.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card border h-100">
                                <div class="card-body p-2">
                                    <h6 class="small mb-1">Compra (intermedio)</h6>
                                    <p class="mb-0 small">
                                        Una temporada que queda en el medio. Queda como
                                        <strong>control</strong>: esa temporada la aporta su propia
                                        versión oficial, para que no se cuente dos veces.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card bg-danger text-white h-100">
                                <div class="card-body p-2">
                                    <h6 class="small mb-1">Sin cubrir</h6>
                                    <p class="mb-0 small">
                                        El resto de la temporada en curso. <strong>No es mercadería
                                        a comprar</strong>: un contenedor nuevo ya no llega a
                                        tiempo. Es venta que va a quedar sin cobertura.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="small mt-3"><i class="fas fa-shield-halved me-2"></i>Déficit de stock de cobertura</h6>
                    <p class="small">
                        Cuando el stock de seguridad supera a todo lo disponible, el stock
                        proyectado arranca en negativo (por ejemplo <code>0 − 309 = −309</code>).
                        Ese déficit <strong>no</strong> se le carga al resto de la temporada en
                        curso, porque ahí no se puede comprar y se perdería: se imputa al
                        <strong>primer tramo que todavía se puede comprar</strong>, que es el
                        contenedor más cercano sobre el que se puede actuar. El tooltip de la celda
                        aclara cuánto de esa compra es déficit y no venta proyectada.
                    </p>

                    <div class="alert alert-warning p-2 mb-0">
                        <strong class="small">⚠️ Editar un índice en una sola solapa</strong>
                        <p class="small mb-0 mt-1">
                            Los índices se editan por solapa. Verano e invierno comparten tramos, y
                            si se corrige un rubro en una sola, las dos versiones dejan de coincidir
                            sobre la misma temporada. Al marcar una versión como oficial el sistema
                            lo avisa y muestra qué rubros difieren.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>