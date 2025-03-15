<?php
session_start();
require 'config.php';
// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener el rol y el nombre del usuario desde la sesión
$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina_actual > 1) ? ($pagina_actual * $registros_por_pagina) - $registros_por_pagina : 0;

// Obtener datos de filtros
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';
$huesped = $_GET['huesped'] ?? '';
$habitacion = $_GET['habitacion'] ?? '';

// Construir consulta principal
$sql = "SELECT 
            r.id AS recibo_id,
            r.subtotal,
            r.descuento,
            r.total_pagar,
            r.total_pagado,
            r.saldo,
            r.estado_pago,
            h.nombre AS huesped_nombre,
            GROUP_CONCAT(e.nombre SEPARATOR ', ') AS elementos,
            SUM(dr.tarifa) AS tarifa_total,
            (SELECT COALESCE(SUM(a.monto), 0) FROM anticipos a WHERE a.recibo_id = r.id) AS anticipo_total
        FROM recibos r
        JOIN huespedes h ON r.id_huesped = h.id
        JOIN detalles_reserva dr ON r.id = dr.recibo_id
        JOIN elementos e ON dr.elemento_id = e.id
        WHERE 1=1";

$params = [];

// Aplicar filtros
if (!empty($check_in)) {
    $sql .= " AND r.check_in >= :check_in";
    $params[':check_in'] = $check_in;
}

if (!empty($check_out)) {
    $sql .= " AND r.check_out <= :check_out";
    $params[':check_out'] = $check_out;
}

if (!empty($huesped)) {
    $sql .= " AND h.nombre LIKE :huesped";
    $params[':huesped'] = "%$huesped%";
}

if (!empty($habitacion)) {
    $sql .= " AND e.nombre LIKE :habitacion";
    $params[':habitacion'] = "%$habitacion%";
}

$sql .= " GROUP BY r.id
          ORDER BY r.id DESC
          LIMIT $inicio, $registros_por_pagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Consulta para total de registros
$sql_count = "SELECT COUNT(DISTINCT r.id)
            FROM recibos r
            JOIN huespedes h ON r.id_huesped = h.id
            JOIN detalles_reserva dr ON r.id = dr.recibo_id
            JOIN elementos e ON dr.elemento_id = e.id
            WHERE 1=1";

if (!empty($check_in)) $sql_count .= " AND r.check_in >= '$check_in'";
if (!empty($check_out)) $sql_count .= " AND r.check_out <= '$check_out'";
if (!empty($huesped)) $sql_count .= " AND h.nombre LIKE '%$huesped%'";
if (!empty($habitacion)) $sql_count .= " AND e.nombre LIKE '%$habitacion%'";

$total_registros = $pdo->query($sql_count)->fetchColumn();
$paginas = ceil($total_registros / $registros_por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Reservaciones</title>
    <link rel="icon" type="image/png" sizes="32x32" href="logo_hotel.jpg">
    <style>
     :root {
         --color-primary: #2c3e50;
         --color-secondary: #2ecc71;
         --color-accent: #e74c3c;
         --color-background: #f5f6fa;
         --color-text: #2c3e50;
         --color-border: #bdc3c7;
         --color-shadow: rgba(0, 0, 0, 0.1);
         --color-letters: #f5f6fa;
        }

        body { 
                font-family: 'Segoe UI', system-ui, sans-serif; 
                margin: 0; 
                padding: 0; 
                background: #f8f9fa; 
                position: relative;
                min-height: 100vh;
            }
        a {
            color: var(--color-primary);
            text-decoration: none;
            }
        a:hover {
                text-decoration: underline;
            }
            /* Sidebar */
        .sidebar {
            position: fixed; /* Fija la barra lateral en una posición específica */
            top: 0;          /* Posición desde la parte superior */
            left: 0;         /* Posición desde la izquierda */
            width: 250px;
            height: 100vh;   /* Asegura que la barra lateral ocupe toda la altura de la ventana */
            background-color: var(--color-primary);
            color: white;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            /* Elimina la propiedad 'transition' si no necesitas que la barra lateral tenga una transición */
            /* transition: left 0.3s ease; */
            overflow: hidden; /* Asegura que no haya desplazamiento dentro de la barra lateral */
            z-index: 1000;   /* Asegura que la barra lateral esté por encima de otros elementos */
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5em;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            margin: 20px 0;
        }

        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 1.1em;
            padding: 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .sidebar ul li a i {
            margin-right: 10px;
            font-size: 1.2em;
        }

        .sidebar ul li a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .toggle-sidebar {
                display: none;
                position: fixed;
                top: 1px;
                left: 15px;
                background: var(--color-primary);
                color: white;
                border: none;
                padding: 10px;
                border-radius: 4px;
                z-index: 1000;
                }
                /* Overlay para el sidebar en móvil */
                .overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 998;
                }
        /* Contenido principal */
        .contenido {
            margin-left: 250px;
            padding: 30px;
            transition: margin 0.3s;
        }

        /* Estilos para dispositivos móviles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px; /* Oculto por defecto en móvil */
                height: 100%;
                z-index: 999;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .toggle-sidebar {
                display: block;
            }

            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 998;
                transition: display 0.3s ease;
            }

            .overlay.active {
                display: block;
            }

            .contenido {
                margin-left: 0;
                padding: 20px;
                padding-top: 60px;
            }
        }

        @media (max-width: 480px) {
            .contenido {
                padding: 15px;
                padding-top: 60px;
            }

            h1 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }

            .sidebar ul li {
                padding: 12px 15px;
            }

            .sidebar h2 {
                font-size: 1.3rem;
            }
        }

        /* Botón del menú */
        .menu-btn {
            display: none;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }

        /* Menú lateral en dispositivos móviles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px; /* Oculto por defecto en móvil */
                height: 100%;
                z-index: 999;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .toggle-sidebar {
                display: block;
            }

            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 998;
                transition: display 0.3s ease;
            }

            .overlay.active {
                display: block;
            }

            .contenido {
                margin-left: 0;
                padding: 20px;
                padding-top: 60px;
            }
        }
        /* Tabla responsiva */
        .tabla-contenedor {
            background: var(--color-blanco);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        th {
            background: var(--color-primario);
            color: var(--color-blanco);
            font-weight: 600;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        /* Acciones */
        .acciones a {
            text-decoration: none;
            margin: 0 5px;
            padding: 8px 12px;
            border-radius: 5px;
            color: var(--color-blanco);
            font-size: 0.9rem;
            transition: opacity 0.3s;
            display: inline-block;
        }

        .btn-editar { background: #f1c40f; }
        .btn-eliminar { background: #e74c3c; }
        .btn-imprimir { background: #3498db; }

        .acciones a:hover {
            opacity: 0.9;
        }

        /* Paginación */
        .paginacion {
            margin-top: 25px;
            text-align: center;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .paginacion a {
            padding: 8px 15px;
            margin: 0 3px;
            text-decoration: none;
            color: var(--color-blanco);
            background: var(--color-primario);
            border-radius: 5px;
            transition: background 0.3s;
        }

        .paginacion a.activo {
            background: var(--color-secundario);
            cursor: default;
        }

        .paginacion a:hover:not(.activo) {
            background: #1a252f;
        }

        /* Filtros */
        .filtros {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .filtros h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 1.5rem;
        }
        .filtros form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filtros label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
            font-weight: 500;
        }

        .filtros input[type="date"],
        .filtros input[type="text"] {
            width: 200px;
            padding: 8px 1px;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            transition: border-color 0.3s;
        }

        .filtros input[type="date"]:focus,
        .filtros input[type="text"]:focus {
            border-color: #3498db;
            outline: none;
        }

        .filtros button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background-color: #3498db;
            color: #ffffff;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .filtros button:hover {
            background-color: #2980b9;
        }

        .filtros button[type="button"] {
            background-color: #95a5a6;
        }

        .filtros button[type="button"]:hover {
            background-color: #7f8c8d;
        }

        /* Estilos para el contenedor de filtros en dispositivos móviles */
        @media (max-width: 768px) {
            .filtros form {
                flex-direction: column;
                align-items: stretch;
            }

            .filtros input[type="date"],
            .filtros input[type="text"] {
                width: 100%;
            }

            .filtros button {
                width: 100%;
            }
        }

        /* Menú lateral en dispositivos móviles */
        @media (max-width: 480px) {
            .contenido {
                padding: 15px;
                padding-top: 60px;
            }

            h1 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }

        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px; /* Oculto por defecto en móvil */
                height: 100%;
                z-index: 999;
            }
            .sidebar.active {
                left: 0;
            }
            .content {
                margin-left: 0;
            }
            .toggle-sidebar {
                display: block;
            }
            /* Overlay opcional para enfocar el sidebar */
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }
            .overlay.active {
                display: block;
            }
            }

            @media (max-width: 480px) {
            .search-container {
                flex-direction: column;
                align-items: flex-start;
            }
            }
            

    </style>   
</head>
<body>
    
<button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
<!-- Menú Lateral -->
<aside class="sidebar">
        <h2>Menú</h2>
        <ul>
        <li><a href="bottom_menu.php"><i class="fas fa-home"></i> Inicio</a></li>
        <?php if ($rol === 'admin'): ?>
        
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
        <?php endif; ?>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
</aside>

<div class="overlay"></div>
<!-- Contenido Principal -->
<main class="contenido">
    <h1>Registros de Caja</h1>
    <h1>Lista de Registros</h1>
    
    <!-- Filtros -->
    <div class="filtros">
        <h2>Filtros</h2>
        <form id="formulario-filtros" method="get">
            <label for="check_in">Check-in:</label>
            <input type="date" id="check_in" name="check_in" value="<?= htmlspecialchars($check_in) ?>">

            <label for="check_out">Check-out:</label>
            <input type="date" id="check_out" name="check_out" value="<?= htmlspecialchars($check_out) ?>">

            <label for="huesped">Huésped:</label>
            <input type="text" id="huesped" name="huesped" value="<?= htmlspecialchars($huesped) ?>">

            <label for="habitacion">Habitación:</label>
            <input type="text" id="habitacion" name="habitacion" value="<?= htmlspecialchars($habitacion) ?>">

            <button type="submit">Aplicar Filtros</button>
            <button type="button" onclick="resetFiltros()">Resetear Filtros</button>
            <div class="filtro">

        </form>
    </div>
    <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Huésped</th>
                        <th>Elementos</th>
                        <th>Tarifa Total</th>
                        <th>Subtotal</th>
                        <th>Descuento</th>
                        <th>Total Pagar</th>
                        <th>Anticipo</th>
                        <th>Cambio/Restante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = ($pagina_actual - 1) * $registros_por_pagina; ?>
                    <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td><?= ++$contador ?></td>
                        <td><?= htmlspecialchars($registro['huesped_nombre']) ?></td>
                        <td><?= htmlspecialchars($registro['elementos']) ?></td>
                        <td>$<?= number_format($registro['tarifa_total'], 2) ?></td>
                        <td>$<?= number_format($registro['subtotal'], 2) ?></td>
                        <td>$<?= number_format($registro['descuento'], 2) ?></td>
                        <td>$<?= number_format($registro['total_pagar'], 2) ?></td>
                        <td>$<?= number_format($registro['anticipo_total'], 2) ?></td>
                        <td>$<?= number_format($registro['saldo'], 2) ?></td>
                       
                        <td class="acciones">
                            <a href="editar_reserva.php?id=<?= $registro['recibo_id'] ?>" class="btn-editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="eliminar_reserva.php?id=<?= $registro['recibo_id'] ?>" 
                               class="btn-eliminar" 
                               onclick="return confirm('¿Eliminar este recibo?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="javascript:imprimirRecibo(<?= $registro['recibo_id'] ?>)" 
                               class="btn-imprimir">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<!-- Paginación -->
<div class="paginacion">
    <?php if ($pagina_actual > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])) ?>">&laquo; Anterior</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $paginas; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>" class="<?= ($pagina_actual == $i) ? 'activo' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($pagina_actual < $paginas): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])) ?>">Siguiente &raquo;</a>
    <?php endif; ?>
        </div>
<script>

   // Función para imprimir
    function imprimirRecibo(id) {
        window.open(`imprimir_recibo.php?id=${id}`, '_blank').print();
    }


    // Resetear filtros
    function resetFiltros() {
        window.location.href = 'recibos.php';
    }
      // Toggle del sidebar en móvil
      const toggleButton = document.querySelector('.toggle-sidebar');
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.overlay');

      toggleButton.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
      });

      overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
      });
    
</script>

</body>
</html>