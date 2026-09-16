<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT id, id_docente, titulo, detalle, estado, fecha
        FROM sugerencias_capacitacion
        WHERE id_docente = ?
        ORDER BY fecha DESC, id DESC';

$stmt = preparar_consulta_sugerencias($conexion, $sql);
$stmt->bind_param('i', $idDocente);
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
