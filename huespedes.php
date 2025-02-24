<?php
session_start();
require 'config.php';

// Manejo de errores
$error = '';

// Lógica para mostrar los huéspedes
$huespedes = [];
$stmt = $pdo->query('SELECT * FROM huespedes');
$huespedes = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['buscar'])) {
        // Lógica para buscar huéspedes
        $nombre_huesped = $_POST['nombre_huesped'] ?? '';
        $tipo_huesped = $_POST['tipo_huesped'] ?? '';

        // Preparar la consulta SQL con parámetros para evitar inyecciones SQL
        if ($tipo_huesped) {
            $stmt = $pdo->prepare('SELECT * FROM huespedes WHERE (nombre LIKE :nombre OR nombre_empresa LIKE :nombre) AND tipo_huesped = :tipo_huesped');
            $stmt->bindParam(':tipo_huesped', $tipo_huesped, PDO::PARAM_STR);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM huespedes WHERE nombre LIKE :nombre OR nombre_empresa LIKE :nombre');
        }
        $nombre_like = "%$nombre_huesped%";
        $stmt->bindParam(':nombre', $nombre_like, PDO::PARAM_STR);
        $stmt->execute();
        $huespedes = $stmt->fetchAll();
    } elseif (isset($_POST['borrar'])) {
        // Lógica para eliminar un huésped
        $id = $_POST['id'];
        $stmt = $pdo->prepare('DELETE FROM huespedes WHERE id = ?');
        if ($stmt->execute([$id])) {
            // Redirigir para evitar el reenvío del formulario
            header('Location: huespedes.php');
            exit;
        } else {
            $error = "Hubo un error al eliminar el huésped.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Huéspedes</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Variables de color */
    :root {
      --color-primary: #2c3e50; /* Azul */
      --color-secondary: #2ecc71; /* Verde */
      --color-accent: #e74c3c; /* Rojo */
      --color-background: #f5f6fa; /* Gris claro */
      --color-text: #2c3e50; /* Texto oscuro */
      --color-success: #27ae60; /* Verde éxito */
      --color-error: #e74c3c; /* Rojo error */
      --color-border: #bdc3c7; /* Gris para bordes */
      --color-shadow: rgba(0, 0, 0, 0.1); /* Sombra ligera */
    }

    /* Estilos generales */
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

    /* Contenido principal */
    .content {
      flex-grow: 1;
      padding: 20px;
      overflow-y: auto;
      margin-left: 250px;
      transition: margin-left 0.3s ease;
    }

    /* Contenedor principal */
    .container {
      max-width: 1000px;
      width: 100%;
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px var(--color-shadow);
    }

    /* Botón Agregar */
    .add-button {
      background-color: var(--color-secondary);
      color: white;
      padding: 10px 15px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      margin-bottom: 20px;
    }

    .add-button:hover {
      background-color: #25a745;
    }

    /* Barra de búsqueda */
    .search-container {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-bottom: 20px;
    }

    .search-container input, .search-container select {
      padding: 10px;
      border: 1px solid var(--color-border);
      border-radius: 4px;
      font-size: 16px;
    }

    .search-container .search-input {
      flex: 1;
    }

    .search-container .search-icon {
      background: var(--color-primary);
      color: white;
      padding: 10px 15px;
      border-radius: 4px;
      cursor: pointer;
    }

    /* Tabla */
    .table-container {
      overflow-y: auto;
      max-height: 500px;
      border-radius: 8px;
    }

    .guest-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }

    .guest-table th, .guest-table td {
      padding: 12px;
      text-align: center;
      border-bottom: 1px solid var(--color-border);
    }

    .guest-table th {
      background-color: var(--color-primary);
      color: white;
      font-weight: bold;
      position: sticky;
      top: 0;
      z-index: 2;
    }

    .guest-table img {
      max-width: 50px;
      border-radius: 4px;
    }

    /* Botones de acción */
    .btn-edit, .btn-delete {
      text-decoration: none;
      color: #555;
      margin: 0 5px;
    }

    .btn-edit:hover, .btn-delete:hover {
      color: #000;
    }

    /* Botón para mostrar/ocultar el sidebar en móvil */
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

    /* Responsive */
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
  <!-- Botón para togglear el sidebar en móvil -->
  <button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
  
  <!-- Sidebar -->
  <aside class="sidebar">
    <h2>Menú</h2>
    <ul>
      <li><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>
      <li><a href="habitaciones.php"><i class="fas fa-bed"></i> Habitaciones y Servicios</a></li>
      <li><a href="huespedes.php"><i class="fas fa-users"></i> Huéspedes</a></li>
      <li><a href="Crear_Recibo.php"><i class="fas fa-pen-alt"></i> Crear reservas</a></li>
      <li><a href="recibos.php"><i class="fas fa-file-invoice"></i> Reservas</a></li>
      <li><a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
    </ul>
  </aside>

  <!-- Overlay opcional para móvil -->
  <div class="overlay"></div>

  <!-- Contenido principal -->
  <div class="content">
    <div class="container">
      <h1>Gestión de Huéspedes</h1>
      <button class="add-button" onclick="window.location.href='agregar_huesped.php'">
        <i class="fas fa-plus"></i> Agregar Huésped
      </button>

      <div class="search-container">
        <input type="text" id="search" class="search-input" placeholder="Buscar por nombre...">
        <select id="filter-type">
          <option value="">Todos</option>
          <option value="persona">Persona</option>
          <option value="empresa">Empresa</option>
        </select>
        <i class="fas fa-search search-icon"></i>
      </div>

      <div class="table-container">
        <table class="guest-table" id="guest-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Logo</th>
              <th>Tipo</th>
              <th>RFC</th>
              <th>Nombre / Empresa</th>
              <th>Teléfono</th>
              <th>Correo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($huespedes as $index => $huesped): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><?= $huesped['logo'] ? "<img src='{$huesped['logo']}' alt='Logo'>" : "No aplica" ?></td>
                <td><?= $huesped['tipo_huesped'] ?></td>
                <td><?= $huesped['rfc'] ?></td>
                <td><?= $huesped['tipo_huesped'] === 'persona' ? $huesped['nombre'] : $huesped['nombre_empresa'] ?></td>
                <td><?= $huesped['telefono'] ?></td>
                <td><?= $huesped['correo'] ?></td>
                <td>
                  <!-- Botón Editar -->
                  <a href="editar_huesped.php?id=<?= $huesped['id'] ?>" class="btn-edit" title="Editar">
                    <i class="fas fa-edit"></i>
                  </a>
                  <!-- Botón Eliminar -->
                  <form method="POST" action="huespedes.php" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este huésped?');">
                    <input type="hidden" name="id" value="<?= $huesped['id'] ?>">
                    <button type="submit" name="borrar" class="btn-delete" title="Eliminar">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const searchInput = document.getElementById("search");
      const filterType = document.getElementById("filter-type");
      const tableBody = document.querySelector("#guest-table tbody");

      function renderTable() {
        const rows = document.querySelectorAll("#guest-table tbody tr");
        rows.forEach(row => {
          const name = row.cells[4].innerText.toLowerCase();
          const type = row.cells[2].innerText.toLowerCase();
          const rfc = row.cells[3].innerText.toLowerCase();
          const searchValue = searchInput.value.toLowerCase();
          const filterValue = filterType.value.toLowerCase();

          if (
            (searchValue === "" || name.includes(searchValue)) &&
            (filterValue === "" || type.includes(filterValue)) &&
            (searchValue === "" || rfc.includes(searchValue))
          ) {
            row.style.display = "";
          } else {
            row.style.display = "none";
          }
        });
      }

      searchInput.addEventListener("keyup", renderTable);
      filterType.addEventListener("change", renderTable);

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
