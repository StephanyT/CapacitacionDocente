<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT ' . columnas_docente() . ' FROM docentes ORDER BY apellidos ASC, nombres ASC, id ASC';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->execute();
$resultado = $stmt->get_result();
$docentes = [];

while ($fila = $resultado->fetch_assoc()) {
    $docentes[] = normalizar_docente($fila);
}

$stmt->close();
$conexion->close();

responder_json(['ok' => true, 'docentes' => $docentes]);

?>
