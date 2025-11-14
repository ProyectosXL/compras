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

    <main class="container">
        <!-- Contenido de las Pestañas (se muestra una a la vez) -->

        <!-- Pestaña 1: RESUMEN (Dashboard) -->
        <div id="tab-resumen" class="tab-content active">
            <div class="header"><h1>Resumen General</h1><p>Indicadores clave de tus órdenes de compra.</p></div>
            <div id="kpi-loader" class="info-card" style="text-align: center;">Cargando indicadores...</div>
            <div class="kpi-grid" id="kpi-container" style="display: none;"></div>
        </div>

        <!-- Pestaña 2: GESTIÓN (Autorizar/Rechazar) -->
        <div id="tab-gestion" class="tab-content">
            <div class="header"><h1>Gestión de Pendientes</h1><p>Selecciona tu usuario para ver las OCs que te corresponden.</p></div>
            <div class="filter-card">
                <div class="form-group"><label for="autorizador-select">Tu Usuario Autorizador</label><select id="autorizador-select"><option value="">Cargando...</option></select></div>
                <button id="buscar-pendientes-btn" class="btn btn-primary">Buscar Mis Pendientes</button>
            </div>
            <div id="ordenes-container"></div>
        </div>

        <!-- Pestaña 3: CONSULTA (Monitor) -->
        <div id="tab-consulta" class="tab-content">
            <div class="header"><h1>Monitor de Órdenes</h1><p>Busca en el historial de OCs.</p></div>
            <div class="filter-card">
                <div class="filtros">
                    <div class="form-group"><label for="filtro-usuario">Usuario Involucrado</label><select id="filtro-usuario"><option value="">Todos</option></select></div>
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
            <div class="modal-header">
                <h3 id="modal-title"></h3>
            </div>
            <div class="modal-body">
                <p id="modal-message"></p>
            </div>
            <div class="modal-footer" id="modal-footer-buttons">
                <!-- Los botones se insertarán aquí dinámicamente -->
            </div>
        </div>
    </div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    // ---- VARIABLES GLOBALES Y UTILIDADES ----
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
                if (!targetTab.dataset.loaded) {
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
            button.onclick = () => {
                modalOverlay.classList.remove('active');
                if (btnInfo.callback) btnInfo.callback();
            };
            modalFooter.appendChild(button);
        });
        modalOverlay.classList.add('active');
    };
    const showConfirmationModal = (title, message, onConfirm) => {
        showModal(title, message, [
            { text: 'Cancelar', class: 'btn-secondary', callback: () => {} },
            { text: 'Sí, Autorizar', class: 'btn-success', callback: onConfirm }
        ]);
    };
    const showRejectionModal = (title, message, onConfirm) => {
        const bodyWithMessage = `${message}<br><textarea id="rejection-reason" rows="3" placeholder="El motivo es obligatorio..."></textarea>`;
        showModal(title, bodyWithMessage, [
            { text: 'Cancelar', class: 'btn-secondary', callback: () => {} },
            { text: 'Rechazar OC', class: 'btn-danger', callback: () => {
                const reason = document.getElementById('rejection-reason').value.trim();
                if (reason) {
                    onConfirm(reason);
                } else {
                    document.getElementById('rejection-reason').style.borderColor = 'var(--danger-color)';
                }
            }}
        ]);
    };

    // --- PESTAÑA 1: RESUMEN ----
    const kpiContainer = document.getElementById('kpi-container');
    const kpiLoader = document.getElementById('kpi-loader');
    const loadResumenData = () => {
        kpiLoader.style.display = 'block';
        kpiContainer.style.display = 'none';
        fetch('api/get_dashboard_stats.php').then(res => res.json()).then(stats => {
            kpiLoader.style.display = 'none';
            kpiContainer.style.display = 'grid';
            kpiContainer.innerHTML = `
                <div class="kpi-card pending"><div class="icon"><i class="bi bi-hourglass-split"></i></div><div class="value">${stats.pendientes_count || 0}</div><div class="label">Pendientes</div></div>
                <div class="kpi-card amount"><div class="icon"><i class="bi bi-cash-coin"></i></div><div class="value" style="font-size:1.8rem;">${formatCurrency(stats.pendientes_monto || 0)}</div><div class="label">Monto Pendiente</div></div>
                <div class="kpi-card authorized"><div class="icon"><i class="bi bi-check-circle-fill"></i></div><div class="value">${stats.autorizadas_hoy || 0}</div><div class="label">Autorizadas Hoy</div></div>
                <div class="kpi-card rejected"><div class="icon"><i class="bi bi-x-circle-fill"></i></div><div class="value">${stats.rechazadas_hoy || 0}</div><div class="label">Rechazadas Hoy</div></div>`;
        }).catch(err => kpiLoader.textContent = 'Error al cargar indicadores.');
    };

    // --- PESTAÑA 2: GESTIÓN ----
    const gestionSelect = document.getElementById('autorizador-select');
    const gestionBtn = document.getElementById('buscar-pendientes-btn');
    const gestionContainer = document.getElementById('ordenes-container');
    const loadGestionData = () => {
        fetch('api/get_autorizadores.php').then(r => r.json()).then(data => {
            gestionSelect.innerHTML = '<option value="">-- Seleccionar Usuario --</option>';
            if (Array.isArray(data)) data.forEach(c => gestionSelect.innerHTML += `<option value="${c}">${c}</option>`);
        });
    };
    gestionBtn.addEventListener('click', () => {
        const autorizador = gestionSelect.value;
        if (!autorizador) { alert("Por favor, selecciona tu usuario para continuar."); return; }
        gestionContainer.innerHTML = '<div class="info-card">Buscando tus órdenes pendientes...</div>';
        fetch(`api/get_ordenes_pendientes_por_usuario.php?autorizador=${encodeURIComponent(autorizador)}`).then(r => r.json()).then(ordenes => {
            gestionContainer.innerHTML = '';
            if (ordenes.length === 0) { gestionContainer.innerHTML = '<div class="info-card">¡Felicidades! No tienes órdenes pendientes de autorizar.</div>'; return; }
            ordenes.forEach(oc => {
                const card = document.createElement('div');
                card.className = 'action-card'; card.id = `oc-${oc.numero}`;
                let observacionHtml = '';
                if (oc.observacion && oc.observacion.trim() !== '') {
                    observacionHtml = `<div class="action-card-header" style="font-size:0.8rem; padding-top: 0.5rem; margin-top: 0.5rem; border-top: 1px solid var(--border-color)"><div class="info"><strong>Observación:</strong><span>${oc.observacion}</span></div></div>`;
                }
                card.innerHTML = `<div class="action-card-header"><div class="info"><strong>${oc.proveedor}</strong><span>OC: ${oc.numero} / Fecha: ${oc.fecha}</span></div></div>${observacionHtml}<div class="action-card-monto">${formatCurrency(oc.monto)}</div><div class="action-card-buttons"><button class="btn btn-danger btn-flex rechazar" data-oc="${oc.numero}">Rechazar</button><button class="btn btn-success btn-flex autorizar" data-oc="${oc.numero}">Autorizar</button></div>`;
                gestionContainer.appendChild(card);
            });
        }).catch(error => { gestionContainer.innerHTML = '<div class="info-card">Error al cargar las órdenes pendientes.</div>'; console.error('Error:', error); });
    });
    gestionContainer.addEventListener('click', e => {
        const isAutorizar = e.target.classList.contains('autorizar'); const isRechazar = e.target.classList.contains('rechazar');
        if (!isAutorizar && !isRechazar) return;

        const boton = e.target;
        const numeroOC = boton.dataset.oc;
        const autorizadorSeleccionado = gestionSelect.value;
        if (!autorizadorSeleccionado) {
            showModal('Error', 'Por favor, selecciona tu usuario en la lista antes de realizar una acción.', [{ text: 'Entendido', class: 'btn-primary' }]);
            return;
        }

        const ejecutarAccion = (motivo = '') => {
            const card = document.getElementById(`oc-${numeroOC}`);
            const botones = card.querySelectorAll('button');
            botones.forEach(b => b.disabled = true);
            boton.textContent = 'Procesando...';
            
            const formData = new FormData();
            formData.append('n_orden_co', numeroOC);
            const usuario_que_actua = autorizadorSeleccionado;
            let url;

            if (isAutorizar) {
                url = 'api/autorizar_orden.php';
                formData.append('usuario_autoriza', usuario_que_actua);
            } else {
                url = 'api/rechazar_orden.php';
                formData.append('usuario_rechaza', usuario_que_actua);
                formData.append('motivo', motivo);
            }

            fetch(url, { method: 'POST', body: formData }).then(r => r.json()).then(data => {
                if (data.status === 'success') {
                    showModal('¡Éxito!', data.message, [{ text: 'Aceptar', class: 'btn-success' }]);
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0'; card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                } else { 
                    showModal('Error', data.message, [{ text: 'Cerrar', class: 'btn-danger' }]);
                    botones.forEach(b => b.disabled = false); 
                    boton.textContent = isAutorizar ? "Autorizar" : "Rechazar";
                }
            }).catch(err => {
                showModal('Error de Conexión', 'No se pudo procesar la acción. Revisa tu conexión a internet.', [{ text: 'Cerrar', class: 'btn-danger' }]);
                botones.forEach(b => b.disabled = false);
                boton.textContent = isAutorizar ? "Autorizar" : "Rechazar";
            });
        };

        if (isAutorizar) {
            showConfirmationModal( 'Confirmar Autorización', `¿Estás seguro de que deseas AUTORIZAR la OC Nro. ${numeroOC}?`, () => ejecutarAccion());
        } else if (isRechazar) {
            showRejectionModal('Motivo del Rechazo', `(Usuario: ${autorizadorSeleccionado}) Ingresa un motivo para rechazar la OC ${numeroOC}:`, (motivo) => {
                if (motivo) { ejecutarAccion(motivo); }
            });
        }
    });

    // --- PESTAÑA 3: CONSULTA ---
    const consultaUsuarioSelect = document.getElementById('filtro-usuario');
    const consultaEstadoSelect = document.getElementById('filtro-estado');
    const consultaBuscarBtn = document.getElementById('btn-buscar-monitor');
    const consultaTbody = document.getElementById('tabla-resultados-body');
    const loadConsultaData = () => {
        fetch('api/get_autorizadores.php').then(res => res.json()).then(autorizadores => {
             if (Array.isArray(autorizadores)) {
                consultaUsuarioSelect.innerHTML = '<option value="">Todos</option>';
                autorizadores.forEach(u => consultaUsuarioSelect.innerHTML += `<option value="${u}">${u}</option>`);
            }
        });
        const estados = { 1: 'Ingresada', 2: 'Autorizada', 4: 'Desautorizada', 10: 'Cumplida', 11: 'Cerrada' };
        consultaEstadoSelect.innerHTML = '<option value="">Todos</option>';
        for (const id in estados) consultaEstadoSelect.innerHTML += `<option value="${id}">${estados[id]}</option>`;
        consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Info" style="text-align:center;">Use los filtros para buscar.</td></tr>';
    };
    consultaBuscarBtn.addEventListener('click', () => {
        consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Info" style="text-align:center;">Buscando...</td></tr>';
        const params = new URLSearchParams();
        if (document.getElementById('filtro-usuario').value) params.append('usuario_involucrado', document.getElementById('filtro-usuario').value);
        if (document.getElementById('filtro-estado').value) params.append('estado', document.getElementById('filtro-estado').value);
        if (document.getElementById('filtro-fecha-desde').value) params.append('fecha_desde', document.getElementById('filtro-fecha-desde').value);
        if (document.getElementById('filtro-fecha-hasta').value) params.append('fecha_hasta', document.getElementById('filtro-fecha-hasta').value);
        fetch(`api/buscar_ordenes.php?${params.toString()}`).then(r => r.json()).then(data => {
            consultaTbody.innerHTML = '';
            if (!data || data.length === 0) { consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Info" style="text-align:center;">No se encontraron resultados.</td></tr>'; return; }
            data.forEach(oc => {
                const statusClass = `status-${(oc.estado_desc || '').split(' ')[0].toLowerCase().replace('y', '')}`;
                let observacionHtml = '';
                if (oc.observacion && oc.observacion.trim() !== '') { observacionHtml = `<td data-label="Observación">${oc.observacion}</td>`; }
                consultaTbody.innerHTML += `<tr><td data-label="OC / Fecha"><strong>${oc.numero}</strong><small style="display:block;">${oc.fecha}</small></td><td data-label="Proveedor">${oc.proveedor}</td><td data-label="Comprador">${oc.comprador}</td><td data-label="Estado"><span class="status ${statusClass}">${oc.estado_desc || 'N/A'}</span></td>${observacionHtml}<td data-label="Monto" style="font-weight:700;">${formatCurrency(oc.monto)}</td></tr>`;
            });
        }).catch(err => { console.error(err); consultaTbody.innerHTML = '<tr><td colspan="6" data-label="Error" style="text-align:center;">Error al cargar los datos.</td></tr>';});
    });

    // ---- CARGA INICIAL ----
    loadTabData('tab-resumen');
});
</script>

</body>
</html>