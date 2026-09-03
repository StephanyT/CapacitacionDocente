<?php
// Datos de conexion a MySQL. Con XAMPP, por defecto el usuario es "root"
// y la contrasena esta vacia -- si tu MySQL tiene otra contrasena, cambiala aqui.
$host = "localhost";
$usuario = "root";
$clave = "";
$basedatos = "certus_db";

$conexion = new mysqli($host, $usuario, $clave, $basedatos);

if ($conexion->connect_error) {
    die(json_encode(["ok" => false, "mensaje" => "Error de conexion: " . $conexion->connect_error]));
}

$conexion->set_charset("utf8mb4");
?>
