<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT id, id_docente, id_insignia, texto, estado, fecha
        FROM evidencias_insignia
        ORDER BY (estado = "pendiente") DESC, fecha DESC, id DESC';

$stmt = preparar_consulta_evidencias($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$evidencias = [];

while ($fila = $resultado->fetch_assoc()) {
    $evidencias[] = normalizar_evidencia($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'evidencias' => $evidencias
]);

?>
