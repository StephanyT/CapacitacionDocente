<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

$conexion->set_charset('utf8mb4');

function preparar_consulta_evidencias(mysqli $conexion, string $sql): mysqli_stmt
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

function payload_evidencia_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function rechazar_campos_no_permitidos_evidencia(array $datos, array $permitidos): void
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

function id_insignia_request(array $datos): int
{
    if (!array_key_exists('id_insignia', $datos)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'El campo id_insignia es obligatorio.'
        ], 422);
    }

    $id = filter_var($datos['id_insignia'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$id) {
        responder_json([
            'ok' => false,
            'mensaje' => 'id_insignia invalido.'
        ], 422);
    }

    return (int) $id;
}

function id_evidencia_request(array $datos): int
{
    if (!array_key_exists('id_evidencia', $datos)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'El campo id_evidencia es obligatorio.'
        ], 422);
    }

    $id = filter_var($datos['id_evidencia'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$id) {
        responder_json([
            'ok' => false,
            'mensaje' => 'id_evidencia invalido.'
        ], 422);
    }

    return (int) $id;
}

function estado_revision_request(array $datos): string
{
    $estado = trim((string) ($datos['estado'] ?? ''));

    if (!in_array($estado, ['aprobada', 'rechazada'], true)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'estado invalido.'
        ], 422);
    }

    return $estado;
}

function texto_evidencia_request(array $datos): string
{
    $texto = trim((string) ($datos['texto'] ?? ''));

    if ($texto === '') {
        responder_json([
            'ok' => false,
            'mensaje' => 'El campo texto es obligatorio.'
        ], 422);
    }

    return $texto;
}

function obtener_insignia_activa(mysqli $conexion, int $idInsignia): ?array
{
    $sql = 'SELECT
                id,
                nombre,
                codigo_icono,
                requisito,
                area,
                id_capacitacion_requerida,
                requiere_evidencia,
                activo
            FROM insignias
            WHERE id = ? AND activo = 1
            LIMIT 1';

    $stmt = preparar_consulta_evidencias($conexion, $sql);
    $stmt->bind_param('i', $idInsignia);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        return null;
    }

    return [
        'id' => (int) $fila['id'],
        'nombre' => $fila['nombre'],
        'codigo_icono' => $fila['codigo_icono'],
        'requisito' => $fila['requisito'],
        'area' => $fila['area'],
        'id_capacitacion_requerida' => $fila['id_capacitacion_requerida'] !== null
            ? (int) $fila['id_capacitacion_requerida']
            : null,
        'requiere_evidencia' => (int) $fila['requiere_evidencia'] === 1,
        'activo' => (int) $fila['activo'] === 1
    ];
}

function docente_cumple_requisito_insignia(mysqli $conexion, int $idDocente, array $insignia): bool
{
    if ($insignia['id_capacitacion_requerida'] === null) {
        return false;
    }

    $sql = "SELECT id
            FROM inscripciones
            WHERE id_docente = ?
              AND id_capacitacion = ?
              AND estado = 'completada'
            LIMIT 1";

    $stmt = preparar_consulta_evidencias($conexion, $sql);
    $stmt->bind_param('ii', $idDocente, $insignia['id_capacitacion_requerida']);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cumple = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $cumple;
}

function existe_evidencia_pendiente(mysqli $conexion, int $idDocente, int $idInsignia): bool
{
    $sql = "SELECT id
            FROM evidencias_insignia
            WHERE id_docente = ?
              AND id_insignia = ?
              AND estado = 'pendiente'
            LIMIT 1";

    $stmt = preparar_consulta_evidencias($conexion, $sql);
    $stmt->bind_param('ii', $idDocente, $idInsignia);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

function normalizar_evidencia_admin(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_docente' => (int) $fila['id_docente'],
        'id_insignia' => (int) $fila['id_insignia'],
        'texto' => $fila['texto'],
        'estado' => $fila['estado'],
        'fecha_envio' => $fila['fecha_envio'],
        'fecha_revision' => $fila['fecha_revision'] ?? null,
        'revisado_por' => $fila['revisado_por'] !== null ? (int) $fila['revisado_por'] : null,
        'docente' => [
            'id' => (int) $fila['docente_id'],
            'nombres' => $fila['docente_nombres'],
            'apellidos' => $fila['docente_apellidos'],
            'nombre_completo' => trim($fila['docente_nombres'] . ' ' . $fila['docente_apellidos']),
            'correo' => $fila['docente_correo']
        ],
        'insignia' => [
            'id' => (int) $fila['insignia_id'],
            'nombre' => $fila['insignia_nombre'],
            'codigo_icono' => $fila['insignia_codigo_icono']
        ]
    ];
}

function normalizar_evidencia_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_insignia' => (int) $fila['id_insignia'],
        'texto' => $fila['texto'],
        'estado' => $fila['estado'],
        'fecha_envio' => $fila['fecha_envio'],
        'fecha_revision' => $fila['fecha_revision'],
        'revisado_por' => $fila['revisado_por'] !== null ? (int) $fila['revisado_por'] : null,
        'insignia' => [
            'id' => (int) $fila['insignia_id'],
            'nombre' => $fila['insignia_nombre'],
            'codigo_icono' => $fila['insignia_codigo_icono']
        ]
    ];
}

?>
