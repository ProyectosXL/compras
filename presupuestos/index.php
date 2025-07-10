
<?php
// presupuestos/index.php - Archivo principal simplificado
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Presupuesto de Compras</title>
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/obtener-presupuesto.css" rel="stylesheet">
    
    <!-- Estilos específicos -->
    <?php include 'components/styles.php'; ?>
</head>
<body>
    <div class="container-fluid py-2 pb-1">
        <div class="container-fluid py-2 pb-1" style="margin-bottom: 0; padding-bottom: 0.5rem;">
            
            <!-- Header -->
            <?php include 'components/header.php'; ?>
            
            <!-- Información de Temporada -->
            <?php include 'components/temporada-info.php'; ?>
            
            <!-- Loading -->
            <?php include 'components/loading.php'; ?>
            
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