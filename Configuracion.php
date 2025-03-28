<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}
$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
$mensaje = "";

// Obtener valores actuales
$stmt = $pdo->query("SELECT iva, ish, wifi_nombre, wifi_contrasena FROM configuracion_general LIMIT 1");
$config = $stmt->fetch();
$iva_actual = $config['iva'] ?? 16.00;
$ish_actual = $config['ish'] ?? 3.00;
$wifi_nombre = $config['wifi_nombre'] ?? '';
$wifi_password = $config['wifi_contrasena'] ?? '';
// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_iva = (float)$_POST['iva'];
    $nuevo_ish = (float)$_POST['ish'];
    $wifi_nombre = $_POST['wifi_nombre'] ?? '';
    $wifi_password = $_POST['wifi_contrasena'] ?? '';
    if ($nuevo_iva < 0 || $nuevo_iva > 100 || $nuevo_ish < 0 || $nuevo_ish > 100) {
        $mensaje = "Los valores deben estar entre 0 y 100.";
    } else {
        $stmt = $pdo->prepare("UPDATE configuracion_general SET iva = ?, ish = ?, wifi_nombre = ?, wifi_contrasena = ? WHERE id = 1");
$stmt->execute([$nuevo_iva, $nuevo_ish, $wifi_nombre, $wifi_password]);
        $mensaje = "Configuración actualizada correctamente.";
        $iva_actual = $nuevo_iva;
        $ish_actual = $nuevo_ish;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración de IVA e ISH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: sans-serif;
            background: #f4f6f8;
            padding: 30px;
        }

        .form-container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #2c3e50;
        }

        .campo-formulario {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
        }

        input[type="number"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-top: 5px;
        }

        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
        }

        .mensaje {
            text-align: center;
            margin-top: 10px;
            color: green;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: var(--color-primario);
            color: var(--color-letters);
            padding: 20px;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5em;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li {
            margin: 20px 0;
        }

        .sidebar ul li a {
            color: var(--color-letters);
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
    top: 15px;
    left: 15px;
    background: var(--color-primario);
    color: var(--color-letters);
    border: none;
    padding: 10px;
    border-radius: 5px;
    z-index: 1101;
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
        }

        .overlay.active {
            display: block;
        }

        .contenido {
            margin-left: 250px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 992px) {
    .contenido {
        margin-left: 0;
        padding: 20px;
    }

    .form-container {
        max-width: 100%;
        margin: 1rem;
        padding: 1.5rem;
    }

    .sidebar {
        left: -250px;
        transition: left 0.3s ease;
    }

    .sidebar.active {
        left: 0;
    }

    .overlay.active {
        display: block;
    }

    .toggle-sidebar {
        display: block;
    }

    h1 {
        font-size: 1.5rem;
    }

    input[type="number"] {
        font-size: 1rem;
        padding: 0.75rem;
    }

    button {
        width: 100%;
        font-size: 1rem;
        padding: 0.8rem;
    }

    .campo-formulario {
        margin-bottom: 1.2rem;
    }
}

@media (max-width: 480px) {
    .sidebar h2 {
        font-size: 1.2rem;
    }

    .sidebar ul li a {
        font-size: 1rem;
        padding: 8px;
    }

    .form-container {
        margin: 1rem;
        padding: 1rem;
    }

    h1 {
        font-size: 1.3rem;
    }
}

        :root {
      --color-primario: #2c3e50;
      --color-secundario: #3498db;
      --color-background: #ffffff;
      --color-text: #333333;
      --color-border: #bdc3c7;
      --color-shadow: rgba(0, 0, 0, 0.1);
      --color-letters: #ffffff;
    }
    </style>
</head>
<body>
<button class="toggle-sidebar"><i class="fas fa-bars"></i></button>

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
<div class="overlay"></div>
<div class="form-container">
    <h1><i class="fas fa-cogs"></i> Configuración de Impuestos</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="campo-formulario">
            <label for="iva">IVA (%)</label>
            <input type="number" name="iva" id="iva" value="<?= $iva_actual ?>" step="0.01" min="0" max="100" required>
        </div>

        <div class="campo-formulario">
            <label for="ish">ISH (%)</label>
            <input type="number" name="ish" id="ish" value="<?= $ish_actual ?>" step="0.01" min="0" max="100" required>
        </div>

        
        <div class="campo-formulario">
           <label for="wifi_nombre">Nombre WIFI</label>
            <input type="text" name="wifi_nombre" id="wifi_nombre" value="<?= htmlspecialchars($wifi_nombre) ?>">
        </div>

        <div class="campo-formulario">
            <label for="wifi_password">Contraseña WIFI</label>
            <input type="text" name="wifi_password" id="wifi_password" value="<?= htmlspecialchars($wifi_password) ?>">
        </div>
        <button type="submit"><i class="fas fa-save"></i> Guardar Configuración</button>
   
    </form>
</div>
<script>
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
