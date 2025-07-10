
<!-- Estilos específicos del sistema -->
<style>
    .loading { display: none; }
    .table-responsive { max-height: 75vh; overflow: auto; }
    .nav-tabs { border-bottom: 2px solid #dee2e6; }
    .nav-tabs .nav-link { 
        border: none; 
        color: #6c757d; 
        font-weight: 600;
        padding: 0.75rem 1rem; /* Reducido padding */
    }
    .nav-tabs .nav-link.active { 
        background-color: #0d6efd; 
        color: white; 
        border-radius: 0.5rem 0.5rem 0 0;
    }
    .tab-content { 
        background: white; 
        border: 1px solid #dee2e6; 
        border-top: none;
        border-radius: 0 0 0.5rem 0.5rem;
        min-height: 400px;
    }
    .search-container {
        background: #f8f9fa;
        padding: 0.75rem; /* Reducido de 1rem */
        border-bottom: 1px solid #dee2e6;
    }
    .editable-cell {
        background-color: #fff3cd !important;
        cursor: pointer;
        position: relative;
    }
    .editable-cell:hover {
        background-color: #ffeaa7 !important;
    }
    .indice-input {
        width: 100%;
        border: none;
        background: transparent;
        text-align: center;
        font-weight: bold;
    }
    .indice-input:focus {
        outline: 2px solid #0d6efd;
        background: white;
    }
    .valor-positivo { color: #198754; font-weight: bold; }
    .valor-negativo { color: #dc3545; font-weight: bold; }
    .valor-neutro { color: #6c757d; font-weight: bold; }
    .sticky-header th {
        position: sticky;
        top: 0;
        background: #343a40;
        z-index: 10;
    }
    /* Header compacto */
    .header-compacto {
        background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
        color: white;
        padding: 1rem 0; /* Reducido de 2rem */
        margin: -1rem -15px 1rem -15px; /* Ajustado márgenes */
        border-radius: 0 0 0.5rem 0.5rem;
        box-shadow: 0 2px 8px rgba(13, 110, 253, 0.2);
    }
    .header-compacto h1 {
        font-size: 1.5rem; /* Reducido tamaño */
        margin-bottom: 0;
        font-weight: 700;
    }
    /* Botones de exportación agrupados */
    .export-buttons {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    /* Zona horaria Argentina */
    .timezone-info {
        font-size: 0.85rem;
        opacity: 0.9;
    }
    .temporada-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.75rem; /* Reducido de 1rem */
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }
    /* Mejorar búsqueda rápida */
    .search-input-fast {
        transition: all 0.15s ease;
    }
    .search-input-fast:focus {
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        border-color: #0d6efd;
        transform: scale(1.02);
    }
    /* Badge animado para contadores */
    .contador-animado {
        transition: all 0.3s ease;
    }
    .contador-animado.actualizado {
        animation: pulse-counter 0.5s ease;
    }
    @keyframes pulse-counter {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    /* Headers de columnas mejorados */
    .header-venta-anterior {
        background-color: #0dcaf0 !important; /* Info azul claro */
        color: #000 !important; /* Texto negro para contraste */
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-align: center !important;
        padding: 8px 4px !important;
        border: 1px solid #000 !important;
    }
    
    .header-venta-proyectada {
        background-color: #0d6efd !important; /* Azul primario */
        color: #fff !important; /* Texto blanco */
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-align: center !important;
        padding: 8px 4px !important;
        border: 1px solid #000 !important;
    }
    
    .header-compra-proyectada {
        background-color: #198754 !important; /* Verde */
        color: #fff !important; /* Texto blanco */
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-align: center !important;
        padding: 8px 4px !important;
        border: 1px solid #000 !important;
    }
    
    /* Asegurar que todos los headers de la tabla sean visibles */
    .table thead th {
        background-color: #343a40 !important;
        color: #fff !important;
        font-weight: 700 !important;
        text-align: center !important;
        padding: 8px 4px !important;
        border: 1px solid #000 !important;
        font-size: 0.75rem !important;
    }
    
    /* Headers específicos con colores distintivos */
    .bg-warning {
        background-color: #ffc107 !important;
        color: #000 !important; /* Texto negro para contraste */
        font-weight: 700 !important;
        border: 1px solid #000 !important;
    }
    
    .bg-info.text-dark {
        background-color: #0dcaf0 !important;
        color: #000 !important;
        font-weight: 700 !important;
        border: 1px solid #000 !important;
    }
    
    /* Sticky headers mejorados */
    .sticky-header th {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
    }

    /* Efectos hover para cards */
    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .contador-animado.actualizado {
        animation: pulse-total 0.6s ease;
    }

    @keyframes pulse-total {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    /* Responsive para móviles */
    @media (max-width: 768px) {
        .search-container .row {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .search-container .col-md-3,
        .search-container .col-md-4 {
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        .card.bg-warning-subtle,
        .card.bg-primary-subtle {
            margin-bottom: 0.5rem;
        }
        
        .export-buttons {
            justify-content: center;
        }
        
        .table thead th {
            font-size: 0.65rem !important;
            padding: 4px 2px !important;
        }
        
        .header-venta-anterior,
        .header-venta-proyectada,
        .header-compra-proyectada {
            font-size: 0.6rem !important;
            padding: 4px 2px !important;
        }
    }
</style>