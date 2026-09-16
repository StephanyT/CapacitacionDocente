<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

// Periodo institucional activo. El sistema solo maneja un periodo a la vez
// (no hay RF de historico de periodos pasados) — mismo valor que se usaba
// como default en el front cuando el plan vivia en localStorage.
const PERIODO_ACTIVO_PLAN = '2026-II';

function preparar_consulta_planes(mysqli $conexion, string $sql): mysqli_stmt
{
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    return $stmt;
}

function normalizar_meta(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'texto' => $fila['texto'],
        'estado' => $fila['estado']
    ];
}

// Trae (o arma) el plan del docente para el periodo activo, con sus metas.
// Si el docente todavia no tiene fila en planes_desarrollo, devuelve un plan
// "vacio" en memoria (id null) sin insertar nada — igual que antes, cuando
// planDeDocente() devolvia un default {periodo, metas:[]} sin persistir
// hasta que el docente agregara su primera meta.
function obtener_plan_docente(mysqli $conexion, int $idDocente, string $periodo): array
{
    $sql = 'SELECT id FROM planes_desarrollo WHERE id_docente = ? AND periodo = ? LIMIT 1';
    $stmt = preparar_consulta_planes($conexion, $sql);
    $stmt->bind_param('is', $idDocente, $periodo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        return ['id' => null, 'id_docente' => $idDocente, 'periodo' => $periodo, 'metas' => []];
    }

    $idPlan = (int) $fila['id'];
    $sqlMetas = 'SELECT id, texto, estado FROM metas_desarrollo WHERE id_plan = ? ORDER BY id ASC';
    $stmt = preparar_consulta_planes($conexion, $sqlMetas);
    $stmt->bind_param('i', $idPlan);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $metas = [];
    while ($m = $resultado->fetch_assoc()) {
        $metas[] = normalizar_meta($m);
    }
    $stmt->close();

    return ['id' => $idPlan, 'id_docente' => $idDocente, 'periodo' => $periodo, 'metas' => $metas];
}

// Encuentra el plan del docente para el periodo activo, o lo crea si es la
// primera meta que agrega.
function obtener_o_crear_id_plan(mysqli $conexion, int $idDocente, string $periodo): int
{
    $sql = 'SELECT id FROM planes_desarrollo WHERE id_docente = ? AND periodo = ? LIMIT 1';
    $stmt = preparar_consulta_planes($conexion, $sql);
    $stmt->bind_param('is', $idDocente, $periodo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila) {
        return (int) $fila['id'];
    }

    $sqlInsert = 'INSERT INTO planes_desarrollo (id_docente, periodo) VALUES (?, ?)';
    $stmt = preparar_consulta_planes($conexion, $sqlInsert);
    $stmt->bind_param('is', $idDocente, $periodo);
    $stmt->execute();
    $idPlan = (int) $conexion->insert_id;
    $stmt->close();

    return $idPlan;
}

?>
