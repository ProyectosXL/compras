<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Autorizaciones OC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php
        $usuario_externo = isset($_GET['usuario']) ? htmlspecialchars($_GET['usuario'], ENT_QUOTES, 'UTF-8') : '';
    ?>

    <main class="container">
        <?php $es_rodrial = ($usuario_externo === 'RODRIAL'); ?>
        <!-- Pestaña 1: RESUMEN (Dashboard) -->
        <div id="tab-resumen" class="tab-content active">
            <div class="header"><h1>Resumen General</h1><p>Indicadores clave de tus órdenes de compra.</p></div>
            <div id="kpi-loader" class="info-card" style="text-align: center;">Cargando indicadores...</div>
            <div class="kpi-grid" id="kpi-container" style="display: none;"></div>
        </div>

        <!-- Pestaña 2: GESTIÓN (Autorizar/Rechazar/Derivar) -->
        <div id="tab-gestion" class="tab-content">
            <div class="header"><h1>Gestión de Pendientes</h1><p id="gestion-subtitulo"></p></div>
            <div class="filter-card" id="gestion-filtro-usuario" style="display: none;">
                <div class="form-group"><label for="autorizador-select">Tu Usuario Autorizador</label><select id="autorizador-select"></select></div>
                <button id="buscar-pendientes-btn" class="btn btn-primary">Buscar Mis Pendientes</button>
            </div>
            <div id="ordenes-container"></div>
        </div>

        <!-- Pestaña 3: CONSULTA (Monitor) -->
        <div id="tab-consulta" class="tab-content">
            <div class="header"><h1>Monitor de Órdenes</h1><p id="consulta-subtitulo"></p></div>
            <div class="filter-card">
                <div class="filtros">
                    <div class="form-group"><label for="filtro-estado">Estado</label><select id="filtro-estado"><option value="">Todos</option></select></div>
                    <div class="form-group"><label for="filtro-fecha-desde">Desde</label><input type="date" id="filtro-fecha-desde"></div>
                    <div class="form-group"><label for="filtro-fecha-hasta">Hasta</label><input type="date" id="filtro-fecha-hasta"></div>
                </div>
                <button id="btn-buscar-monitor" class="btn btn-primary">Buscar</button>
            </div>
            <table class="resultados-tabla" id="consulta-tabla"><tbody id="tabla-resultados-body"></tbody></table>
        </div>

        <!-- Pestaña 4: REGLAS (Exclusiva RODRIGOAL) -->
        <div id="tab-reglas" class="tab-content">
            <div class="header"><h1>Reglas de Derivación</h1><p>Configura los autorizadores automáticos por comprador y monto.</p></div>
            <div class="table-container" style="overflow-x: auto; background: white; border-radius: 12px; padding: 1rem; box-shadow: var(--shadow-sm);">
                <table class="resultados-tabla" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Comprador</th>
                            <th>Hasta $100k</th>
                            <th>Hasta $500k</th>
                            <th>Hasta $2M</th>
                            <th>Mayor $2M</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-reglas-body">
                        <tr><td colspan="5" style="text-align:center;">Cargando reglas...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <nav class="bottom-nav">
        <a href="#resumen" class="nav-item active"><i class="bi bi-pie-chart-fill"></i><span>Resumen</span></a>
        <a href="#gestion" class="nav-item"><i class="bi bi-card-checklist"></i><span>Gestión</span></a>
        <a href="#consulta" class="nav-item"><i class="bi bi-search"></i><span>Consulta</span></a>
        <?php if ($usuario_externo === 'RODRIGOAL'): ?>
        <a href="#reglas" class="nav-item"><i class="bi bi-gear-fill"></i><span>Reglas</span></a>
        <?php endif; ?>
    </nav>
    
    <!-- Modal para Derivación -->
    <div class="modal-overlay" id="derivar-modal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="derivar-modal-title"></h3></div>
            <div class="modal-body">
                <p id="derivar-modal-message"></p>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="derivar-usuario-select">Asignar a Usuario:</label>
                    <select id="derivar-usuario-select">
                        <option value="">Cargando autorizadores...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button id="derivar-cancelar-btn" class="btn btn-secondary btn-flex">Cancelar</button>
                <button id="derivar-confirmar-btn" class="btn btn-primary btn-flex">Confirmar Derivación</button>
            </div>
        </div>
    </div>
    
    <!-- Modal Genérico -->
    <div class="modal-overlay" id="generic-modal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modal-title"></h3></div>
            <div class="modal-body"><p id="modal-message"></p></div>
            <div class="modal-footer" id="modal-footer-buttons"></div>
        </div>
    </div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    let usuarioActivo = "<?php echo $usuario_externo; ?>";
    const esDispatcher = (usuarioActivo === 'RODRIAL');

    const navItems = document.querySelectorAll('.nav-item');
    const tabs = document.querySelectorAll('.tab-content');
    let allDerivaciones = []; // Almacén local para filtrado
    const formatCurrency = (amount) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(amount || 0);
    
    navItems.forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            const targetId = `tab-${item.getAttribute('href').substring(1)}`;
            navItems.forEach(i => i.classList.remove('active'));
            tabs.forEach(t => t.classList.remove('active'));
            item.classList.add('active');
            const targetTab = document.getElementById(targetId);
            if (targetTab) {
                targetTab.classList.add('active');
                if (!targetTab.dataset.loaded || targetId === 'tab-resumen' || targetId === 'tab-gestion') {
                    loadTabData(targetId);
                    targetTab.dataset.loaded = 'true';
                }
            }
        });
    });

    const loadTabData = (tabId) => {
        if (tabId === 'tab-resumen') loadResumenData();
        if (tabId === 'tab-gestion') loadGestionData();
        if (tabId === 'tab-consulta') loadConsultaData();
        if (tabId === 'tab-reglas') loadReglasData();
    };

    const loadReglasData = () => {
        const body = document.getElementById('tabla-reglas-body');
        body.innerHTML = '<tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>';
        
        Promise.all([
            fetch('api/get_reglas_autoc.php').then(r => r.json()),
            fetch('api/get_autorizadores.php').then(r => r.json())
        ]).then(([reglas, autorizadores]) => {
            body.innerHTML = '';
            reglas.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${r.COMPRADOR}</strong></td>
                    <td>${renderSelect(r.COMPRADOR, 'USR_HASTA_100K', r.USR_HASTA_100K, autorizadores)}</td>
                    <td>${renderSelect(r.COMPRADOR, 'USR_HASTA_500K', r.USR_HASTA_500K, autorizadores)}</td>
                    <td>${renderSelect(r.COMPRADOR, 'USR_HASTA_2M', r.USR_HASTA_2M, autorizadores)}</td>
                    <td>${renderSelect(r.COMPRADOR, 'USR_MAYOR_2M', r.USR_MAYOR_2M, autorizadores)}</td>
                `;
                body.appendChild(tr);
            });
        });
    };

    const renderSelect = (comprador, campo, valorActual, autorizadores) => {
        let options = '<option value="">-- Seleccionar --</option>';
        autorizadores.forEach(a => {
            options += `<option value="${a}" ${a === valorActual ? 'selected' : ''}>${a}</option>`;
        });
        return `<select class="regla-select" data-comprador="${comprador}" data-campo="${campo}" style="width:100%; padding:4px; border-radius:4px; border:1px solid #ddd;">${options}</select>`;
    };

    document.getElementById('tabla-reglas-body').addEventListener('change', (e) => {
        if (e.target.classList.contains('regla-select')) {
            const sel = e.target;
            const formData = new FormData();
            formData.append('comprador', sel.dataset.comprador);
            formData.append('campo', sel.dataset.campo);
            formData.append('valor', sel.value);
            formData.append('usuario_modifica', usuarioActivo);

            sel.style.backgroundColor = '#fff3cd'; // Indicador de "guardando"
            fetch('api/save_reglas_autoc.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        sel.style.backgroundColor = '#d4edda';
                        setTimeout(() => sel.style.backgroundColor = 'white', 1000);
                    } else {
                        alert('Error al guardar: ' + data.message);
                        sel.style.backgroundColor = '#f8d7da';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error de conexión');
                });
        }
    });

    const genericModal = document.getElementById('generic-modal');
    const showModal = (title, message, buttons) => {
        genericModal.querySelector('#modal-title').textContent = title;
        genericModal.querySelector('#modal-message').innerHTML = message;
        const footer = genericModal.querySelector('#modal-footer-buttons');
        footer.innerHTML = '';
        buttons.forEach(btnInfo => {
            const button = document.createElement('button');
            button.className = `btn btn-flex ${btnInfo.class}`;
            button.textContent = btnInfo.text;
            button.onclick = () => { genericModal.classList.remove('active'); if (btnInfo.callback) btnInfo.callback(); };
            footer.appendChild(button);
        });
        genericModal.classList.add('active');
    };

    const showConfirmationModal = (title, message, onConfirm) => {
        showModal(title, message, [
            { text: 'Cancelar', class: 'btn-secondary' },
            { text: 'Sí, Autorizar', class: 'btn-success', callback: onConfirm }
        ]);
    };
    
    const showRejectionModal = (title, message, onConfirm) => {
        const bodyWithMessage = `${message}<br><textarea id="rejection-reason" rows="3" placeholder="El motivo es obligatorio..."></textarea>`;
        showModal(title, bodyWithMessage, [
            { text: 'Cancelar', class: 'btn-secondary' },
            { text: 'Rechazar OC', class: 'btn-danger', callback: () => {
                const reasonInput = document.getElementById('rejection-reason');
                const reason = reasonInput.value.trim();
                if (reason) { 
                    onConfirm(reason); 
                } else { 
                    reasonInput.style.borderColor = 'var(--danger-color)'; 
                    reasonInput.focus();
                }
            }}
        ]);
    };

    const loadResumenData = () => {
        const kpiContainer = document.getElementById('kpi-container');
        const kpiLoader = document.getElementById('kpi-loader');
        kpiLoader.style.display = 'block'; kpiContainer.style.display = 'none';
        let url = `api/get_dashboard_stats.php`;
        if (usuarioActivo) { url += `?usuario=${encodeURIComponent(usuarioActivo)}`; }
        fetch(url).then(res => res.json()).then(stats => {
            kpiLoader.style.display = 'none'; kpiContainer.style.display = 'grid';
            kpiContainer.innerHTML = `
                <div class="kpi-card pending"><div class="kpi-card-info"><div class="value">${stats.pendientes_count || 0}</div><div class="label">${esDispatcher ? 'Por Asignar' : 'Mis Pendientes'}</div></div><div class="icon"><i class="bi bi-hourglass-split"></i></div></div>
                <div class="kpi-card amount"><div class="kpi-card-info"><div class="value">${formatCurrency(stats.pendientes_monto || 0)}</div><div class="label">Monto Pendiente</div></div><div class="icon"><i class="bi bi-cash-coin"></i></div></div>
                <div class="kpi-card authorized"><div class="kpi-card-info"><div class="value">${stats.autorizadas_hoy || 0}</div><div class="label">Autorizadas Hoy</div></div><div class="icon"><i class="bi bi-check-circle-fill"></i></div></div>
                <div class="kpi-card rejected"><div class="kpi-card-info"><div class="value">${stats.rechazadas_hoy || 0}</div><div class="label">Rechazadas Hoy</div></div><div class="icon"><i class="bi bi-x-circle-fill"></i></div></div>`;
        }).catch(err => { kpiLoader.textContent = 'Error al cargar indicadores.'; console.error(err); });
    };

    const gestionContainer = document.getElementById('ordenes-container');
    const derivarModal = document.getElementById('derivar-modal');
    const derivarTitle = document.getElementById('derivar-modal-title');
    const derivarMessage = document.getElementById('derivar-modal-message');
    const derivarSelect = document.getElementById('derivar-usuario-select');

    document.getElementById('derivar-cancelar-btn').addEventListener('click', () => derivarModal.classList.remove('active'));
    document.getElementById('derivar-confirmar-btn').addEventListener('click', () => {
        const usuarioAsignado = derivarSelect.value; const proveedorCodigo = derivarModal.dataset.codProvee;
        if (!usuarioAsignado) { return alert('Por favor, selecciona un usuario.'); }
        const formData = new FormData();
        formData.append('cod_provee', proveedorCodigo); formData.append('usuario_asignado', usuarioAsignado); formData.append('asignado_por', usuarioActivo);
        fetch('api/derivar_proveedor.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            derivarModal.classList.remove('active');
            if (data.status === 'success') { showModal('Éxito', data.message, [{ text: 'Aceptar', class: 'btn-success' }]); loadTabData('tab-gestion');
            } else { showModal('Error', data.message || 'Error.', [{ text: 'Cerrar', class: 'btn-danger' }]); }
        }).catch(err => { derivarModal.classList.remove('active'); console.error(err); showModal('Error de Conexión', 'No se pudo comunicar con el servidor.', [{ text: 'Cerrar', class: 'btn-danger' }]); });
    });

const buscarPendientesParaUsuario = (usuario) => {
    gestionContainer.innerHTML = '<div class="info-card">Buscando...</div>';
    fetch(`api/get_ordenes_pendientes_por_usuario.php?autorizador=${encodeURIComponent(usuario)}`).then(r => r.json()).then(ordenes => {
        gestionContainer.innerHTML = '';
        if (ordenes.length === 0) {
            const msg = esDispatcher ? 'No hay proveedores por asignar.' : '¡Felicidades! No tienes órdenes pendientes.';
            gestionContainer.innerHTML = `<div class="info-card">${msg}</div>`;
            return;
        }
        ordenes.forEach(oc => {
            const card = document.createElement('div');
            card.className = 'action-card';

            let observacionHtml = '';
            if (oc.observacion && oc.observacion.trim() !== '') {
                observacionHtml = `
                    <div class="action-card-detail">
                        <span class="label">Observación:</span>
                        <span class="value">${oc.observacion}</span>
                    </div>`;
            }

            const baseHtml = `
                <div class="action-card-header">
                    <strong>${oc.proveedor}</strong>
                </div>
                <div class="action-card-details">
                    <div class="action-card-detail">
                        <span class="label">OC / Fecha:</span>
                        <span class="value">${oc.numero} / ${oc.fecha}</span>
                    </div>
                    <div class="action-card-detail">
                        <span class="label">Comprador:</span>
                        <span class="value"><strong>${oc.comprador || 'N/A'}</strong></span>
                    </div>
                    ${observacionHtml}
                </div>
                <div class="action-card-monto">${formatCurrency(oc.monto)}</div>
            `;

            // Botones de Autorizar/Rechazar: Solo si NO es el dispatcher (RODRIAL)
            let actionButtonsHTML = '';
            if (!esDispatcher) {
                actionButtonsHTML = `
                <div class="action-card-buttons">
                    <button class="btn btn-danger btn-flex rechazar" data-oc="${oc.numero}">Rechazar</button>
                    <button class="btn btn-success btn-flex autorizar" data-oc="${oc.numero}">Autorizar</button>
                </div>`;
            }
            
            // Botón de Derivación: Solo si ES el dispatcher Y el proveedor no está asignado.
            let dispatcherButtonHTML = '';
            if (esDispatcher && oc.asignado == 0) {
                dispatcherButtonHTML = `
                    <div class="action-card-buttons" style="margin-top: 0.5rem;">
                        <button class="btn btn-secondary btn-flex derivar-btn" 
                                data-proveedor-nombre="${oc.proveedor}" 
                                data-cod-provee="${oc.cod_provee}">
                            Asignar Proveedor a Usuario
                        </button>
                    </div>`;
            }
            
            card.innerHTML = baseHtml + actionButtonsHTML + dispatcherButtonHTML;
            gestionContainer.appendChild(card);
        });
    }).catch(err => {
        gestionContainer.innerHTML = '<div class="info-card">Error al cargar pendientes.</div>';
        console.error('Error:', err);
    });
};

    const loadGestionData = () => {
        const subtituloGestion = document.getElementById('gestion-subtitulo'); const filtroUsuarioCard = document.getElementById('gestion-filtro-usuario');
        if (usuarioActivo) {
            filtroUsuarioCard.style.display = 'none';
            const subtitulo = esDispatcher ? 'Proveedores pendientes de asignación' : `Mostrando pendientes para: ${usuarioActivo}`;
            subtituloGestion.textContent = subtitulo;
            buscarPendientesParaUsuario(usuarioActivo);
        } else {
            filtroUsuarioCard.style.display = 'block'; subtituloGestion.textContent = 'Selecciona tu usuario para ver las OCs.'; gestionContainer.innerHTML = '';
            const gestionSelect = document.getElementById('autorizador-select');
            fetch('api/get_autorizadores.php').then(r => r.json()).then(data => {
                gestionSelect.innerHTML = '<option value="">-- Seleccionar --</option>';
                if (Array.isArray(data)) data.forEach(c => gestionSelect.innerHTML += `<option value="${c}">${c}</option>`);
            });
        }
    };
    
    document.getElementById('buscar-pendientes-btn').addEventListener('click', () => {
        usuarioActivo = document.getElementById('autorizador-select').value;
        if (usuarioActivo) { loadGestionData(); document.getElementById('tab-resumen').dataset.loaded = 'false'; document.getElementById('tab-consulta').dataset.loaded = 'false';
        } else { alert('Por favor, selecciona un usuario.'); }
    });

    gestionContainer.addEventListener('click', e => {
        if (e.target.classList.contains('derivar-btn')) {
            const boton = e.target;
            derivarModal.dataset.codProvee = boton.dataset.codProvee;
            derivarTitle.textContent = `Derivar Proveedor`;
            derivarMessage.innerHTML = `Asignar permanentemente <strong>${boton.dataset.proveedorNombre}</strong> a un usuario:`;
            derivarSelect.innerHTML = '<option value="">Cargando...</option>';
            fetch('api/get_autorizadores.php').then(r => r.json()).then(users => {
                derivarSelect.innerHTML = '<option value="">-- Seleccionar --</option>';
                users.forEach(user => { if (user !== 'RODRIAL') { derivarSelect.innerHTML += `<option value="${user}">${user}</option>`; }});
            });
            derivarModal.classList.add('active');
            return;
        }
        
        const isAutorizar = e.target.classList.contains('autorizar'); const isRechazar = e.target.classList.contains('rechazar');
        if (!isAutorizar && !isRechazar) return;
        const boton = e.target; const numeroOC = boton.dataset.oc;

        const ejecutarAccion = (endpoint, formData) => {
            boton.textContent = '...'; boton.disabled = true;
            fetch(endpoint, { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if (data.status && data.status.includes('success')) {
                    showModal('Éxito', data.message, [{ text: 'Aceptar', class: 'btn-success' }]);
                    loadTabData('tab-gestion');
                    document.getElementById('tab-resumen').dataset.loaded = 'false';
                } else {
                    showModal('Error', data.message || 'Error inesperado.', [{ text: 'Cerrar', class: 'btn-danger' }]);
                    boton.textContent = isAutorizar ? 'Autorizar' : 'Rechazar';
                    boton.disabled = false;
                }
            }).catch(err => { console.error(err); showModal('Error de Red', err.message, [{ text: 'Cerrar', class: 'btn-danger'}]); });
        };

        if (isAutorizar) {
            showConfirmationModal(`Confirmar Autorización`, `¿Estás seguro de autorizar la OC Nro. ${numeroOC}?`, () => {
                const formData = new FormData();
                formData.append('n_orden_co', numeroOC); formData.append('usuario_autoriza', usuarioActivo);
                ejecutarAccion('api/autorizar_orden.php', formData);
            });
        } else if (isRechazar) {
            showRejectionModal('Motivo del Rechazo', `Ingresa un motivo para rechazar la OC Nro. ${numeroOC}:`, (motivo) => {
                const formData = new FormData();
                formData.append('n_orden_co', numeroOC); formData.append('usuario_rechaza', usuarioActivo); formData.append('motivo', motivo);
                ejecutarAccion('api/rechazar_orden.php', formData);
            });
        }
    });

    const loadConsultaData = () => {
        const consultaSubtitulo = document.getElementById('consulta-subtitulo');
        if (usuarioActivo && !esDispatcher) { consultaSubtitulo.textContent = `Busca en las OCs donde ${usuarioActivo} estuvo involucrado.`;
        } else { consultaSubtitulo.textContent = 'Busca en el historial global de OCs.'; }
        const consultaEstadoSelect = document.getElementById('filtro-estado');
        const estados = { 1: 'Ingresada', 2: 'Autorizada', 4: 'Desautorizada', 10: 'Cumplida', 11: 'Cerrada' };
        consultaEstadoSelect.innerHTML = '<option value="">Todos</option>';
        for (const id in estados) { consultaEstadoSelect.innerHTML += `<option value="${id}">${estados[id]}</option>`; }
        document.getElementById('tabla-resultados-body').innerHTML = '<tr><td colspan="6" style="text-align:center;">Usa los filtros para buscar.</td></tr>';
    };

    document.getElementById('btn-buscar-monitor').addEventListener('click', () => {
        const consultaTbody = document.getElementById('tabla-resultados-body');
        consultaTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Buscando...</td></tr>';
        const params = new URLSearchParams();
        if (usuarioActivo && !esDispatcher) { params.append('usuario_involucrado', usuarioActivo); }
        if (document.getElementById('filtro-estado').value) { params.append('estado', document.getElementById('filtro-estado').value); }
        if (document.getElementById('filtro-fecha-desde').value) { params.append('fecha_desde', document.getElementById('filtro-fecha-desde').value); }
        if (document.getElementById('filtro-fecha-hasta').value) { params.append('fecha_hasta', document.getElementById('filtro-fecha-hasta').value); }
        
        fetch(`api/buscar_ordenes.php?${params.toString()}`).then(r => r.json()).then(data => {
            consultaTbody.innerHTML = '';
            if (!data || data.length === 0) { consultaTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No se encontraron resultados.</td></tr>'; return; }
            data.forEach(oc => {
                const statusClass = `status-${(oc.estado_desc || '').split(' ')[0].toLowerCase().replace('y', '')}`;
                let observacionHtml = ''; if(oc.observacion && oc.observacion.trim() !== '') { observacionHtml = `<td data-label="Observación">${oc.observacion}</td>`; }
                consultaTbody.innerHTML += `<tr><td data-label="OC / Fecha"><strong>${oc.numero}</strong><small style="display:block;">${oc.fecha}</small></td><td data-label="Proveedor">${oc.proveedor}</td><td data-label="Comprador">${oc.comprador}</td><td data-label="Estado"><span class="status ${statusClass}">${oc.estado_desc || 'N/A'}</span></td>${observacionHtml}<td data-label="Monto" style="font-weight:700;">${formatCurrency(oc.monto)}</td></tr>`;
            });
        }).catch(err => { console.error(err); consultaTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Error al cargar los datos.</td></tr>'; });
    });

    const loadDerivacionesData = () => {
        const tbody = document.getElementById('tabla-derivaciones-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Buscando...</td></tr>';
        fetch('api/get_derivaciones.php').then(r => r.json()).then(data => {
            allDerivaciones = data;
            renderDerivaciones(allDerivaciones);
        }).catch(err => { console.error(err); tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Error al cargar los datos.</td></tr>'; });
    };

    const renderDerivaciones = (data) => {
        const tbody = document.getElementById('tabla-derivaciones-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!data || data.length === 0) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No se encontraron resultados.</td></tr>'; return; }
        data.forEach(d => {
            tbody.innerHTML += `
                <tr>
                    <td data-label="Proveedor"><strong>${d.COD_PROVEE}</strong><br><small>${d.proveedor || 'N/A'}</small></td>
                    <td data-label="Usuario Asignado"><span class="status status-autorizada" style="background:var(--primary-color);color:white;">${d.usuario}</span></td>
                    <td data-label="Asignado Por"><small>${d.ASIGNADO_POR}</small></td>
                    <td data-label="Fecha">${d.fecha}</td>
                </tr>`;
        });
    };

    if (esDispatcher) {
        document.getElementById('btn-actualizar-derivaciones')?.addEventListener('click', loadDerivacionesData);
        document.getElementById('buscar-derivacion')?.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const filtered = allDerivaciones.filter(d => 
                (d.COD_PROVEE || '').toLowerCase().includes(query) ||
                (d.proveedor || '').toLowerCase().includes(query) ||
                (d.usuario || '').toLowerCase().includes(query) ||
                (d.ASIGNADO_POR || '').toLowerCase().includes(query)
            );
            renderDerivaciones(filtered);
        });
    }
    
    // Carga inicial
    loadTabData('tab-resumen');
});
</script>
</body>
</html>