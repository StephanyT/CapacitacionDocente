<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol(['docente', 'admin'], true);

$sql = 'SELECT ' . columnas_insignia() . ' FROM insignias ORDER BY id ASC';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->execute();
$resultado = $stmt->get_result();
$insignias = [];

while ($fila = $resultado->fetch_assoc()) {
    $insignias[] = normalizar_insignia($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'insignias' => $insignias
]);

?>
