<?php

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

$datos = payload_evidencia_request();
rechazar_campos_no_permitidos_evidencia($datos, ['id_insignia', 'texto']);

$idInsignia = id_insignia_request($datos);
$texto = texto_evidencia_request($datos);

$insignia = obtener_insignia_activa($conexion, $idInsignia);

if (!$insignia) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Insignia no encontrada.'
    ], 404);
}

if (!$insignia['requiere_evidencia']) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Esta insignia no requiere evidencia.'
    ], 422);
}

if (!docente_cumple_requisito_insignia($conexion, $idDocente, $insignia)) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Aun no cumples el requisito base de esta insignia.'
    ], 422);
}

if (existe_evidencia_pendiente($conexion, $idDocente, $idInsignia)) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Ya tienes una evidencia pendiente para esta insignia.'
    ], 409);
}

$estado = 'pendiente';
$sql = 'INSERT INTO evidencias_insignia
            (id_docente, id_insignia, texto, estado)
        VALUES (?, ?, ?, ?)';

$stmt = preparar_consulta_evidencias($conexion, $sql);
$stmt->bind_param('iiss', $idDocente, $idInsignia, $texto, $estado);

if (!$stmt->execute()) {
    $stmt->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo guardar la evidencia.'
    ], 500);
}

$idEvidencia = (int) $conexion->insert_id;
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Evidencia enviada correctamente.',
    'evidencia' => [
        'id' => $idEvidencia,
        'id_insignia' => $idInsignia,
        'estado' => $estado
    ]
], 201);

?>
