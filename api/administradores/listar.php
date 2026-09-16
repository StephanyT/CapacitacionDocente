<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT ' . columnas_administrador() . ' FROM administradores ORDER BY apellidos ASC, nombres ASC, id ASC';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->execute();
$resultado = $stmt->get_result();
$administradores = [];

while ($fila = $resultado->fetch_assoc()) {
    $administradores[] = normalizar_administrador($fila);
}

$stmt->close();
$conexion->close();

responder_json(['ok' => true, 'administradores' => $administradores]);

?>
