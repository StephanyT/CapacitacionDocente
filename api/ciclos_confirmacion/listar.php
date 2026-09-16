<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT id, id_docente, periodo, estado, fecha_solicitud, confirmado_por, fecha_confirmacion
        FROM ciclos_confirmacion';
$stmt = preparar_consulta_ciclos($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$ciclos = [];

while ($fila = $resultado->fetch_assoc()) {
    $ciclos[] = normalizar_ciclo($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'ciclos' => $ciclos
]);

?>
