<?php
session_start();
require 'config.php';

$error = ''; // Para capturar errores
$success = ''; // Para mostrar mensajes de éxito

// Verifica si se recibió un ID de huésped a editar
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Obtener los datos actuales del huésped
    $stmt = $pdo->prepare('SELECT * FROM huespedes WHERE id = ?');
    $stmt->execute([$id]);
    $huesped = $stmt->fetch();

    if (!$huesped) {
        $error = "Huésped no encontrado.";
    }
} else {
    $error = "No se recibió un ID válido.";
}

// Lógica para guardar la edición del huésped
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_edicion'])) {
    $rfc = $_POST['rfc'];
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $tipo_huesped = $_POST['tipo_huesped'];
    $logo = '';

    // Manejo del logo
    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . basename($_FILES["logo"]["name"]);
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            $logo = $target_file;
        } else {
            $error = "Error al subir el logo.";
        }
    }

    // Actualizar los datos del huésped en la base de datos
    $stmt = $pdo->prepare('UPDATE huespedes SET rfc = ?, nombre = ?, telefono = ?, correo = ?, tipo_huesped = ?, logo = ? WHERE id = ?');
    if ($stmt->execute([$rfc, $nombre, $telefono, $correo, $tipo_huesped, $logo, $id])) {
        $success = "Huésped actualizado con éxito.";
        header('Location: huespedes.php');
        exit;
    } else {
        $error = "Hubo un error al actualizar el huésped.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Huésped</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
      /* Variables de color */
:root {
    --color-primary: #2c3e50;
    --color-secondary: #2ecc71;
    --color-accent: #e74c3c;
    --color-background: #f5f6fa;
    --color-text: #2c3e50;
    --color-border: #bdc3c7;
    --color-shadow: rgba(0, 0, 0, 0.1);
    --sidebar-width: 250px;
    --transition-speed: 0.3s;
}

/* Estilos generales */
body {
    font-family: 'Roboto', sans-serif;
    background-color: var(--color-background);
    color: var(--color-text);
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: var(--sidebar-width);
    background-color: var(--color-primary);
    color: white;
    padding: 20px;
    box-sizing: border-box;
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 998;
    transition: left var(--transition-speed) ease;
}

.sidebar h2 {
    text-align: center;
    font-size: 1.5em;
    margin-bottom: 30px;
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
    transition: background-color var(--transition-speed);
}

.sidebar ul li a:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

/* Botón de menú para móviles */
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
    z-index: 999;
    cursor: pointer;
}

.toggle-sidebar i {
    font-size: 1.2em;
}

/* Overlay */
.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 997;
}

/* Contenido principal */
.container {
    flex-grow: 1;
    padding: 20px;
    margin-left: var(--sidebar-width);
    transition: margin-left var(--transition-speed) ease;
}

/* Formulario */
.edit-form {
    background-color: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 6px var(--color-shadow);
    max-width: 600px;
    margin: 0 auto;
    animation: fadeIn 0.5s ease-in-out;
}

.form-group {
    margin-bottom: 20px;
    position: relative;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: var(--color-primary);
}

.form-group input,
.form-group select {
    width: 97%;
    padding: 12px 15px;
    border: 2px solid var(--color-border);
    border-radius: 5px;
    font-size: 1em;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--color-secondary);
    outline: none;
}

.logo-upload {
    margin-bottom: 20px;
}

.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1em;
    transition: background-color 0.3s;
}
h1 {
            text-align: center; /* Centrar el texto */
   }

.btn-guardar {
    background-color: var(--color-secondary);
    color: white;
}

.btn-guardar:hover {
    background-color: #27ae60;
}

.btn-cancelar {
    background-color: var(--color-accent);
    color: white;
    margin-left: 10px;
}

.btn-cancelar:hover {
    background-color: #c0392b;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

/* Mensajes de error y éxito */
.error-message {
    color: var(--color-accent);
    margin-bottom: 20px;
    text-align: center;
}

.success-message {
    color: var(--color-secondary);
    margin-bottom: 20px;
    text-align: center;
}

/* Animación de entrada */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        left: -var(--sidebar-width);
    }

    .sidebar.active {
        left: 0;
    }

    .toggle-sidebar {
        display: block;
    }

    .container {
        margin-left: 0;
    }

    .overlay.active {
        display: block;
    }
}

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
<aside class="sidebar">
        <h2>Menú</h2>
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Servicios</a></li>
            <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
            <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Crear Reservación</a></li>
            <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Reservas</a></li>
            <li><a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </aside>
    <!-- Overlay para móvil -->
<div class="overlay"></div>
&nbsp;
    <div class="container">
        <h1 id="Tittle">Editar Huésped</h1>

        <!-- Mostrar mensaje de error o éxito -->
        <?php if (!empty($error)) { echo "<p class='error-message'>$error</p>"; } ?>
        <?php if (!empty($success)) { echo "<p class='success-message'>$success</p>"; } ?>

        <form method="post" enctype="multipart/form-data" class="edit-form">
            <div class="form-group">
                <label for="tipo_huesped">Tipo de Huésped:</label>
                <select name="tipo_huesped" id="tipo_huesped" onchange="toggleFields()" required>
                    <option value="persona" <?php if ($huesped['tipo_huesped'] == 'persona') echo 'selected'; ?>>Persona</option>
                    <option value="empresa" <?php if ($huesped['tipo_huesped'] == 'empresa') echo 'selected'; ?>>Empresa</option>
                </select>
            </div>

            <div class="form-group">
                <label for="rfc">RFC:</label>
                <input type="text" id="rfc" name="rfc" value="<?= htmlspecialchars($huesped['rfc']) ?>" required>
            </div>

            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($huesped['nombre']) ?>" required>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono:</label>
                <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($huesped['telefono']) ?>">
            </div>

            <div class="form-group">
                <label for="correo">Correo:</label>
                <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($huesped['correo']) ?>">
            </div>

            <div class="form-group logo-upload">
                <label for="logo">Logo:</label>
                <input type="file" id="logo" name="logo">
            </div>

            <button type="submit" name="guardar_edicion" class="btn btn-guardar">Guardar</button>
            <button type="button" class="btn btn-cancelar" onclick="window.location.href='huespedes.php'">Cancelar</button>
        </form>
    </div>

    <script>
        function toggleFields() {
            const tipoHuesped = document.getElementById('tipo_huesped').value;
            const logoUpload = document.querySelector('.logo-upload');
            if (tipoHuesped === 'empresa') {
                logoUpload.style.display = 'block';
            } else {
                logoUpload.style.display = 'none';
            }
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
    
        // Inicializar el estado del campo de carga de logo
        window.onload = toggleFields;
    </script>
</body>
</html>
