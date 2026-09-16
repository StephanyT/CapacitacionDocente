<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT id, id_docente, titulo, detalle, estado, fecha
        FROM sugerencias_capacitacion
        ORDER BY fecha DESC, id DESC';

$stmt = preparar_consulta_sugerencias($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$sugerencias = [];

while ($fila = $resultado->fetch_assoc()) {
    $sugerencias[] = normalizar_sugerencia($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'sugerencias' => $sugerencias
]);

?>
