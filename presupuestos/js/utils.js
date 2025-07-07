
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
        return new Intl.NumberFormat('es-AR').format(numero);
    }

    /**
     * Formatear decimales con precisión específica
     */
    static formatearDecimal(numero, decimales = 2) {
        return new Intl.NumberFormat('es-AR', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }).format(numero);
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
    static parsearNumero(texto) {
        if (!texto) return 0;
        return parseFloat(texto.replace(/[^\d.-]/g, '')) || 0;
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
     * Mostrar información de temporada
     */
    static mostrarInfoTemporada(info) {
        if (info && info.temporada_actual) {
            const container = document.getElementById('info-temporada-container');
            const temporadaElement = document.getElementById('temporada-actual');
            const diasElement = document.getElementById('dias-restantes');
            const fechaElement = document.getElementById('fecha-actual');
            
            if (temporadaElement) {
                temporadaElement.textContent = `${info.temporada_actual.temporada} ${info.temporada_actual.ano}`;
            }
            
            if (diasElement) {
                diasElement.textContent = info.dias_restantes;
            }
            
            if (fechaElement) {
                fechaElement.textContent = `Fecha: ${info.fecha_calculo}`;
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
        if (info.etiquetas_proyeccion) {
            const headerVerano = document.getElementById('header-venta-verano');
            const headerInvierno = document.getElementById('header-venta-invierno');
            const headerVeranoInv = document.getElementById('header-venta-verano-inv');
            const headerInviernoInv = document.getElementById('header-venta-invierno-inv');
            
            if (headerVerano) headerVerano.textContent = info.etiquetas_proyeccion.verano;
            if (headerInvierno) headerInvierno.textContent = info.etiquetas_proyeccion.invierno;
            if (headerVeranoInv) headerVeranoInv.textContent = info.etiquetas_proyeccion.verano;
            if (headerInviernoInv) headerInviernoInv.textContent = info.etiquetas_proyeccion.invierno;
        }
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