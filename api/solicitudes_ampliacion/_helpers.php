<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_ampliacion(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    return $stmt;
}

function payload_ampliacion_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function normalizar_solicitud_ampliacion_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'motivo' => $fila['motivo'],
        'motivo_rechazo' => $fila['motivo_rechazo'],
        'fecha_solicitud' => $fila['fecha_solicitud'],
        'fecha_resolucion' => $fila['fecha_resolucion'],
        'nueva_fecha_limite' => $fila['nueva_fecha_limite'],
        'inscripcion' => [
            'id' => (int) $fila['inscripcion_id'],
            'estado' => $fila['inscripcion_estado'],
            'fecha_limite' => $fila['fecha_limite']
        ],
        'capacitacion' => [
            'id' => (int) $fila['capacitacion_id'],
            'titulo' => $fila['capacitacion_titulo']
        ]
    ];
}

// La inscripcion debe ser del docente que hace la solicitud y estar
// realmente vencida -- no tiene sentido pedir ampliacion de algo que sigue
// en curso o que ya se completo.
function obtener_inscripcion_vencida_propia(mysqli $conexion, int $idInscripcion, int $idDocente): ?array
{
    $sql = 'SELECT id, id_docente, estado, fecha_limite
            FROM inscripciones
            WHERE id = ? AND id_docente = ?
            LIMIT 1';

    $stmt = preparar_consulta_ampliacion($conexion, $sql);
    $stmt->bind_param('ii', $idInscripcion, $idDocente);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

function existe_solicitud_ampliacion_pendiente(mysqli $conexion, int $idInscripcion): bool
{
    $sql = "SELECT id FROM solicitudes_ampliacion
            WHERE id_inscripcion = ? AND estado = 'pendiente'
            LIMIT 1";

    $stmt = preparar_consulta_ampliacion($conexion, $sql);
    $stmt->bind_param('i', $idInscripcion);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $existe;
}

?>
