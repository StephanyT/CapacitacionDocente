<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function preparar_consulta_autoeval(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    return $stmt;
}

// El checklist se guarda como texto corto ("1,0,1") en vez de una tabla
// aparte, porque siempre son las mismas 3 preguntas fijas del formulario
// (no hay RF que pida que sean configurables) — igual de simple que se
// guardaba en localStorage, solo que ahora persiste de verdad.
function checklist_a_texto(array $checklist): string
{
    return implode(',', array_map(fn($v) => $v ? '1' : '0', $checklist));
}

function checklist_a_array(?string $texto): array
{
    if ($texto === null || $texto === '') {
        return [];
    }

    return array_map(fn($v) => $v === '1', explode(',', $texto));
}

function normalizar_autoevaluacion(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_docente' => (int) $fila['id_docente'],
        'id_capacitacion' => (int) $fila['id_capacitacion'],
        'video_link' => $fila['video_link'],
        'checklist' => checklist_a_array($fila['checklist']),
        // notaPropia (sin guion bajo) para que coincida con el nombre que ya
        // usa el frontend (autoevaluacion.html), migrado tal cual de la
        // version en localStorage.
        'notaPropia' => $fila['nota_propia'],
        'fecha' => $fila['fecha'],
        'comentarios' => []
    ];
}

function normalizar_comentario_autoeval(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'id_autoevaluacion' => (int) $fila['id_autoevaluacion'],
        'autor' => $fila['autor'],
        'texto' => $fila['texto'],
        'fecha' => $fila['fecha']
    ];
}

// Adjunta los comentarios de cada autoevaluacion (una sola consulta con IN
// en vez de una por fila) — igual que se hacia en memoria con el arreglo
// local de comentarios.
function adjuntar_comentarios(mysqli $conexion, array $autoevaluaciones): array
{
    if (!$autoevaluaciones) {
        return $autoevaluaciones;
    }

    $ids = array_column($autoevaluaciones, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));

    $sql = "SELECT id, id_autoevaluacion, autor, texto, fecha
            FROM comentarios_autoevaluacion
            WHERE id_autoevaluacion IN ($marcadores)
            ORDER BY fecha ASC, id ASC";
    $stmt = preparar_consulta_autoeval($conexion, $sql);
    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $porAutoeval = [];
    while ($fila = $resultado->fetch_assoc()) {
        $c = normalizar_comentario_autoeval($fila);
        $porAutoeval[$c['id_autoevaluacion']][] = $c;
    }
    $stmt->close();

    foreach ($autoevaluaciones as &$a) {
        $a['comentarios'] = $porAutoeval[$a['id']] ?? [];
    }

    return $autoevaluaciones;
}

?>
