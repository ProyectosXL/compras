
// Utilidades generales para el sistema
// Archivo: presupuestos/js/utils.js

/**
 * Utilidades para formateo de números y valores
 */
class FormatoUtils {
    
    /**
     * Formatear números con separadores de miles
     */
    static formatearNumero(numero) {
        const num = parseFloat(numero);
        if (isNaN(num)) return '0';
        
        // Si es un número entero o muy cercano a entero, no mostrar decimales
        if (Number.isInteger(num) || Math.abs(num - Math.round(num)) < 0.01) {
            return new Intl.NumberFormat('es-AR').format(Math.round(num));
        }
        
        return new Intl.NumberFormat('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(num);
    }

    static formatearParaInput(numero, decimales = 2) {
        const num = parseFloat(numero);
        if (isNaN(num)) return '0.00';
        
        // Usar formato inglés con punto para inputs HTML
        return num.toFixed(decimales);
    }

    /**
     * Formatear decimales con precisión específica
     */
    static formatearDecimal(numero, decimales = 2) {
        const num = parseFloat(numero);
        if (isNaN(num)) return '0.00';
        
        return new Intl.NumberFormat('es-AR', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }).format(num);
    }

    /**
     * Formatear como porcentaje
     */
    static formatearPorcentaje(numero, decimales = 1) {
        return new Intl.NumberFormat('es-AR', {
            style: 'percent',
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }).format(numero / 100);
    }

    /**
     * Obtener clase CSS según el valor (positivo, negativo, neutro)
     */
    static obtenerClaseValor(valor) {
        if (valor > 0) return 'valor-positivo';
        if (valor < 0) return 'valor-negativo';
        return 'valor-neutro';
    }

    /**
     * Formatear moneda
     */
    static formatearMoneda(numero, moneda = 'ARS') {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: moneda
        }).format(numero);
    }

    /**
     * Abreviar números grandes (1K, 1M, etc.)
     */
    static abreviarNumero(numero) {
        if (numero >= 1000000) {
            return (numero / 1000000).toFixed(1) + 'M';
        } else if (numero >= 1000) {
            return (numero / 1000).toFixed(1) + 'K';
        }
        return numero.toString();
    }

    /**
     * Parsear número desde texto formateado
     */
    /**
     * Parsear número desde texto formateado (MEJORADA)
     */
    static parsearNumero(texto) {
        if (!texto) return 0;
        
        // Convertir a string si no lo es
        const str = texto.toString();
        
        // Remover todo excepto números, punto, coma y signo negativo
        let numeroLimpio = str.replace(/[^\d.,-]/g, '');
        
        // Si está vacío después de limpiar, retornar 0
        if (!numeroLimpio) return 0;
        
        // Manejar números negativos
        const esNegativo = str.includes('-') || str.startsWith('(');
        
        // Remover signos para procesar
        numeroLimpio = numeroLimpio.replace(/[-()]/g, '');
        
        // Manejar separadores decimales (punto vs coma)
        // Si hay tanto punto como coma, asumir que la coma es separador de miles
        if (numeroLimpio.includes('.') && numeroLimpio.includes(',')) {
            // Formato: 1,234.56 (coma para miles, punto para decimales)
            numeroLimpio = numeroLimpio.replace(/,/g, '');
        } else if (numeroLimpio.includes(',')) {
            // Solo coma - puede ser decimal o miles
            const partes = numeroLimpio.split(',');
            if (partes.length === 2 && partes[1].length <= 2) {
                // Formato: 123,45 (coma como decimal)
                numeroLimpio = numeroLimpio.replace(',', '.');
            } else {
                // Formato: 1,234 (coma como separador de miles)
                numeroLimpio = numeroLimpio.replace(/,/g, '');
            }
        }
        
        // Convertir a número
        const numero = parseFloat(numeroLimpio);
        
        // Aplicar signo negativo si corresponde
        return isNaN(numero) ? 0 : (esNegativo ? -Math.abs(numero) : numero);
    }

    /**
     * Validar que un valor sea numérico válido
     */
    static esNumeroValido(valor) {
        return !isNaN(valor) && isFinite(valor);
    }
}

/**
 * Utilidades para manejo de UI
 */
class UIUtils {
    
    /**
     * Mostrar/ocultar loading
     */
    static mostrarLoading(mostrar) {
        const loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = mostrar ? 'block' : 'none';
        }
    }

    /**
     * Mostrar/ocultar container de tabs
     */
    static mostrarTabsContainer(mostrar) {
        const container = document.getElementById('tabs-container');
        if (container) {
            container.style.display = mostrar ? 'block' : 'none';
        }
    }

    /**
     * Mostrar información de temporada - MODIFICADA
     */
    static mostrarInfoTemporada(info) {
        if (info && info.temporada_actual) {
            const container = document.getElementById('info-temporada-container');
            const temporadaElement = document.getElementById('temporada-actual');
            const diasTotalesElement = document.getElementById('dias-totales');
            const diasElement = document.getElementById('dias-restantes');
            const fechaElement = document.getElementById('fecha-actual');
            const ultActElement = document.getElementById('ultima-actualizacion');

            if (temporadaElement) {
                // Código en la convención única de la app ("VER 26-27", "INV 27").
                // Antes se armaba con temporada + año ("VERANO 2027"), que nombraba el
                // verano por el año en que termina y no coincidía ni con las columnas
                // históricas ni con los códigos de oleada de Comercio Exterior.
                temporadaElement.textContent = info.temporada_actual.codigo;
                temporadaElement.title = `${UIUtils.formatearFechaCorta(info.temporada_actual.desde)} a ${UIUtils.formatearFechaCorta(info.temporada_actual.hasta)}`;
            }

            if (diasTotalesElement && info.dias_totales) {
                diasTotalesElement.textContent = info.dias_totales;
            }

            if (diasElement) {
                diasElement.textContent = info.dias_restantes;
            }

            if (fechaElement) {
                fechaElement.textContent = `Fecha: ${info.fecha_calculo}`;
            }

            // Mostrar ULT_ACTUALIZACION desde la base de datos
            if (ultActElement) {
                ultActElement.textContent = info.ult_actualizacion || '--';
            }

            if (container) {
                container.style.display = 'block';
            }

            // Actualizar headers dinámicos
            UIUtils.actualizarHeadersDinamicos(info);
        }
    }

    /**
     * Actualizar headers dinámicos con etiquetas de temporada
     */
    static actualizarHeadersDinamicos(info) {
        if (!info || !info.periodos) return;

        // Cada solapa recibe sus propios períodos: la columna "Venta Proy. Verano" cubre
        // distinto rango en verano que en invierno, así que no se puede usar una sola
        // etiqueta para las dos. Antes se aplicaba la misma a ambas y, encima, los IDs
        // estaban duplicados, así que la solapa invierno se quedaba con el texto fijo.
        UIUtils.aplicarHeadersSolapa('', info.periodos.verano);
        UIUtils.aplicarHeadersSolapa('-inv', info.periodos.invierno);
    }

    /**
     * Rotula los encabezados de una solapa con el período que cubre cada columna.
     * @param {string} sufijo '' para la solapa verano, '-inv' para la de invierno
     */
    static aplicarHeadersSolapa(sufijo, periodos) {
        if (!periodos) return;

        const proyectadas = [
            ['header-venta-verano' + sufijo, 'Venta Proy. Verano', periodos.verano],
            ['header-venta-invierno' + sufijo, 'Venta Proy. Invierno', periodos.invierno]
        ];

        proyectadas.forEach(([id, titulo, periodo]) => {
            const th = document.getElementById(id);
            if (!th || !periodo) return;

            // La etiqueta lleva los dos tramos cuando la columna suma dos (por ejemplo
            // "Resto VER 26-27 + VER 27-28"): la celda es la suma, así que mostrar una
            // sola temporada haría leer mal el número.
            th.innerHTML = `${titulo}<br><span class="fw-normal">${periodo.etiqueta}</span>`;
            th.title = periodo.detalle;
        });

        // Las columnas "anterior" nombran la temporada histórica que sirve de base.
        UIUtils.rotularVentaAnterior('header-venta-verano-ant' + sufijo, 'Venta Ver. Anterior', 'VERANO');
        UIUtils.rotularVentaAnterior('header-venta-invierno-ant' + sufijo, 'Venta Inv. Anterior', 'INVIERNO');
    }

    /**
     * Rotula una columna de venta anterior con la última temporada completa de su tipo,
     * que es la base desde la que se proyecta.
     */
    static rotularVentaAnterior(id, titulo, tipo) {
        const th = document.getElementById(id);
        if (!th) return;

        const columnas = (window.presupuestoApp && window.presupuestoApp.columnasHistoricas) || [];
        const ultima = columnas.filter(c => c.toUpperCase().includes(tipo)).pop();
        if (!ultima) return;

        th.innerHTML = `${titulo}<br><span class="fw-normal">${TemporadaServidor.etiquetaHistorica(ultima)}</span>`;
    }

    /** Fecha YYYY-MM-DD a DD/MM/YYYY, para los tooltips. */
    static formatearFechaCorta(fecha) {
        if (!fecha) return '';
        const p = String(fecha).split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : fecha;
    }

    /**
     * Actualizar contador de registros
     */
    static actualizarContador(elementId, count) {
        const elemento = document.getElementById(elementId);
        if (elemento) {
            elemento.textContent = `${FormatoUtils.formatearNumero(count)} registros`;
        }
    }

    /**
     * Mostrar alertas
     */
    static mostrarAlerta(mensaje, tipo = 'info', duracion = 5000) {
        const container = document.getElementById('alert-container');
        if (!container) return;
        
        const alertId = 'alert-' + Date.now();
        
        const tiposBootstrap = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        
        const iconos = {
            'success': 'check-circle',
            'error': 'exclamation-triangle',
            'warning': 'exclamation-circle',
            'info': 'info-circle'
        };
        
        const alert = document.createElement('div');
        alert.id = alertId;
        alert.className = `alert alert-${tiposBootstrap[tipo]} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="fas fa-${iconos[tipo]} me-2"></i>
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        container.appendChild(alert);
        
        // Auto-remover después del tiempo especificado
        setTimeout(() => {
            const alertElement = document.getElementById(alertId);
            if (alertElement) {
                const bsAlert = new bootstrap.Alert(alertElement);
                bsAlert.close();
            }
        }, duracion);
    }

    /**
     * Confirmar acción con modal
     */
    static async confirmarAccion(titulo, mensaje, tipoBoton = 'danger') {
        return new Promise((resolve) => {
            const modalId = 'modal-confirmacion-' + Date.now();
            
            const modalHTML = `
                <div class="modal fade" id="${modalId}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${titulo}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${mensaje}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-${tipoBoton}" id="btn-confirmar">Confirmar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            const btnConfirmar = document.getElementById('btn-confirmar');
            
            btnConfirmar.addEventListener('click', () => {
                modal.hide();
                resolve(true);
            });
            
            document.getElementById(modalId).addEventListener('hidden.bs.modal', () => {
                document.getElementById(modalId).remove();
                resolve(false);
            });
            
            modal.show();
        });
    }

    /**
     * Solicitar texto con un modal personalizado
     */
    static async solicitarTexto(titulo, mensaje, valorDefecto = '') {
        return new Promise((resolve) => {
            const modalId = 'modal-prompt-' + Date.now();
            const inputId = 'input-prompt-' + Date.now();
            
            const modalHTML = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content shadow-lg border-0">
                            <div class="modal-header bg-primary text-white" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;">
                                <h5 class="modal-title"><i class="fas fa-save me-2"></i>${titulo}</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <p class="mb-3 text-muted fw-bold" style="font-size: 0.95rem;">${mensaje}</p>
                                <input type="text" class="form-control form-control-lg border-2" id="${inputId}" value="${valorDefecto}" autocomplete="off" placeholder="Escriba aquí...">
                            </div>
                            <div class="modal-footer bg-light border-top-0">
                                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary btn-sm px-4" id="btn-confirmar-prompt">Aceptar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            const modalElement = document.getElementById(modalId);
            const inputElement = document.getElementById(inputId);
            const btnConfirmar = modalElement.querySelector('#btn-confirmar-prompt');
            
            const modal = new bootstrap.Modal(modalElement);
            
            modalElement.addEventListener('shown.bs.modal', () => {
                inputElement.focus();
                inputElement.select();
            });
            
            inputElement.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    btnConfirmar.click();
                }
            });
            
            btnConfirmar.addEventListener('click', () => {
                const valor = inputElement.value;
                modal.hide();
                resolve(valor);
            });
            
            modalElement.addEventListener('hidden.bs.modal', () => {
                modalElement.remove();
                resolve(null);
            });
            
            modal.show();
        });
    }

    /**
     * Mostrar modal de progreso
     */
    static mostrarProgreso(titulo, progreso = 0) {
        let modal = document.getElementById('modal-progreso');
        
        if (!modal) {
            const modalHTML = `
                <div class="modal fade" id="modal-progreso" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title" id="titulo-progreso">${titulo}</h6>
                            </div>
                            <div class="modal-body text-center">
                                <div class="progress mb-3">
                                    <div class="progress-bar" id="barra-progreso" style="width: ${progreso}%"></div>
                                </div>
                                <div id="texto-progreso">${progreso}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modal = document.getElementById('modal-progreso');
        }
        
        document.getElementById('titulo-progreso').textContent = titulo;
        UIUtils.actualizarProgreso(progreso);
        
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        return bsModal;
    }

    /**
     * Actualizar progreso
     */
    static actualizarProgreso(progreso) {
        const barra = document.getElementById('barra-progreso');
        const texto = document.getElementById('texto-progreso');
        
        if (barra) barra.style.width = progreso + '%';
        if (texto) texto.textContent = Math.round(progreso) + '%';
    }

    /**
     * Cerrar modal de progreso
     */
    static cerrarProgreso() {
        const modal = document.getElementById('modal-progreso');
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        }
    }

    /**
     * Mostrar tooltip personalizado
     */
    static mostrarTooltip(elemento, mensaje, posicion = 'top') {
        if (elemento.tooltipInstance) {
            elemento.tooltipInstance.dispose();
        }
        
        elemento.setAttribute('title', mensaje);
        elemento.tooltipInstance = new bootstrap.Tooltip(elemento, {
            placement: posicion,
            trigger: 'manual'
        });
        
        elemento.tooltipInstance.show();
        
        setTimeout(() => {
            if (elemento.tooltipInstance) {
                elemento.tooltipInstance.hide();
            }
        }, 3000);
    }

    /**
     * Animar elemento (shake, bounce, etc.)
     */
    static animarElemento(elemento, animacion = 'shake') {
        elemento.classList.add(`animate-${animacion}`);
        
        setTimeout(() => {
            elemento.classList.remove(`animate-${animacion}`);
        }, 600);
    }

    /**
     * Scroll suave a elemento
     */
    static scrollToElement(elemento, offset = 0) {
        const rect = elemento.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const targetPosition = rect.top + scrollTop - offset;
        
        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
    }

    /**
     * Copiar texto al portapapeles
     */
    static async copiarAlPortapapeles(texto) {
        try {
            await navigator.clipboard.writeText(texto);
            UIUtils.mostrarAlerta('Texto copiado al portapapeles', 'success', 2000);
            return true;
        } catch (error) {
            console.error('Error copiando al portapapeles:', error);
            UIUtils.mostrarAlerta('Error al copiar texto', 'error', 2000);
            return false;
        }
    }

    /**
     * Detectar si es dispositivo móvil
     */
    static esMobile() {
        return window.innerWidth <= 768;
    }

    /**
     * Debounce para funciones
     */
    static debounce(func, delay) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    }

    /**
     * Throttle para funciones
     */
    static throttle(func, delay) {
        let inThrottle;
        return function (...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, delay);
            }
        };
    }
}

/**
 * Utilidades para manejo de fechas
 */
class FechaUtils {
    
    /**
     * Formatear fecha para mostrar
     */
    static formatearFecha(fecha, formato = 'es-AR') {
        return new Date(fecha).toLocaleDateString(formato);
    }

    /**
     * Formatear fecha y hora
     */
    static formatearFechaHora(fecha, formato = 'es-AR') {
        return new Date(fecha).toLocaleString(formato);
    }

    /**
     * Obtener diferencia en días entre fechas
     */
    static diferenciaDias(fecha1, fecha2) {
        const diff = Math.abs(new Date(fecha2) - new Date(fecha1));
        return Math.ceil(diff / (1000 * 60 * 60 * 24));
    }

    /**
     * Agregar días a una fecha
     */
    static agregarDias(fecha, dias) {
        const nuevaFecha = new Date(fecha);
        nuevaFecha.setDate(nuevaFecha.getDate() + dias);
        return nuevaFecha;
    }

    /**
     * Obtener nombre del mes
     */
    static obtenerNombreMes(numeroMes) {
        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        return meses[numeroMes - 1] || '';
    }

    /**
     * Validar formato de fecha
     */
    static esFechaValida(fecha) {
        return !isNaN(Date.parse(fecha));
    }
}

/**
 * Utilidades para almacenamiento local
 */
class StorageUtils {
    
    /**
     * Guardar en localStorage con expiración
     */
    static guardar(clave, valor, tiempoExpiracion = null) {
        const item = {
            valor: valor,
            timestamp: Date.now(),
            expiracion: tiempoExpiracion ? Date.now() + tiempoExpiracion : null
        };
        
        try {
            localStorage.setItem(clave, JSON.stringify(item));
            return true;
        } catch (error) {
            console.error('Error guardando en localStorage:', error);
            return false;
        }
    }

            
    /**
     * Obtener de localStorage con validación de expiración
     */
    static obtener(clave) {
        try {
            const itemStr = localStorage.getItem(clave);
            if (!itemStr) return null;
            
            const item = JSON.parse(itemStr);
            
            // Verificar expiración
            if (item.expiracion && Date.now() > item.expiracion) {
                localStorage.removeItem(clave);
                return null;
            }
            
            return item.valor;
        } catch (error) {
            console.error('Error obteniendo de localStorage:', error);
            return null;
        }
    }

    /**
     * Eliminar elemento del localStorage
     */
    static eliminar(clave) {
        try {
            localStorage.removeItem(clave);
            return true;
        } catch (error) {
            console.error('Error eliminando de localStorage:', error);
            return false;
        }
    }

    /**
     * Limpiar elementos expirados
     */
    static limpiarExpirados() {
        try {
            const claves = Object.keys(localStorage);
            let eliminados = 0;
            
            claves.forEach(clave => {
                const itemStr = localStorage.getItem(clave);
                try {
                    const item = JSON.parse(itemStr);
                    if (item.expiracion && Date.now() > item.expiracion) {
                        localStorage.removeItem(clave);
                        eliminados++;
                    }
                } catch (e) {
                    // Si no se puede parsear, no es un item válido nuestro
                }
            });
            
            return eliminados;
        } catch (error) {
            console.error('Error limpiando localStorage:', error);
            return 0;
        }
    }

    /**
     * Obtener tamaño del localStorage en MB
     */
    static obtenerTamaño() {
        try {
            let total = 0;
            for (let clave in localStorage) {
                if (localStorage.hasOwnProperty(clave)) {
                    total += localStorage[clave].length + clave.length;
                }
            }
            return (total / 1024 / 1024).toFixed(2); // MB
        } catch (error) {
            console.error('Error calculando tamaño de localStorage:', error);
            return '0';
        }
    }
}

/**
 * Utilidades para validación de datos
 */
class ValidacionUtils {
    
    /**
     * Validar email
     */
    static esEmailValido(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Validar número de teléfono argentino
     */
    static esTelefonoValido(telefono) {
        const regex = /^(\+54|0)?[1-9]\d{8,9}$/;
        return regex.test(telefono.replace(/\s|-/g, ''));
    }

    /**
     * Validar CUIT/CUIL argentino
     */
    static esCUITValido(cuit) {
        if (!cuit || cuit.length !== 11) return false;
        
        const multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        let suma = 0;
        
        for (let i = 0; i < 10; i++) {
            suma += parseInt(cuit[i]) * multiplicadores[i];
        }
        
        const resto = suma % 11;
        const digitoVerificador = resto < 2 ? resto : 11 - resto;
        
        return parseInt(cuit[10]) === digitoVerificador;
    }

    /**
     * Validar rango numérico
     */
    static estaEnRango(valor, min, max) {
        return valor >= min && valor <= max;
    }

    /**
     * Validar longitud de string
     */
    static longitudValida(texto, minimo, maximo = null) {
        if (!texto) return false;
        if (texto.length < minimo) return false;
        if (maximo && texto.length > maximo) return false;
        return true;
    }

    /**
     * Sanitizar input para evitar XSS
     */
    static sanitizarInput(input) {
        const elemento = document.createElement('div');
        elemento.textContent = input;
        return elemento.innerHTML;
    }

    /**
     * Validar que no contenga caracteres especiales peligrosos
     */
    static esTextoSeguro(texto) {
        const caracteresProhibidos = /<script|javascript:|on\w+=/i;
        return !caracteresProhibidos.test(texto);
    }
}

// Exportar utilidades como globales para compatibilidad
window.FormatoUtils = FormatoUtils;
window.UIUtils = UIUtils;
window.FechaUtils = FechaUtils;
window.StorageUtils = StorageUtils;
window.ValidacionUtils = ValidacionUtils;