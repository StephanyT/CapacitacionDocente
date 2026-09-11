<?php

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$usuario = exigir_rol('admin', true);
$idAdmin = (int) $usuario['id'];

$datos = payload_evidencia_request();
rechazar_campos_no_permitidos_evidencia($datos, ['id_evidencia', 'estado']);

$idEvidencia = id_evidencia_request($datos);
$estado = estado_revision_request($datos);

$conexion->begin_transaction();

try {
    $sql = 'SELECT id, estado
            FROM evidencias_insignia
            WHERE id = ?
            LIMIT 1
            FOR UPDATE';

    $stmt = preparar_consulta_evidencias($conexion, $sql);
    $stmt->bind_param('i', $idEvidencia);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $evidencia = $resultado->fetch_assoc();
    $stmt->close();

    if (!$evidencia) {
        $conexion->rollback();
        responder_json([
            'ok' => false,
            'mensaje' => 'Evidencia no encontrada.'
        ], 404);
    }

    if ($evidencia['estado'] !== 'pendiente') {
        $conexion->rollback();
        responder_json([
            'ok' => false,
            'mensaje' => 'La evidencia ya fue revisada.'
        ], 409);
    }

    $sqlUpdate = 'UPDATE evidencias_insignia
                  SET estado = ?,
                      fecha_revision = CURRENT_TIMESTAMP,
                      revisado_por = ?
                  WHERE id = ?';

    $stmtUpdate = preparar_consulta_evidencias($conexion, $sqlUpdate);
    $stmtUpdate->bind_param('sii', $estado, $idAdmin, $idEvidencia);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo revisar la evidencia.'
    ], 500);
}

$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => $estado === 'aprobada'
        ? 'Evidencia aprobada correctamente.'
        : 'Evidencia rechazada correctamente.',
    'evidencia' => [
        'id' => $idEvidencia,
        'estado' => $estado
    ]
]);

?>
