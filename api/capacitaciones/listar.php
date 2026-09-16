<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol(['docente', 'admin'], true);

$sql = 'SELECT ' . columnas_capacitacion() . ' FROM capacitaciones';

if ($usuario['rol'] === 'docente') {
    $sql .= ' WHERE activo = 1';
}

$sql .= ' ORDER BY fecha ASC, id ASC';

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo preparar la consulta.'
    ], 500);
}

$stmt->execute();
$resultado = $stmt->get_result();
$capacitaciones = [];

while ($fila = $resultado->fetch_assoc()) {
    $capacitaciones[] = normalizar_capacitacion($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'capacitaciones' => $capacitaciones
]);

?>
