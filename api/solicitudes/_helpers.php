<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_solicitudes(mysqli $conexion, string $sql): mysqli_stmt
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

function payload_solicitud_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function rechazar_campos_no_permitidos_solicitud(array $datos, array $permitidos): void
{
    $permitidosMapa = array_fill_keys($permitidos, true);
    $errores = [];

    foreach (array_keys($datos) as $campo) {
        if (!isset($permitidosMapa[$campo])) {
            $errores[] = "El campo {$campo} no puede enviarse.";
        }
    }

    if ($errores) {
        responder_json([
            'ok' => false,
            'mensaje' => 'La solicitud contiene campos no permitidos.',
            'errores' => $errores
        ], 422);
    }
}

function id_capacitacion_solicitud(array $datos): int
{
    if (!array_key_exists('id_capacitacion', $datos)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'El campo id_capacitacion es obligatorio.'
        ], 422);
    }

    $id = filter_var($datos['id_capacitacion'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$id) {
        responder_json([
            'ok' => false,
            'mensaje' => 'id_capacitacion invalido.'
        ], 422);
    }

    return (int) $id;
}

function normalizar_solicitud_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'fecha_solicitud' => $fila['fecha_solicitud'],
        'fecha_resolucion' => $fila['fecha_resolucion'],
        'capacitacion' => [
            'id' => (int) $fila['capacitacion_id'],
            'titulo' => $fila['capacitacion_titulo']
        ]
    ];
}

function obtener_capacitacion_activa_solicitud(mysqli $conexion, int $idCapacitacion): ?array
{
    $sql = 'SELECT id, titulo
            FROM capacitaciones
            WHERE id = ? AND activo = 1
            LIMIT 1';

    $stmt = preparar_consulta_solicitudes($conexion, $sql);
    $stmt->bind_param('i', $idCapacitacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        return null;
    }

    return [
        'id' => (int) $fila['id'],
        'titulo' => $fila['titulo']
    ];
}

function existe_solicitud_docente_capacitacion(mysqli $conexion, int $idDocente, int $idCapacitacion): ?array
{
    $sql = 'SELECT id, estado
            FROM solicitudes_capacitacion
            WHERE id_docente = ? AND id_capacitacion = ?
            LIMIT 1';

    $stmt = preparar_consulta_solicitudes($conexion, $sql);
    $stmt->bind_param('ii', $idDocente, $idCapacitacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        return null;
    }

    return [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado']
    ];
}

function existe_inscripcion_docente_capacitacion(mysqli $conexion, int $idDocente, int $idCapacitacion): bool
{
    $sql = 'SELECT id
            FROM inscripciones
            WHERE id_docente = ? AND id_capacitacion = ?
            LIMIT 1';

    $stmt = preparar_consulta_solicitudes($conexion, $sql);
    $stmt->bind_param('ii', $idDocente, $idCapacitacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

function obtener_solicitud_docente_por_id(mysqli $conexion, int $idSolicitud, int $idDocente): ?array
{
    $sql = 'SELECT
                s.id,
                s.estado,
                s.fecha_solicitud,
                s.fecha_resolucion,
                c.id AS capacitacion_id,
                c.titulo AS capacitacion_titulo
            FROM solicitudes_capacitacion s
            INNER JOIN capacitaciones c ON c.id = s.id_capacitacion
            WHERE s.id = ? AND s.id_docente = ?
            LIMIT 1';

    $stmt = preparar_consulta_solicitudes($conexion, $sql);
    $stmt->bind_param('ii', $idSolicitud, $idDocente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? normalizar_solicitud_docente($fila) : null;
}

?>
