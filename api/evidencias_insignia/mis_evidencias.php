<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$sql = 'SELECT id, id_docente, id_insignia, texto, estado, fecha
        FROM evidencias_insignia
        WHERE id_docente = ?
        ORDER BY id ASC';

$stmt = preparar_consulta_evidencias($conexion, $sql);
$stmt->bind_param('i', $idDocente);
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
