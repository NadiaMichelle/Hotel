<?php
// editar_elemento.php
session_start();
require 'config.php';

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
$elemento = [];

// Obtener el ID del elemento a editar
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Obtener el elemento actual
        $stmt = $pdo->prepare('SELECT * FROM elementos WHERE id = ?');
        $stmt->execute([$id]);
        $elemento = $stmt->fetch();

        if (!$elemento) {
            $error = "Elemento no encontrado.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $estado = $_POST['estado'];

    try {
        // Validar que el nombre y tipo no se dupliquen
        $stmt = $pdo->prepare('SELECT id FROM elementos WHERE nombre = ? AND tipo = ? AND id != ?');
        $stmt->execute([$nombre, $tipo, $id]);
        if ($stmt->fetch()) {
            $error = "El nombre del elemento ya existe para este tipo.";
        } else {
            // Actualizar el elemento
            $stmt = $pdo->prepare('UPDATE elementos SET nombre = ?, descripcion = ?, tipo = ?, estado = ? WHERE id = ?');
            if ($stmt->execute([$nombre, $descripcion, $tipo, $estado, $id])) {
                // Redirigir de vuelta a la página principal
                header('Location: habitaciones.php');
                exit;
            } else {
                $error = "Error al actualizar el elemento.";
            }
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Elemento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos para el formulario de edición */
        .formulario-edicion {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--color-shadow);
            margin-bottom: 20px;
        }
        .formulario-edicion .form-group {
            margin-bottom: 15px;
        }
        .formulario-edicion label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .formulario-edicion input,
        .formulario-edicion textarea,
        .formulario-edicion select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            font-size: 1em;
            background-color: #ecf0f1;
            box-sizing: border-box;
        }
        .formulario-edicion button {
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
        .formulario-edicion button:hover {
            background-color: #2980b9;
        }
        /* Estilos para el sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--color-primary);
            color: white;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: left 0.3s ease;
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
        /* Estilos generales */
        :root {
            --color-primary: #2c3e50;
            --color-secondary: #2ecc71;
            --color-accent: #e74c3c;
            --color-background: #f5f6fa;
            --color-text: #2c3e50;
            --color-border: #bdc3c7;
            --color-shadow: rgba(0, 0, 0, 0.1);
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
        /* Botón de cancelar */
        .btn-cancelar {
            background-color: var(--color-accent);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-cancelar:hover {
            background-color: #c0392b;
        }
        /* Contenido principal */
        .content {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            /* Sin margin-left para que en PC el contenido quede pegado al sidebar */
            transition: margin-left 0.3s ease;
        }
    /* Estilos para el formulario de edición */
.formulario-edicion {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px var(--color-shadow);
    margin-bottom: 20px;
    margin-left: 270px; /* Añadido para dar espacio al sidebar en PC */
    transition: margin-left 0.3s ease;
}

/* Estilos para el sidebar */
.sidebar {
    width: 250px;
    background-color: var(--color-primary);
    color: white;
    padding: 20px;
    box-sizing: border-box;
    position: fixed; /* Asegura que el sidebar esté fijo en su posición */
    top: 0;
    left: 0; /* Cambiado de -250px a 0 para que esté visible en PC */
    height: 100%;
    z-index: 998; /* Asegura que el sidebar esté detrás del overlay en móviles */
    transition: left 0.3s ease;
}

.sidebar.active {
    left: 0;
}

/* Botón para togglear el sidebar en móvil */
.toggle-sidebar {
    display: none; /* Ocultar en PC */
    position: fixed;
    top: 15px;
    left: 15px;
    background: var(--color-primary);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 4px;
    z-index: 999;
}

/* Overlay para el sidebar en móvil */
.overlay {
    display: none; /* Ocultar en PC */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 997;
}

/* Contenido principal */
.content {
    flex-grow: 1;
    padding: 20px;
    overflow-y: auto;
    margin-left: 250px; /* Añadido para dar espacio al sidebar en PC */
    transition: margin-left 0.3s ease;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        left: -250px; /* Ocultar el sidebar en móviles */
    }
    .sidebar.active {
        left: 0;
    }
    .content {
        margin-left: 0; /* Eliminar el margen izquierdo en móviles */
    }
    .toggle-sidebar {
        display: block; /* Mostrar el botón de togglear en móviles */
    }
    .overlay.active {
        display: block; /* Mostrar el overlay en móviles */
    }
    .formulario-edicion {
        margin-left: 0; /* Eliminar el margen izquierdo en móviles */
    }
}
    </style>
</head>
<body>
    <!-- Botón para togglear el sidebar en móvil -->
    <button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
<!-- Sidebar -->
<aside class="sidebar">
    <h2>Menú</h2>
    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
        <li><a href="habitaciones.php"><i class="fas fa-bed"></i>Servicios</a></li>
        <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
        <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Generar Recibo</a></li>
        <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Registro de Caja</a></li>
        <li><a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
</aside>
<!-- Overlay para móvil -->
<div class="overlay"></div>
<!-- Contenido principal -->
<div class="content">
        <h1>Editar Elemento</h1>
        <?php if ($error) { echo "<p class='error-message'>{$error}</p>"; } ?>

        <div class="formulario-edicion">
            <form method="post">
                <input type="hidden" name="id" value="<?= $elemento['id'] ?>">
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($elemento['nombre']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion"><?= htmlspecialchars($elemento['descripcion']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="tipo">Tipo:</label>
                    <select id="tipo" name="tipo" required>
                        <option value="habitacion" <?php if ($elemento['tipo'] == 'habitacion') echo 'selected'; ?>>Habitación</option>
                        <option value="servicio" <?php if ($elemento['tipo'] == 'servicio') echo 'selected'; ?>>Servicio</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select id="estado" name="estado" required>
                        <option value="disponible" <?php if ($elemento['estado'] == 'disponible') echo 'selected'; ?>>Disponible</option>
                        <option value="ocupada" <?php if ($elemento['estado'] == 'ocupada') echo 'selected'; ?>>Ocupada</option>
                        <option value="Mantenimiento" <?php if ($elemento['estado'] == 'Mantenimiento') echo 'selected'; ?>>Mantenimiento</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="habitaciones.php" class="btn btn-cancelar">Cancelar</a>
            </form>
        </div>
    </div>

    <script>

document.addEventListener("DOMContentLoaded", function() {
    const toggleSidebar = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');

    toggleSidebar.addEventListener('click', function() {
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