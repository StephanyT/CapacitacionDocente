<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

// Mismo periodo institucional activo que planes_desarrollo — el ciclo
// cierra formalmente el plan de ese periodo, no tiene sentido que difieran.
const PERIODO_ACTIVO_CICLO = '2026-II';

function preparar_consulta_ciclos(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    return $stmt;
}

function normalizar_ciclo(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_docente' => (int) $fila['id_docente'],
        'periodo' => $fila['periodo'],
        'estado' => $fila['estado'],
        'fecha_solicitud' => $fila['fecha_solicitud'],
        'confirmado_por' => $fila['confirmado_por'],
        'fecha_confirmacion' => $fila['fecha_confirmacion']
    ];
}

?>
