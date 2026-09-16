<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$raw = file_get_contents('php://input');
$datos = json_decode($raw, true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$idMeta = filter_var($datos['id_meta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$idMeta) {
    responder_json(['ok' => false, 'mensaje' => 'Meta invalida.'], 422);
}

$idMeta = (int) $idMeta;

// Se valida que la meta pertenezca a un plan de ESTE docente (via el join
// con planes_desarrollo) antes de tocarla — sin esto, cualquier docente
// logueado podria marcar como cumplida la meta de otro solo adivinando el id.
$sqlCheck = 'SELECT m.id, m.estado
             FROM metas_desarrollo m
             INNER JOIN planes_desarrollo p ON p.id = m.id_plan
             WHERE m.id = ? AND p.id_docente = ?
             LIMIT 1';
$stmt = preparar_consulta_planes($conexion, $sqlCheck);
$stmt->bind_param('ii', $idMeta, $idDocente);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meta) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Meta no encontrada.'], 404);
}

$nuevoEstado = $meta['estado'] === 'cumplida' ? 'en progreso' : 'cumplida';
$sqlUpdate = 'UPDATE metas_desarrollo SET estado = ? WHERE id = ?';
$stmt = preparar_consulta_planes($conexion, $sqlUpdate);
$stmt->bind_param('si', $nuevoEstado, $idMeta);
$stmt->execute();
$stmt->close();

$plan = obtener_plan_docente($conexion, $idDocente, PERIODO_ACTIVO_PLAN);
$conexion->close();

responder_json([
    'ok' => true,
    'plan' => $plan
]);

?>
