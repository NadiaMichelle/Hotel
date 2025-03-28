<?php
session_start();
require 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre_usuario'];
$error = '';
$huespedes = [];

// Mostrar todos los huéspedes por defecto
try {
    $stmt = $pdo->query('SELECT * FROM huespedes');
    $huespedes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar huéspedes: " . $e->getMessage();
}

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['buscar'])) {
        // Buscar huéspedes
        $nombre_huesped = $_POST['nombre_huesped'] ?? '';
        $tipo_huesped = $_POST['tipo_huesped'] ?? '';
        $nombre_like = "%$nombre_huesped%";

        try {
            if (!empty($tipo_huesped)) {
                $stmt = $pdo->prepare('
                    SELECT * FROM huespedes 
                    WHERE (nombre LIKE :nombre OR nombre_empresa LIKE :nombre)
                    AND tipo_huesped = :tipo_huesped
                ');
                $stmt->execute([
                    ':nombre' => $nombre_like,
                    ':tipo_huesped' => $tipo_huesped
                ]);
            } else {
                $stmt = $pdo->prepare('
                    SELECT * FROM huespedes 
                    WHERE nombre LIKE :nombre OR nombre_empresa LIKE :nombre
                ');
                $stmt->execute([':nombre' => $nombre_like]);
            }

            $huespedes = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error = "Error al buscar huéspedes: " . $e->getMessage();
        }

    } elseif (isset($_POST['borrar'])) {
        // Eliminar huésped
        $id = $_POST['id'];

        try {
            $stmt = $pdo->prepare('DELETE FROM huespedes WHERE id = ?');
            if ($stmt->execute([$id])) {
                header('Location: huespedes.php');
                exit;
            } else {
                $error = "No se pudo eliminar el huésped.";
            }
        } catch (PDOException $e) {
            $error = "Error al eliminar huésped: " . $e->getMessage();
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
    :root {
      --color-primario: #2c3e50;
      --color-secundario: #3498db;
      --color-background: #ffffff;
      --color-text: #333333;
      --color-border: #bdc3c7;
      --color-shadow: rgba(0, 0, 0, 0.1);
      --color-letters: #ffffff;
    }

    body {
      font-family: 'Roboto', sans-serif;
      background-color: var(--color-background);
      color: var(--color-text);
      margin: 0;
      padding: 0;
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
      border-radius: 4px;
      z-index: 1000;
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
      z-index: 999;
      transition: left 0.3s ease;
    }

    .sidebar h2 {
      text-align: center;
      margin-bottom: 30px;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
    }

    .sidebar ul li {
      margin: 15px 0;
    }

    .sidebar ul li a {
      color: var(--color-letters);
      text-decoration: none;
      display: flex;
      align-items: center;
      padding: 10px;
      border-radius: 4px;
      transition: background-color 0.3s;
    }

    .sidebar ul li a i {
      margin-right: 10px;
    }

    .sidebar ul li a:hover {
      background-color: rgba(255, 255, 255, 0.2);
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

   

    .card {
      background: var(--color-background);
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 4px 12px var(--color-shadow);
    }

    .table-container {
      overflow-x: auto;
      border-radius: 8px;
    }

    .table th {
      position: sticky;
      top: 0;
      background-color: var(--color-primario);
      color: white;
    }
  </style>
</head>
<body>
  <button class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
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

  <div class="contenido">
    <div class="container-fluid">
      <div class="card">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
          <h2 class="text-primary mb-3 mb-md-0"><i class="fas fa-users me-2"></i> Gestión de Huéspedes</h2>
          <a href="agregar_huesped.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Agregar
          </a>
        </div>

        <form method="post" class="row g-3 mb-4">
          <div class="col-md-5">
          <input type="text" id="search" name="nombre_huesped" class="form-control" placeholder="Buscar por nombre o empresa">
          </div>
          <div class="col-md-4">
          <select id="filter-type" name="tipo_huesped" class="form-select">
              <option value="">Todos los tipos</option>
              <option value="persona">Persona</option>
              <option value="empresa">Empresa</option>
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" name="buscar" class="btn btn-primary w-100">
              <i class="fas fa-search"></i> Buscar
            </button>
          </div>
        </form>

        <div class="table-container">
        <table id="guest-table" class="table table-bordered table-hover align-middle text-center">
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
                <td><?= $huesped['logo'] ? "<img src='{$huesped['logo']}' alt='Logo' style='width:50px;height:50px;border-radius:8px;'>" : "No aplica" ?></td>
                <td><?= ucfirst($huesped['tipo_huesped']) ?></td>
                <td><?= $huesped['rfc'] ?></td>
                <td><?= $huesped['tipo_huesped'] === 'persona' ? $huesped['nombre'] : $huesped['nombre_empresa'] ?></td>
                <td><?= $huesped['telefono'] ?></td>
                <td><?= $huesped['correo'] ?></td>
                <td>
                  <a href="editar_huesped.php?id=<?= $huesped['id'] ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit"></i>
                  </a>
                  <form method="POST" action="huespedes.php" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este huésped?');">
                    <input type="hidden" name="id" value="<?= $huesped['id'] ?>">
                    <button type="submit" name="borrar" class="btn btn-sm btn-danger">
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
  </script>
</body>
</html>
>
