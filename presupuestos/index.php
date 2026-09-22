
<?php
// presupuestos/index.php - Archivo principal simplificado

// Misma zona horaria que api.php: el php.ini de XAMPP viene en Europe/Berlin y
// deja al servidor cinco horas adelante. Ver el comentario en api.php.
date_default_timezone_set('America/Argentina/Buenos_Aires');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto de Compras</title>
    <link rel="icon" href="../images/logo.jpg" type="image/jpeg">
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/obtener-presupuesto.css?v=1.3" rel="stylesheet">
    <link href="css/tabla-optimizada.css?v=1.5" rel="stylesheet">
    <link href="css/filtros-persistentes.css?v=1.2" rel="stylesheet">
    <link href="css/proceso-presupuesto.css?v=1.2" rel="stylesheet">
    
    <!-- Estilos específicos -->
    <?php include 'components/styles.php'; ?>
</head>
<body style="margin: 0; padding: 0; height: 100vh; overflow-x: hidden;">
    <div class="container-fluid flex-container">
        
        <!-- Header -->
        <div class="flex-header">
            <?php include 'components/header.php'; ?>
            
            <!-- Información de Temporada -->
            <?php include 'components/temporada-info.php'; ?>
            
            <!-- Loading -->
            <?php include 'components/loading.php'; ?>
        </div>
        
        <!-- Contenido Principal -->
        <div class="flex-content">
            <!-- Tabs Container -->
            <?php include 'components/tabs-container.php'; ?>
        </div>
        
    </div>

    <!-- Contenedores de componentes -->
    <?php include 'components/alert-container.php'; ?>
    <?php include 'components/modals.php'; ?>

    <!-- Scripts -->
    <?php include 'components/scripts.php'; ?>
</body>
</html>