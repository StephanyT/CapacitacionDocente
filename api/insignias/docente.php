<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if (array_diff(array_keys($_GET), ['id_docente'])) {
    responder_json([
        'ok' => false,
        'mensaje' => 'La solicitud contiene campos no permitidos.'
    ], 422);
}

$idDocente = filter_input(INPUT_GET, 'id_docente', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idDocente) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_docente invalido.'
    ], 422);
}

$stmtDocente = preparar_consulta_insignias(
    $conexion,
    "SELECT id, nombres, apellidos
     FROM usuarios
     WHERE id = ? AND rol = 'docente'
     LIMIT 1"
);
$stmtDocente->bind_param('i', $idDocente);
$stmtDocente->execute();
$resultadoDocente = $stmtDocente->get_result();
$docente = $resultadoDocente->fetch_assoc();
$stmtDocente->close();

if (!$docente) {
    responder_json([
        'ok' => false,
        'mensaje' => 'Docente no encontrado.'
    ], 404);
}

$sql = "SELECT
            ins.id,
            ins.nombre,
            ins.codigo_icono,
            ins.requisito,
            ins.area,
            ins.id_capacitacion_requerida,
            ins.requiere_evidencia,
            ins.activo,
            ins.fecha_creacion,
            CASE
                WHEN ins.id_capacitacion_requerida IS NULL THEN 0
                ELSE EXISTS (
                    SELECT 1
                    FROM inscripciones i
                    WHERE i.id_docente = ?
                      AND i.id_capacitacion = ins.id_capacitacion_requerida
                      AND i.estado = 'completada'
                    LIMIT 1
                )
            END AS cumple_requisito,
            EXISTS (
                SELECT 1
                FROM evidencias_insignia e
                WHERE e.id_docente = ?
                  AND e.id_insignia = ins.id
                  AND e.estado = 'aprobada'
                LIMIT 1
            ) AS evidencia_aprobada,
            (
                SELECT e.estado
                FROM evidencias_insignia e
                WHERE e.id_docente = ?
                  AND e.id_insignia = ins.id
                ORDER BY e.fecha_envio DESC, e.id DESC
                LIMIT 1
            ) AS estado_evidencia
        FROM insignias ins
        WHERE ins.activo = 1
        ORDER BY ins.id ASC";

$stmt = preparar_consulta_insignias($conexion, $sql);
$stmt->bind_param('iii', $idDocente, $idDocente, $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$insignias = [];

while ($fila = $resultado->fetch_assoc()) {
    $insignias[] = normalizar_insignia_calculada($fila);
}

$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'docente' => [
        'id' => (int) $docente['id'],
        'nombre_completo' => trim($docente['nombres'] . ' ' . $docente['apellidos'])
    ],
    'insignias' => $insignias
]);

?>
