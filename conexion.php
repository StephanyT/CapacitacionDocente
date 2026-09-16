<?php
$servidor = "localhost";
$usuario_db = "root";
$password_db = "";
$nombre_db = "capacitacion_db";

$conexion = new mysqli($servidor, $usuario_db, $password_db, $nombre_db);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>