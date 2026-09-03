<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$correo   = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';
$rol      = $_POST['rol'] ?? 'docente';

if (empty($correo) || empty($password)) {
    header('Location: login.html?error=1');
    exit;
}

if ($rol === 'docente') {
    $stmt = $pdo->prepare("SELECT * FROM docente WHERE correo = ? AND activo = 1");
    $stmt->execute([$correo]);
    $docente = $stmt->fetch();

    if ($docente && password_verify($password, $docente['password'])) {
        $_SESSION['docente_id'] = $docente['id_docente'];
        $_SESSION['nombres']    = $docente['nombres'];
        $_SESSION['apellidos']  = $docente['apellidos'];
        $_SESSION['correo']     = $docente['correo'];
        $_SESSION['rol']        = 'docente';

        header('Location: docente/perfil.html');
        exit;
    } else {
        header('Location: login.html?error=1');
        exit;
    }
}

// Admin por ahora sigue siendo mock
if ($rol === 'admin') {
    header('Location: admin/dashboard.html');
    exit;
}

header('Location: login.html?error=1');
exit;
?>