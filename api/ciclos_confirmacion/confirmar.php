<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('admin', true);
$nombreAdmin = $usuario['nombre'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$raw = file_get_contents('php://input');
$datos = json_decode($raw, true);
if (!is_array($datos)) {
    $datos = $_POST;
}

$idDocente = filter_var($datos['id_docente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$periodo = trim((string) ($datos['periodo'] ?? PERIODO_ACTIVO_CICLO));

if (!$idDocente) {
    responder_json(['ok' => false, 'mensaje' => 'Docente invalido.'], 422);
}

$idDocente = (int) $idDocente;

$sqlCheck = "SELECT id, estado FROM ciclos_confirmacion WHERE id_docente = ? AND periodo = ? LIMIT 1";
$stmt = preparar_consulta_ciclos($conexion, $sqlCheck);
$stmt->bind_param('is', $idDocente, $periodo);
$stmt->execute();
$ciclo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ciclo) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'El docente no ha solicitado confirmacion de este ciclo.'], 404);
}

if ($ciclo['estado'] !== 'solicitado') {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Este ciclo ya fue confirmado.'], 409);
}

$estadoConfirmado = 'confirmado';
$sqlUpdate = 'UPDATE ciclos_confirmacion
              SET estado = ?, confirmado_por = ?, fecha_confirmacion = CURRENT_DATE
              WHERE id = ?';
$stmt = preparar_consulta_ciclos($conexion, $sqlUpdate);
$stmt->bind_param('ssi', $estadoConfirmado, $nombreAdmin, $ciclo['id']);
$stmt->execute();
$stmt->close();

$sqlFinal = 'SELECT id, id_docente, periodo, estado, fecha_solicitud, confirmado_por, fecha_confirmacion
             FROM ciclos_confirmacion WHERE id = ? LIMIT 1';
$stmt = preparar_consulta_ciclos($conexion, $sqlFinal);
$stmt->bind_param('i', $ciclo['id']);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Ciclo confirmado.',
    'ciclo' => normalizar_ciclo($fila)
]);

?>
