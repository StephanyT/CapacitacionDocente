<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_inscripciones(mysqli $conexion, string $sql): mysqli_stmt
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

function normalizar_inscripcion_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'fecha_inscripcion' => $fila['fecha_inscripcion'],
        'fecha_limite' => $fila['fecha_limite'],
        'fecha_actualizacion' => $fila['fecha_actualizacion'],
        'capacitacion' => [
            'id' => (int) $fila['capacitacion_id'],
            'titulo' => $fila['capacitacion_titulo'],
            'fecha' => $fila['capacitacion_fecha'],
            'duracion' => $fila['capacitacion_duracion'],
            'sede' => $fila['capacitacion_sede'],
            'programa' => $fila['capacitacion_programa']
        ]
    ];
}

function normalizar_inscripcion_admin(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'fecha_inscripcion' => $fila['fecha_inscripcion'],
        'fecha_limite' => $fila['fecha_limite'],
        'fecha_actualizacion' => $fila['fecha_actualizacion'],
        'docente' => [
            'id' => (int) $fila['docente_id'],
            'nombres' => $fila['docente_nombres'],
            'apellidos' => $fila['docente_apellidos'],
            'correo' => $fila['docente_correo']
        ],
        'capacitacion' => [
            'id' => (int) $fila['capacitacion_id'],
            'titulo' => $fila['capacitacion_titulo']
        ]
    ];
}

?>
