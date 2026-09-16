<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$datos = payload_evidencia_request();

$idInsignia = filter_var($datos['id_insignia'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$texto = trim((string) ($datos['texto'] ?? ''));

if (!$idInsignia) {
    responder_json(['ok' => false, 'mensaje' => 'Insignia invalida.'], 422);
}

if ($texto === '') {
    responder_json(['ok' => false, 'mensaje' => 'Describe como aplicaste lo aprendido.'], 422);
}

$idInsignia = (int) $idInsignia;

$sqlInsignia = 'SELECT id, requiere_capacitacion, evidencia FROM insignias WHERE id = ? LIMIT 1';
$stmt = preparar_consulta_evidencias($conexion, $sqlInsignia);
$stmt->bind_param('i', $idInsignia);
$stmt->execute();
$insignia = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$insignia) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Insignia no encontrada.'], 404);
}

if ((int) $insignia['evidencia'] !== 1) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Esta insignia no requiere evidencia.'], 422);
}

// Se valida el requisito tambien del lado servidor (el frontend ya oculta el
// boton si no se cumple, pero no hay que confiar solo en eso).
$cumpleRequisito = false;

if ($insignia['requiere_capacitacion'] !== null) {
    $idCapacitacion = (int) $insignia['requiere_capacitacion'];
    $sqlCheck = "SELECT id FROM inscripciones WHERE id_docente = ? AND id_capacitacion = ? AND estado = 'completada' LIMIT 1";
    $stmt = preparar_consulta_evidencias($conexion, $sqlCheck);
    $stmt->bind_param('ii', $idDocente, $idCapacitacion);
    $stmt->execute();
    $cumpleRequisito = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    // Insignia especial (ej. "Mentor Certus"): se gana con sesiones de
    // mentoria completadas, no con una capacitacion.
    $sqlCheck = "SELECT COUNT(*) AS total
                 FROM sesiones_mentoria sm
                 INNER JOIN mentores m ON m.id = sm.id_mentor
                 WHERE m.id_docente = ? AND sm.estado = 'completada'";
    $stmt = preparar_consulta_evidencias($conexion, $sqlCheck);
    $stmt->bind_param('i', $idDocente);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cumpleRequisito = $fila && (int) $fila['total'] >= 10;
}

if (!$cumpleRequisito) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Aun no cumples el requisito de esta insignia.'], 422);
}

// Bloquea un reenvio tanto si ya hay una evidencia en revision como si ya
// fue aprobada (el frontend ya oculta el boton en ese caso, pero el backend
// no debe confiar solo en eso).
$sqlExistente = "SELECT id, estado FROM evidencias_insignia WHERE id_docente = ? AND id_insignia = ? AND estado IN ('pendiente', 'aprobada') LIMIT 1";
$stmt = preparar_consulta_evidencias($conexion, $sqlExistente);
$stmt->bind_param('ii', $idDocente, $idInsignia);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existente) {
    $conexion->close();
    $mensaje = $existente['estado'] === 'aprobada'
        ? 'Ya tienes esta insignia aprobada.'
        : 'Ya tienes una evidencia en revision para esta insignia.';
    responder_json(['ok' => false, 'mensaje' => $mensaje], 409);
}

$estado = 'pendiente';
$sqlInsert = 'INSERT INTO evidencias_insignia (id_docente, id_insignia, texto, estado, fecha) VALUES (?, ?, ?, ?, CURRENT_DATE)';
$stmt = preparar_consulta_evidencias($conexion, $sqlInsert);
$stmt->bind_param('iiss', $idDocente, $idInsignia, $texto, $estado);
$stmt->execute();
$idEvidencia = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Evidencia enviada. El administrador la revisara pronto.',
    'evidencia' => ['id' => $idEvidencia]
], 201);

?>
