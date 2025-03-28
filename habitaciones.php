<?php
// habitaciones.php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];

// Manejar errores solo en desarrollo
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}
error_reporting(E_ALL);

$error = '';
$elementos = [];
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
try {
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
    $estado = isset($_GET['estado']) ? $_GET['estado'] : '';

    $sql = "SELECT * FROM elementos";
    $conditions = [];
    $params = [];

    if ($tipo) {
        $conditions[] = "tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    if ($estado) {
        $conditions[] = "estado = :estado";
        $params[':estado'] = $estado;
    }
    if ($busqueda) {
        $conditions[] = "(nombre LIKE :busqueda OR descripcion LIKE :busqueda)";
        $params[':busqueda'] = "%$busqueda%";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => ''];
    if (isset($_POST['borrar_elemento'])) {
        try {
            $id = intval($_POST['id']);
    
            // Primero eliminamos reservas asociadas (si existen)
            $stmt = $pdo->prepare('DELETE FROM detalles_reserva WHERE elemento_id = ?');
            $stmt->execute([$id]);
    
            // Luego eliminamos el elemento
            $stmt = $pdo->prepare('DELETE FROM elementos WHERE id = ?');
            if ($stmt->execute([$id])) {
                $response['success'] = true;
                $stmt = $pdo->query('SELECT id, codigo, nombre, descripcion, tipo, estado, precio, inapam_valido, creado_at, updated_at FROM elementos');
                $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['elementos'] = $elementos;
            } else {
                $response['error'] = "Error al borrar el elemento.";
            }
        } catch (PDOException $e) {
            $response['error'] = "Error de base de datos: " . $e->getMessage();
        }
    
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['editar_elemento'])) {
        try {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare('SELECT * FROM elementos WHERE id = ?');
            $stmt->execute([$id]);
            $elemento = $stmt->fetch();

            if ($elemento) {
                $estado = $_POST['estado'] ?? $elemento['estado'];
                $nombre = trim($_POST['nombre'] ?? $elemento['nombre']);
                $descripcion = trim($_POST['descripcion'] ?? $elemento['descripcion']);
                $tipo = $_POST['tipo'] ?? $elemento['tipo'];
                $precio = is_numeric($_POST['precio']) ? floatval($_POST['precio']) : $elemento['precio'];
                $inapam_valido = isset($_POST['inapam_valido']) ? 1 : 0;


                $stmt = $pdo->prepare('SELECT id FROM elementos WHERE nombre = ? AND tipo = ? AND id != ?');
                $stmt->execute([$nombre, $tipo, $id]);
                if ($stmt->fetch()) {
                    $response['error'] = "El nombre del elemento ya existe para este tipo.";
                } else {
                    $stmt = $pdo->prepare('UPDATE elementos SET nombre = ?, descripcion = ?, tipo = ?, estado = ?, precio = ? WHERE id = ?');
                    if ($stmt->execute([$nombre, $descripcion, $tipo, $estado, $precio, $id])) {
                        $response['success'] = true;
                        $stmt = $pdo->query('SELECT id, codigo, nombre, descripcion, tipo, estado, precio, created_at, updated_at FROM elementos');
                        $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $response['elementos'] = $elementos;
                    } else {
                        $response['error'] = "Error al actualizar el elemento.";
                    }
                }
            } else {
                $response['error'] = "Elemento no encontrado.";
            }
        } catch (PDOException $e) {
            $response['error'] = "Error de base de datos: " . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    } else {
        try {
            $tipo = $_POST['tipo'] ?? '';

            if ($tipo == 'habitacion') {
                $nombre = trim($_POST['numero_habitacion']);
                $descripcion = trim($_POST['descripcion']);
                if ($descripcion === 'otro' && isset($_POST['descripcion_personalizada'])) {
                    $descripcion = trim($_POST['descripcion_personalizada']);
                }
                $estado = $_POST['estado'] ?? 'disponible';
                $precio = is_numeric($_POST['precio']) ? floatval($_POST['precio']) : 0.00;

                $stmt = $pdo->prepare('SELECT id FROM elementos WHERE nombre = ? AND tipo = ?');
                $stmt->execute([$nombre, 'habitacion']);

                if ($stmt->fetch()) {
                    $response['error'] = "La habitación ya existe.";
                } else {
                    $stmt = $pdo->prepare('SELECT MAX(codigo) FROM elementos WHERE tipo = ?');
                    $stmt->execute([$tipo]);
                    $maxCodigo = $stmt->fetchColumn();
                    $numero = $maxCodigo ? intval(substr($maxCodigo, 1)) + 1 : 1;
                    $codigo = 'H' . str_pad($numero, 4, '0', STR_PAD_LEFT);

                    $stmt = $pdo->prepare('INSERT INTO elementos (codigo, nombre, descripcion, tipo, estado, precio) VALUES (?, ?, ?, ?, ?, ?)');
                    if ($stmt->execute([$codigo, $nombre, $descripcion, $tipo, $estado, $precio])) {
                        $response['success'] = true;
                        $stmt = $pdo->query('SELECT id, codigo, nombre, descripcion, tipo, estado, precio, created_at, updated_at FROM elementos');
                        $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $response['elementos'] = $elementos;
                    }
                }
            } elseif ($tipo == 'servicio') {
                $nombre = trim($_POST['nombre_servicio']);
                $descripcion = trim($_POST['descripcion']);
                $estado = 'activo';
                $precio = is_numeric($_POST['precio']) ? floatval($_POST['precio']) : 0.00;

                $stmt = $pdo->prepare('SELECT id FROM elementos WHERE nombre = ? AND tipo = ?');
                $stmt->execute([$nombre, 'servicio']);

                if ($stmt->fetch()) {
                    $response['error'] = "El servicio ya existe.";
                } else {
                    $stmt = $pdo->prepare('SELECT MAX(codigo) FROM elementos WHERE tipo = ?');
                    $stmt->execute([$tipo]);
                    $maxCodigo = $stmt->fetchColumn();
                    $numero = $maxCodigo ? intval(substr($maxCodigo, 1)) + 1 : 1;
                    $codigo = 'S' . str_pad($numero, 4, '0', STR_PAD_LEFT);

                    $stmt = $pdo->prepare('INSERT INTO elementos (codigo, nombre, descripcion, tipo, estado, precio) VALUES (?, ?, ?, ?, ?, ?)');
                    if ($stmt->execute([$codigo, $nombre, $descripcion, $tipo, $estado, $precio])) {
                        $response['success'] = true;
                        $stmt = $pdo->query('SELECT id, codigo, nombre, descripcion, tipo, estado, precio, created_at, updated_at FROM elementos');
                        $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $response['elementos'] = $elementos;
                    }
                }
            }
        } catch (PDOException $e) {
            $response['error'] = "Error de base de datos: " . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Habitaciones y Servicios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="logo_hotel.jpg">
    <style>
        /* Estilos generales */
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
            font-family: 'Roboto', sans-serif;
            background-color: var(--color-background);
            color: var(--color-text);
            margin: 0;
            padding: 0;
            display: flex;
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
        /* Contenido principal */
        .content {
            flex-grow: 1;
            overflow-y: auto;
            margin-left: 250px; /* Asegura que el contenido principal no se superponga con la barra lateral */
            padding: 30px;
            transition: margin 0.3s;
        }
        /* Formularios */
        .formulario-unificado {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .formulario-unificado input,
        .formulario-unificado textarea,
        .formulario-unificado select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .formulario-unificado button {
            background-color: var(--color-primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s;
            width: 100%;
        }
        .formulario-unificado button:hover {
            background-color: #2980b9;
        }
        /* Estilos para la barra de búsqueda */
        .search-bar {
            display: flex;
            margin-bottom: 20px;
        }
        .search-bar .form-group {
            margin-right: 10px;
        }
        .search-bar .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        .search-bar .form-group select {
            width: 150px;
        }
        /* Botones de acción */
        /* Botón para togglear el sidebar en móvil */
        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 15px;
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
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                height: 100%;
                z-index: 999;
                transition: left 0.3s ease;
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
            .overlay.active {
                display: block;
            }
        }
        /* Notificaciones */
        .notificacion {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            opacity: 0.95;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .notificacion.success { background-color: #2ecc71; }
        .notificacion.error { background-color: #e74c3c; }
        /* Estilos para la tabla */
        .tabla-scroll {
            width: 100%;
            max-height: 500px; /* Establece una altura máxima para el contenedor */
            overflow-x: auto;   /* Habilita el desplazamiento horizontal */
            overflow-y: auto;   /* Habilita el desplazamiento vertical */
            -webkit-overflow-scrolling: touch; /* Para suavizar el desplazamiento en dispositivos móviles */
            border: 1px solid #ddd; /* Opcional: Añade un borde al contenedor para mejorar la apariencia */
            border-radius: 4px;    /* Opcional: Redondea los bordes del contenedor */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Opcional: Añade una sombra para dar profundidad */
        }

        .tabla-scroll table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px; /* Ajusta este valor según el contenido de tu tabla */
        }

        .tabla-scroll th, 
        .tabla-scroll td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .tabla-scroll th {
            background: var(--color-primary);
            color: var(--color-letters);
            position: sticky;
            top: 0;
            z-index: 2;
            /* Esto hará que el encabezado de la tabla se mantenga fijo al hacer scroll vertical */
        }

        .tabla-scroll tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        /* Estilos adicionales para los botones de acciones */
        .botones-acciones {
            display: flex;
            gap: 5px;
        }

        .botones-acciones a, 
        .botones-acciones button {
            text-decoration: none;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            color: var(--color-white);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .botones-acciones a {
            background: #3498db;
        }

        .botones-acciones button {
            background: #e74c3c;
        }

        .botones-acciones a:hover, 
        .botones-acciones button:hover {
            opacity: 0.9;
        }
        /* Estilos para la tabla */
        .tabla-scroll {
            width: 100%;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .tabla-scroll table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .tabla-scroll th, 
        .tabla-scroll td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .tabla-scroll th {
            background: var(--color-primary);
            color: var(--color-letters);
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .tabla-scroll tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        /* Estilos adicionales para los botones de acciones */
        .botones-acciones {
            display: flex;
            gap: 5px;
        }

        .botones-acciones a, 
        .botones-acciones button {
            text-decoration: none;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            color: var(--color-white);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .botones-acciones a {
            background: #3498db;
        }

        .botones-acciones button {
            background: #e74c3c;
        }

        .botones-acciones a:hover, 
        .botones-acciones button:hover {
            opacity: 0.9;
        }
        /* Estilos para la tabla */
        .tabla-scroll {
            width: 100%;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .tabla-scroll table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .tabla-scroll th, 
        .tabla-scroll td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .tabla-scroll th {
            background: var(--color-primary);
            color: var(--color-letters);
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .tabla-scroll tr:nth-child(even) {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Botón para togglear el sidebar en móvil -->
    <button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>

    <!-- Sidebar -->
    <aside class="sidebar">
    <h2><i class="fas fa-columns"></i> Menú</h2>
    <ul>
    <li><a href="bottom_menu.php"><i class="fas fa-home"></i> Inicio</a></li>
        <?php if ($rol === 'admin'): ?>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
        <?php endif; ?>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="cancelaciones.php"><i class="fas fa-tools"></i> Cancelaciones</a></li>
        <li><a href="configuracion.php"><i class="fas fa-cogs"></i> Configuración</a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</aside>
    <!-- Overlay para móvil -->
    <div class="overlay"></div>
    <!-- Contenido principal -->
    <div class="content">
        <h1>Gestión de Habitaciones y Servicios</h1>
        <?php if ($error) { echo "<p class='error-message'>{$error}</p>"; } ?>

        <!-- Formulario unificado -->
        <div class="formulario-unificado">
            <form method="post" id="form-unificado">
                <div class="selector-tipo">
                    <label for="tipo">Elegir familia:</label>
                    <select name="tipo" id="tipo" class="form-control" required>
                        <option value="habitacion">Habitación</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>

                <div id="contenido-condicional">
                    <!-- Formulario para agregar habitación -->
                    <div id="form-habitacion">
                        <h2>Agregar Habitación</h2>
                        <div class="form-group">
                            <label for="numero_habitacion_add">Número de Habitación:</label>
                            <input type="text" id="numero_habitacion_add" name="numero_habitacion" required>
    </div>
    <div class="habitacion-item">
    <select name="descripcion" class="Habitacion_tipo" id="habitacion_tipo">
        <option value="">Seleccione una opción</option>
        <option value="Habitacion Estandar">Habitación Estándar</option>
        <option value="Master Suite (Habitacion Cuadruple)">Master Suite (Habitación Cuádruple)</option>
        <option value="Bungalow Chico">Bungalow Chico</option>
        <option value="Habitacion Sencilla 1">Habitación Sencilla</option>
        <option value="Suite, KingSize y dos sofas">Suite, KingSize y dos sofás</option>
        <option value="Habitacion Sencilla 2">Habitación Sencilla</option>
        <option value="Suite Jr">Suite Jr</option>
        <option value="Suite">Suite</option>
        <option value="otro">Otro</option>
    </select>
    <input type="number" step="0.01" min="0" name="precio" id="precio_habitacion"
    class="form-control mt-2" placeholder="Precio por noche" required> 
    <div class="form-check mt-2">
  <input class="form-check-input" type="checkbox" id="inapam_habitacion" name="inapam_valido" value="1">
  <label class="form-check-label" for="inapam_habitacion">
    Válido con INAPAM
  </label>
</div>

            <!-- Campo oculto que aparece solo si elige "Otro" -->
            <input type="text" name="descripcion_personalizada" id="descripcion_personalizada" 
                placeholder="Especifica tipo de habitación" 
                style="display:none; margin-top: 10px;" class="form-control" />

            <button type="submit" name="agregar" class="btn btn-primary mt-2">
                <i class="fas fa-plus"></i> Agregar
            </button>
        </div>
    
                    <!-- Formulario para agregar servicio -->
                    <div id="form-servicio">
                        <h2>Agregar Servicio</h2>
                        <div class="form-group">
                            <label for="nombre_servicio_add">Nombre del Servicio:</label>
                            <input type="text" id="nombre_servicio_add" name="nombre_servicio" required>
                        </div>
                        <div class="form-group">
                            <label for="descripcion_add">Descripción:</label>
                            <textarea id="descripcion_add" name="descripcion" maxlength="300"></textarea>
                        </div>

                        <input type="number" step="0.01" min="0" name="precio" id="precio_servicio"
                         class="form-control mt-2" placeholder="Precio del servicio" required>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="inapam_servicio" name="inapam_valido" value="1">
                    <label class="form-check-label" for="inapam_servicio">
                        Válido con INAPAM
                    </label>
                </div>

                </div>

                <button type="submit" name="agregar" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Agregar
                </button>
            </form>
            </div>
        </div>

        <!-- Formulario de filtros y búsqueda -->
        <h2>Lista de Elementos</h2>
        <div class="search-bar mb-4">
            <form id="filtros-form">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <option value="habitacion" <?= $tipo === 'habitacion' ? 'selected' : '' ?>>Habitaciones</option>
                            <option value="servicio" <?= $tipo === 'servicio' ? 'selected' : '' ?>>Servicios</option>
                        </select>
                    </div>
                    
                    <div class="col-auto">
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="disponible" <?= $estado === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="ocupada" <?= $estado === 'ocupada' ? 'selected' : '' ?>>Ocupada</option>
                            <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Mantenimiento</option>
                        </select>
                    </div>
                    
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
        <input type="text" id="busqueda" placeholder="Buscar..." class="form-control" style="margin-bottom: 10px;">
        <div class="tabla-scroll">       
            <table>
             <thead>
                <tr>
                        <th>Código</th>
                        <th>Nombre/Numero</th>
                        <th>Descripcion</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Precio</th>
                        <th>Creado At</th>
                        <th>Updated At</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-elementos-body">
                    <?php foreach ($elementos as $e): ?>
                    <tr>
                        <td><?= $e['codigo'] ?></td>
                        <td><?= htmlspecialchars($e['nombre']) ?></td>
                        <td><?= htmlspecialchars($e['descripcion']) ?></td>
                        <td><?= htmlspecialchars($e['tipo']) ?></td>
                        <td><?= htmlspecialchars($e['estado']) ?></td>
                        <td><?= htmlspecialchars($e['precio']) ?></td>
                        <td><?= htmlspecialchars($e['creado_at']) ?></td>
                        <td><?= htmlspecialchars($e['updated_at']) ?></td>
                        <td>
                            <form method="post" class="form-borrar">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button type="submit" name="borrar_elemento" class="boton-borrar">
                                    <i class="fas fa-trash-alt"></i> Borrar
                                </button>
                            </form>
                            <a href="editar_elemento.php?id=<?= $e['id'] ?>" class="boton-editar">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const tipoForm = document.getElementById('tipo');
        const contenidoCondicional = document.getElementById('contenido-condicional');

        tipoForm.addEventListener('change', actualizarFormulario);
        actualizarFormulario();

        function actualizarFormulario() {
            const tipoSeleccionado = tipoForm.value;
            contenidoCondicional.innerHTML = ''; // Limpiar contenido previo

            if (tipoSeleccionado === 'habitacion') {
                contenidoCondicional.innerHTML = `
                        <h2>Agregar Habitación</h2>
                        <div class="form-group">
                            <label for="numero_habitacion_add">Número de Habitación:</label>
                            <input type="text" id="numero_habitacion_add" name="numero_habitacion" required>
                        </div>
                        <div class="habitacion-item">
                            <select name="descripcion" class="Habitacion_tipo" id="habitacion_tipo">
                                <option value="">Seleccione una opción</option>
                                <option value="Habitación Estandar">Habitación Estándar</option>
                                <option value="Master Suite">Master Suite</option>
                                <option value="Bungalow Chico">Bungalow Chico</option>
                                <option value="Bungalow Mediano">Bungalow Mediano</option>
                                <option value="Habitacion Sencilla">Habitación Sencilla</option>
                                <option value="Suite King">Suite King</option>
                                <option value="Suite Jr">Suite Jr</option>
                                <option value="Suite">Suite</option>
                                <option value="otro">Otro</option>
                                <input type="number" step="0.01" min="0" name="precio" id="precio_servicio"
                                class="form-control mt-2" placeholder="Precio del servicio" required>
                                </select>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="inapam_habitacion" name="inapam_valido" value="1">
                                    <label class="form-check-label" for="inapam_habitacion">
                                        Válido con INAPAM
                                    </label>
                            </div>
                            <input type="text" name="descripcion_personalizada" id="descripcion_personalizada"
                                placeholder="Especifica tipo de habitación"
                                style="display:none; margin-top: 10px;" class="form-control" />
                            <button type="submit" name="agregar" class="btn btn-primary mt-2">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                        `;
                        document.getElementById('habitacion_tipo').addEventListener('change', function () {
                const inputOtro = document.getElementById('descripcion_personalizada');
                if (this.value === 'otro') {
                    inputOtro.style.display = 'block';
                    inputOtro.required = true;
                } else {
                    inputOtro.style.display = 'none';
                    inputOtro.required = false;
                }
            });

            } else if (tipoSeleccionado === 'servicio') {
                contenidoCondicional.innerHTML = `
                    <h2>Agregar Servicio</h2>
                    <div class="form-group">
                        <label for="nombre_servicio_add">Nombre del Servicio:</label>
                        <input type="text" id="nombre_servicio_add" name="nombre_servicio" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion_add">Descripción:</label>
                        <textarea id="descripcion_add" name="descripcion" maxlength="300"></textarea>
                    </div>
                    <input type="number" step="0.01" min="0" name="precio" id="precio_servicio"
                    class="form-control mt-2" placeholder="Precio del servicio" required>
                      <button type="submit" name="agregar" class="btn btn-primary">
                      <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="inapam_servicio" name="inapam_valido" value="1">
                            <label class="form-check-label" for="inapam_servicio">
                                Válido con INAPAM
                            </label>
                            </div>

                    <i class="fas fa-plus"></i> Agregar
                </button>
                `;
            }
        }
           

        // Envío del formulario unificado
        const formUnificado = document.getElementById('form-unificado');
        formUnificado.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('habitaciones.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    actualizarTablaElementos(data.elementos); // Recibir todos los elementos
                    formUnificado.reset();
                    mostrarNotificacion('Operación exitosa!', 'success');
                } else {
                    mostrarNotificación(data.error || 'Error en la operación', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión', 'error');
            });
        });

        // Función para mostrar notificaciones
        const mostrarNotificacion = (mensaje, tipo) => {
            const notificacion = document.createElement('div');
            notificacion.className = `notificacion ${tipo}`;
            notificacion.textContent = mensaje;
            document.body.appendChild(notificacion);

            setTimeout(() => {
                notificacion.remove();
            }, 3000);
        };

        function actualizarTablaElementos(elementos) {
            const tbody = document.getElementById('tabla-elementos-body');
            // Verificar si elementos es un array
            if (Array.isArray(elementos)) {
                tbody.innerHTML = elementos.map(e => `
                    <tr>
                        <td>${e.codigo}</td>
                        <td>${e.nombre}</td>
                        <td>${e.descripcion || ''}</td>
                        <td>${e.tipo}</td>
                        <td>${e.precio}</td>
                        <td>${e.estado}</td>
                        <td>${e.created_at}</td>
                        <td>${e.updated_at}</td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="id" value="${e.id}">
                                <button type="submit" name="borrar_elemento" class="boton-borrar">
                                    <i class="fas fa-trash-alt"></i> Borrar
                                </button>
                            </form>
                            <a href="editar_elemento.php?id=${e.id}" class="boton-editar">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        </td>
                    </tr>
                `).join('');
            } else {
                // Si elementos no es un array, muestra un mensaje o deja la tabla vacía
                tbody.innerHTML = '';
            }
        }

        document.getElementById('filtros-form').addEventListener('input', function(e) {
            e.preventDefault();
            
            const formData = new URLSearchParams(new FormData(this));
            
            fetch('habitaciones.php?' + formData.toString())
                .then(response => response.json())
                .then(data => {
                    actualizarTablaElementos(data.elementos);
                });
        });

        // Función de confirmación para borrar
        function confirmarBorrado(event) {
            if (!confirm('¿Estás seguro de querer borrar este elemento?') ){ 
                event.preventDefault();
            }
        }

        $(document).ready(function(){
            $('#busqueda').on('input', function() {
                var query = $(this).val();
                $.ajax({
                    url: 'buscar_elementos.php',
                    method: 'GET',
                    data: { q: query },
                    success: function(data) {
                        $('#tabla-elementos-body').html(data);
                    },
                    error: function() {
                        $('#tabla-elementos-body').html('<tr><td colspan="8">Error al buscar.</td></tr>');
                    }
                });
            });
        });

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
    });
    </script>
</body>
</html>