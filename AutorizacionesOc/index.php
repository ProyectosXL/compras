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
        <!-- Pestaña 1: RESUMEN (Dashboard) -->
        <div id="tab-resumen" class="tab-content active">
            <div class="header"><h1>Resumen General</h1><p>Indicadores clave de tus órdenes de compra.</p></div>
            <div id="kpi-loader" class="info-card" style="text-align: center;">Cargando indicadores...</div>
            <div class="kpi-grid" id="kpi-container" style="display: none;"></div>
        </div>

        <!-- Pestaña 2: GESTIÓN (Autorizar/Rechazar) -->
        <div id="tab-gestion" class="tab-content">
            <div class="header"><h1>Gestión de Pendientes</h1><p id="gestion-subtitulo">Selecciona tu usuario para ver las OCs que te corresponden.</p></div>
            <div class="filter-card" id="gestion-filtro-usuario">
                <div class="form-group"><label for="autorizador-select">Tu Usuario Autorizador</label><select id="autorizador-select"><option value="">Cargando...</option></select></div>
                <button id="buscar-pendientes-btn" class="btn btn-primary">Buscar Mis Pendientes</button>
            </div>
            <div id="ordenes-container"></div>
        </div>

        <!-- Pestaña 3: CONSULTA (Monitor) -->
        <div id="tab-consulta" class="tab-content">
            <div class="header">
                <h1>Monitor de Órdenes</h1>
                <p id="consulta-subtitulo">Busca en el historial global de OCs.</p>
            </div>
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
    </main>

    <!-- Barra de Navegación Inferior -->
    <nav class="bottom-nav">
        <a href="#resumen" class="nav-item active"><i class="bi bi-pie-chart-fill"></i><span>Resumen</span></a>
        <a href="#gestion" class="nav-item"><i class="bi bi-card-checklist"></i><span>Gestión</span></a>
        <a href="#consulta" class="nav-item"><i class="bi bi-search"></i><span>Consulta</span></a>
    </nav>
    
    <!-- Modal Genérico para Confirmaciones y Notificaciones -->
    <div class="modal-overlay" id="generic-modal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modal-title"></h3></div>
            <div class="modal-body"><p id="modal-message"></p></div>
            <div class="modal-footer" id="modal-footer-buttons"></div>
        </div>
    </div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    // ---- VARIABLE GLOBAL PARA "RECORDAR" AL USUARIO ACTIVO EN TODA LA APP ----
    let usuarioActivo = "<?php echo $usuario_externo; ?>";

    // ---- VARIABLES DEL DOM ----
    const navItems = document.querySelectorAll('.nav-item');
    const tabs = document.querySelectorAll('.tab-content');
    const formatCurrency = (amount) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(amount);
    
    // --- LÓGICA DE NAVEGACIÓN POR PESTAÑAS ----
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
                if (!targetTab.dataset.loaded || targetId === 'tab-resumen') { // Siempre recargar el resumen
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
    };

    // --- LÓGICA DE MODALES PERSONALIZADOS ---
    const modalOverlay = document.getElementById('generic-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalMessage = document.getElementById('modal-message');
    const modalFooter = document.getElementById('modal-footer-buttons');
    const showModal = (title, message, buttons) => {
        modalTitle.textContent = title;
        modalMessage.innerHTML = message;
        modalFooter.innerHTML = '';
        buttons.forEach(btnInfo => {
            const button = document.createElement('button');
            button.className = `btn btn-flex ${btnInfo.class}`;
            button.textContent = btnInfo.text;
            button.onclick = () => { modalOverlay.classList.remove('active'); if (btnInfo.callback) btnInfo.callback(); };
            modalFooter.appendChild(button);
        });
        modalOverlay.classList.add('active');
    };
    const showConfirmationModal = (title, message, onConfirm) => {
        showModal(title, message, [{ text: 'Cancelar', class: 'btn-secondary' }, { text: 'Sí, Autorizar', class: 'btn-success', callback: onConfirm }]);
    };
    const showRejectionModal = (title, message, onConfirm) => {
        const bodyWithMessage = `${message}<br><textarea id="rejection-reason" rows="3" placeholder="El motivo es obligatorio..."></textarea>`;
        showModal(title, bodyWithMessage, [{ text: 'Cancelar', class: 'btn-secondary' }, { text: 'Rechazar OC', class: 'btn-danger', callback: () => {
            const reasonInput = document.getElementById('rejection-reason');
            const reason = reasonInput.value.trim();
            if (reason) { onConfirm(reason); } 
            else { reasonInput.style.borderColor = 'var(--danger-color)'; }
        }}]);
    };

    // --- PESTAÑA 1: RESUMEN ----
    const kpiContainer = document.getElementById('kpi-container');
    const kpiLoader = document.getElementById('kpi-loader');
    const loadResumenData = () => {
        kpiLoader.style.display = 'block'; kpiContainer.style.display = 'none';
        let url = `api/get_dashboard_stats.php`;
        if (usuarioActivo) { url += `?usuario=${encodeURIComponent(usuarioActivo)}`; }
        fetch(url).then(res => res.json()).then(stats => {
            kpiLoader.style.display = 'none'; kpiContainer.style.display = 'grid';
            kpiContainer.innerHTML = `
                <div class="kpi-card pending">
                    <div class="kpi-card-info"><div class="value">${stats.pendientes_count || 0}</div><div class="label">${stats.pendientes_count === 1 ? 'Pendiente' : 'Pendientes'}</div></div>
                    <div class="icon"><i class="bi bi-hourglass-split"></i></div>
                </div>
                <div class="kpi-card amount">
                    <div class="kpi-card-info"><div class="value">${formatCurrency(stats.pendientes_monto || 0)}</div><div class="label">Monto Pendiente</div></div>
                    <div class="icon"><i class="bi bi-cash-coin"></i></div>
                </div>
                <div class="kpi-card authorized">
                    <div class="kpi-card-info"><div class="value">${stats.autorizadas_hoy || 0}</div><div class="label">Autorizadas Hoy</div></div>
                    <div class="icon"><i class="bi bi-check-circle-fill"></i></div>
                </div>
                <div class="kpi-card rejected">
                    <div class="kpi-card-info"><div class="value">${stats.rechazadas_hoy || 0}</div><div class="label">Rechazadas Hoy</div></div>
                    <div class="icon"><i class="bi bi-x-circle-fill"></i></div>
                </div>`;
            if (usuarioActivo) kpiContainer.querySelector('.kpi-card.pending .label').textContent = 'Mis Pendientes';
            else kpiContainer.querySelector('.kpi-card.pending .label').textContent = 'Pendientes (Global)';
        }).catch(err => { kpiLoader.textContent = 'Error al cargar indicadores.'; console.error(err); });
    };

    // --- PESTAÑA 2: GESTIÓN ----
    const gestionSelect = document.getElementById('autorizador-select');
    const gestionBtn = document.getElementById('buscar-pendientes-btn');
    const gestionContainer = document.getElementById('ordenes-container');
    const filtroUsuarioCard = document.getElementById('gestion-filtro-usuario');
    const subtituloGestion = document.getElementById('gestion-subtitulo');
    const buscarPendientesParaUsuario = (usuario) => {
        if (!usuario) { alert("Nombre de usuario no válido."); return; }
        gestionContainer.innerHTML = '<div class="info-card">Buscando tus órdenes pendientes...</div>';
        fetch(`api/get_ordenes_pendientes_por_usuario.php?autorizador=${encodeURIComponent(usuario)}`).then(r => r.json()).then(ordenes => {
            gestionContainer.innerHTML = '';
            if (ordenes.length === 0) { gestionContainer.innerHTML = '<div class="info-card">¡Felicidades! No tienes órdenes pendientes de autorizar.</div>'; return; }
            ordenes.forEach(oc => {
                const card = document.createElement('div'); card.className = 'action-card'; card.id = `oc-${oc.numero}`;
                let observacionHtml = '';
                if (oc.observacion && oc.observacion.trim() !== '') { observacionHtml = `<div class="action-card-header" style="font-size:0.8rem; padding-top: 0.5rem; margin-top: 0.5rem; border-top: 1px solid var(--border-color)"><div class="info"><strong>Observación:</strong><span>${oc.observacion}</span></div></div>`; }
                card.innerHTML = `<div class="action-card-header"><div class="info"><strong>${oc.proveedor}</strong><span>OC: ${oc.numero} / Fecha: ${oc.fecha}</span></div></div>${observacionHtml}<div class="action-card-monto">${formatCurrency(oc.monto)}</div><div class="action-card-buttons"><button class="btn btn-danger btn-flex rechazar" data-oc="${oc.numero}">Rechazar</button><button class="btn btn-success btn-flex autorizar" data-oc="${oc.numero}">Autorizar</button></div>`;
                gestionContainer.appendChild(card);
            });
        }).catch(error => { gestionContainer.innerHTML = '<div class="info-card">Error al cargar las órdenes pendientes.</div>'; console.error('Error:', error); });
    };
    const loadGestionData = () => {
        if (usuarioActivo) {
            filtroUsuarioCard.style.display = 'none';
            subtituloGestion.textContent = `Mostrando pendientes para: ${usuarioActivo}`;
            buscarPendientesParaUsuario(usuarioActivo);
        } else {
            filtroUsuarioCard.style.display = 'block';
            subtituloGestion.textContent = 'Selecciona tu usuario para ver las OCs que te corresponden.';
            fetch('api/get_autorizadores.php').then(r => r.json()).then(data => {
                gestionSelect.innerHTML = '<option value="">-- Seleccionar Usuario --</option>';
                if (Array.isArray(data)) data.forEach(c => gestionSelect.innerHTML += `<option value="${c}">${c}</option>`);
            });
        }
    };
    gestionBtn.addEventListener('click', () => {
        usuarioActivo = gestionSelect.value;
        if(usuarioActivo) {
            buscarPendientesParaUsuario(usuarioActivo);
            document.getElementById('tab-resumen').dataset.loaded = 'false';
            document.getElementById('tab-consulta').dataset.loaded = 'false';
        } else {
            alert('Por favor, selecciona un usuario.');
        }
    });

    gestionContainer.addEventListener('click', e => {
        const isAutorizar = e.target.classList.contains('autorizar');
        const isRechazar = e.target.classList.contains('rechazar');
        if (!isAutorizar && !isRechazar) return;

        const boton = e.target;
        const numeroOC = boton.dataset.oc;
        const autorizadorSeleccionado = usuarioActivo || gestionSelect.value;

        if (!autorizadorSeleccionado) {
            showModal('Error', 'No se ha identificado un usuario para esta acción. Por favor, selecciona uno.', [{ text: 'Entendido', class: 'btn-primary' }]);
            return;
        }

        // ================================================================
        // INICIO DE LA SECCIÓN CORREGIDA Y COMPLETADA
        // ================================================================
        const ejecutarAccion = (motivo = '') => {
            const esRechazo = motivo !== '';
            const url = esRechazo ? 'api/rechazar_orden.php' : 'api/autorizar_orden.php';
            
            const formData = new FormData();
            formData.append('n_orden_co', numeroOC);

            if (esRechazo) {
                formData.append('usuario_rechaza', autorizadorSeleccionado);
                formData.append('motivo', motivo);
            } else {
                formData.append('usuario_autoriza', autorizadorSeleccionado);
            }

            // Muestra un estado de "procesando" en el botón para feedback visual
            boton.textContent = 'Procesando...';
            boton.disabled = true;

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    showModal('Éxito', result.message, [{ text: 'Aceptar', class: 'btn-success' }]);
                    
                    // Eliminar la tarjeta de la OC de la vista
                    const cardToRemove = document.getElementById(`oc-${numeroOC}`);
                    if (cardToRemove) {
                        cardToRemove.style.transition = 'opacity 0.5s, transform 0.5s';
                        cardToRemove.style.opacity = '0';
                        cardToRemove.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                           cardToRemove.remove();
                           // Si ya no quedan tarjetas, mostrar el mensaje de "felicidades"
                           if (gestionContainer.children.length === 0) {
                                gestionContainer.innerHTML = '<div class="info-card">¡Felicidades! No tienes órdenes pendientes de autorizar.</div>';
                           }
                        }, 500);
                    }
                    
                    // Forzar recarga de los KPIs del Resumen la próxima vez que se visite la pestaña
                    document.getElementById('tab-resumen').dataset.loaded = 'false';

                } else {
                    // Si falla, mostrar el error y restaurar el botón
                    showModal('Error', result.message || 'Ocurrió un error inesperado.', [{ text: 'Cerrar', class: 'btn-primary' }]);
                    boton.textContent = esRechazo ? 'Rechazar' : 'Autorizar';
                    boton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error en la llamada fetch:', error);
                showModal('Error de Conexión', 'No se pudo comunicar con el servidor. Por favor, revisa tu conexión a internet.', [{ text: 'Cerrar', class: 'btn-primary' }]);
                // Restaurar el botón en caso de error de red
                boton.textContent = esRechazo ? 'Rechazar' : 'Autorizar';
                boton.disabled = false;
            });
        };
        // ================================================================
        // FIN DE LA SECCIÓN CORREGIDA Y COMPLETADA
        // ================================================================

        if (isAutorizar) {
            showConfirmationModal(`Confirmar Autorización`, `¿Estás seguro de que deseas AUTORIZAR la OC Nro. ${numeroOC}?`, () => ejecutarAccion());
        } else if (isRechazar) {
            showRejectionModal('Motivo del Rechazo', `(Usuario: ${autorizadorSeleccionado}) Ingresa un motivo para rechazar la OC ${numeroOC}:`, (motivo) => {
                if (motivo) { ejecutarAccion(motivo); }
            });
        }
    });

    // --- PESTAÑA 3: CONSULTA ---
    const consultaSubtitulo = document.getElementById('consulta-subtitulo');
    const consultaEstadoSelect = document.getElementById('filtro-estado');
    const consultaBuscarBtn = document.getElementById('btn-buscar-monitor');
    const consultaTbody = document.getElementById('tabla-resultados-body');
    const loadConsultaData = () => {
        if (usuarioActivo) {
            consultaSubtitulo.textContent = `Busca en las OCs donde ${usuarioActivo} estuvo involucrado.`;
        } else {
            consultaSubtitulo.textContent = 'Busca en el historial global de OCs.';
        }
        const estados = { 1: 'Ingresada', 2: 'Autorizada', 4: 'Desautorizada', 10: 'Cumplida', 11: 'Cerrada' };
        consultaEstadoSelect.innerHTML = '<option value="">Todos</option>';
        for (const id in estados) {
            consultaEstadoSelect.innerHTML += `<option value="${id}">${estados[id]}</option>`;
        }
        consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Info" style="text-align:center;">Usa los filtros para buscar.</td></tr>';
    };
    consultaBuscarBtn.addEventListener('click', () => {
        consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Info" style="text-align:center;">Buscando...</td></tr>';
        const params = new URLSearchParams();
        if (usuarioActivo) { params.append('usuario_involucrado', usuarioActivo); }
        if (document.getElementById('filtro-estado').value) { params.append('estado', document.getElementById('filtro-estado').value); }
        if (document.getElementById('filtro-fecha-desde').value) { params.append('fecha_desde', document.getElementById('filtro-fecha-desde').value); }
        if (document.getElementById('filtro-fecha-hasta').value) { params.append('fecha_hasta', document.getElementById('filtro-fecha-hasta').value); }
        fetch(`api/buscar_ordenes.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                consultaTbody.innerHTML = '';
                if (!data || data.length === 0) {
                    consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Info" style="text-align:center;">No se encontraron resultados.</td></tr>';
                    return;
                }
                data.forEach(oc => {
                    const statusClass = `status-${(oc.estado_desc || '').split(' ')[0].toLowerCase().replace('y', '')}`;
                    let observacionHtml = '';
                    if (oc.observacion && oc.observacion.trim() !== '') {
                        observacionHtml = `<td data-label="Observación">${oc.observacion}</td>`;
                    }
                    consultaTbody.innerHTML += `
                        <tr>
                            <td data-label="OC / Fecha"><strong>${oc.numero}</strong><small style="display:block;">${oc.fecha}</small></td>
                            <td data-label="Proveedor">${oc.proveedor}</td>
                            <td data-label="Comprador">${oc.comprador}</td>
                            <td data-label="Estado"><span class="status ${statusClass}">${oc.estado_desc || 'N/A'}</span></td>
                            ${observacionHtml}
                            <td data-label="Monto" style="font-weight:700;">${formatCurrency(oc.monto)}</td>
                        </tr>`;
                });
            }).catch(err => {
                console.error(err);
                consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Error" style="text-align:center;">Error al cargar los datos.</td></tr>';
            });
    });
    
    // ---- CARGA INICIAL ----
    loadTabData('tab-resumen');
});
</script>

</body>
</html>