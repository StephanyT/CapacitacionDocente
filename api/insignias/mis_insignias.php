<?php

require_once __DIR__ . '/_helpers.php';

$usuario = exigir_rol('docente', true);
$idDocente = (int) $usuario['id'];

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
    'insignias' => $insignias
]);

?>
