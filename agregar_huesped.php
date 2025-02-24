<?php
session_start();
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $tipo_huesped = $_POST['tipo_huesped'] ?? '';
        $rfc = trim($_POST['rfc'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $nombre = null;
        $nombre_empresa = null;
        $logo = null;

        if ($tipo_huesped === 'persona') {
            $nombre = trim($_POST['nombre'] ?? '');
            if (empty($nombre)) {
                throw new Exception("El campo 'nombre' es obligatorio para personas.");
            }
            $nombre_empresa = null;
        } elseif ($tipo_huesped === 'empresa') {
            $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
            if (empty($nombre_empresa)) {
                throw new Exception("El campo 'nombre de la empresa' es obligatorio.");
            }
            $nombre = ''; // Se asigna una cadena vacía en lugar de NULL
        }
        
            // Manejo del logo
            if (!empty($_FILES['logo']['name'])) {
                $directorio = 'logos/';
                if (!is_dir($directorio) && !mkdir($directorio, 0777, true)) {
                    throw new Exception("No se pudo crear el directorio de logos.");
                }

                $nombreArchivo = basename($_FILES['logo']['name']);
                $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

                // Validar que sea una imagen
                $formatosPermitidos = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($extension, $formatosPermitidos)) {
                    throw new Exception("Formato de imagen no permitido. Solo JPG, PNG y GIF.");
                }

                $logo = $directorio . uniqid() . '.' . $extension;
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo)) {
                    throw new Exception("Error al subir el logo.");
                }
            }
         else {
            throw new Exception("Tipo de huésped inválido.");
        }

        // Insertar en la base de datos
        $stmt = $pdo->prepare('INSERT INTO huespedes (rfc, nombre, telefono, correo, tipo_huesped, nombre_empresa, logo) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$rfc, $nombre, $telefono, $correo, $tipo_huesped, $nombre_empresa, $logo]);

        echo "<script>alert('Huésped agregado con éxito'); window.location='huespedes.php';</script>";
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Huésped</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Estilos Generales */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #34495e;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .error-message {
            color: #e74c3c;
            text-align: center;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-cancel {
            background-color: #95a5a6;
            color: white;
            margin-top: 10px;
        }

        .btn-cancel:hover {
            background-color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Agregar Huésped</h1>
        <?php if (!empty($error)) { echo "<p class='error-message'>{$error}</p>"; } ?>
        <form method="post" action="agregar_huesped.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="tipo_huesped">Tipo de Huésped:</label>
                <select name="tipo_huesped" id="tipo_huesped" onchange="toggleFields()" required>
                    <option value="persona">Persona</option>
                    <option value="empresa">Empresa</option>
                </select>
            </div>

            <div id="persona_fields">
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" name="nombre" id="nombre">
                </div>
            </div>

            <div id="empresa_fields" style="display:none;">
                <div class="form-group">
                    <label for="nombre_empresa">Nombre de la Empresa:</label>
                    <input type="text" name="nombre_empresa" id="nombre_empresa">
                </div>
                <div class="form-group">
                    <label for="logo">Logo de la Empresa:</label>
                    <input type="file" name="logo" id="logo" accept="image/*">
                </div>
            </div>

            <div class="form-group">
                <label for="rfc">RFC:</label>
                <input type="text" name="rfc" id="rfc" required>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono:</label>
                <input type="text" name="telefono" id="telefono">
            </div>

            <div class="form-group">
                <label for="correo">Correo:</label>
                <input type="email" name="correo" id="correo">
            </div>

            <button type="submit" class="btn btn-primary">Agregar Huésped</button>
            <button type="button" class="btn btn-cancel" onclick="window.location='huespedes.php'">Cancelar</button>
        </form>
    </div>

    <script>
        function toggleFields() {
            document.getElementById('persona_fields').style.display = document.getElementById('tipo_huesped').value === 'persona' ? 'block' : 'none';
            document.getElementById('empresa_fields').style.display = document.getElementById('tipo_huesped').value === 'empresa' ? 'block' : 'none';
        }
    </script>
</body>
</html>