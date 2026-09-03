<?php
include 'conexion.php';

$correo = $_POST['correo'];
$password = $_POST['password'];

$sql = "SELECT * FROM usuarios WHERE correo = '$correo' AND password = '$password'";
$resultado = $conexion->query($sql);

if ($resultado->num_rows > 0) {
    $usuario = $resultado->fetch_assoc();
    echo "¡Éxito! Iniciaste sesión correctamente. Tu rol es: " . $usuario['rol'];
} else {
    echo "Error: Correo o contraseña incorrectos.";
}

$conexion->close();
?>