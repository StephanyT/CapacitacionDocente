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

// La "fecha de hoy" simulada del sistema -- el mismo valor fijo que usa
// semaforoClase() en js/app.js para decidir si algo esta vencido (todo el
// proyecto usa datos de demo sembrados alrededor de esa fecha, no la fecha
// real del servidor). Si esto usara CURDATE() real, la mitad de las
// inscripciones de la demo pasarian a "vencida" de golpe sin que el
// semaforo del frontend (que si compara contra la fecha simulada) este de
// acuerdo, y las dos vistas del sistema quedarian contradiciendose entre si.
const CERTUS_HOY_SIMULADA = '2026-08-26';

// Transicion automatica pendiente/en curso -> vencida (RN propuesta en la
// logica de gestion de vencidos). No hay cron en este prototipo, asi que en
// vez de un job en segundo plano esto se corre de forma perezosa: cada vez
// que se listan inscripciones (admin o del propio docente) se actualiza
// primero cualquier registro cuyo plazo ya paso. Nunca toca 'completada' ni
// 'vencida' (ya esta en su estado final o ya fue marcada).
function actualizar_inscripciones_vencidas(mysqli $conexion): void
{
    $sql = "UPDATE inscripciones
            SET estado = 'vencida'
            WHERE estado IN ('pendiente', 'en curso')
              AND fecha_limite IS NOT NULL
              AND fecha_limite < '" . CERTUS_HOY_SIMULADA . "'";

    // Si todavia no se corrio migrar_vencidas_ampliacion.php, la columna
    // estado no acepta 'vencida' y esta consulta falla -- se atrapa en vez
    // de dejarla tumbar toda la pagina (dashboard, gestion de
    // capacitaciones, reportes dependen todos de listar inscripciones).
    try {
        $conexion->query($sql);
    } catch (mysqli_sql_exception $error) {
        // No hace nada mas: sin la migracion, simplemente no hay
        // transicion automatica a vencida todavia (se sigue viendo el
        // semaforo calculado del frontend como antes).
    }
}

function normalizar_inscripcion_admin(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'estado' => $fila['estado'],
        'fecha_inscripcion' => $fila['fecha_inscripcion'],
        'fecha_limite' => $fila['fecha_limite'],
        'fecha_actualizacion' => $fila['fecha_actualizacion'],
        // Ultimo admin que cambio el estado (null si nunca se toco desde que
        // se agrego esta columna, o si la inscripcion sigue en su estado
        // inicial de creacion).
        'actualizado_por' => isset($fila['actualizado_por']) && $fila['actualizado_por'] !== null
            ? [
                'id' => (int) $fila['actualizado_por'],
                'nombre' => trim(($fila['actualizado_por_nombres'] ?? '') . ' ' . ($fila['actualizado_por_apellidos'] ?? ''))
            ]
            : null,
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
