<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';
require_once __DIR__ . '/../inscripciones/_helpers.php';

exigir_rol('admin', true);
$conexion->set_charset('utf8mb4');

// Para que el reporte no muestre "en curso"/"pendiente" en filas cuyo plazo
// ya paso (ver actualizar_inscripciones_vencidas en api/inscripciones/_helpers.php).
actualizar_inscripciones_vencidas($conexion);

function preparar_consulta_reporte(mysqli $conexion, string $sql): mysqli_stmt
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

function bind_parametros_reporte(mysqli_stmt $stmt, string $tipos, array &$parametros): void
{
    if ($tipos === '') {
        return;
    }

    $referencias = [];
    $referencias[] = &$tipos;

    foreach ($parametros as &$valor) {
        $referencias[] = &$valor;
    }

    call_user_func_array([$stmt, 'bind_param'], $referencias);
}

function ejecutar_consulta_reporte(mysqli_stmt $stmt): mysqli_result
{
    if (!$stmt->execute()) {
        $stmt->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo obtener el reporte.'
        ], 500);
    }

    return $stmt->get_result();
}

function obtener_parametro_reporte(string $nombre): string
{
    return trim((string) ($_GET[$nombre] ?? ''));
}

function normalizar_filtro_reporte(string $nombre): string
{
    $valor = obtener_parametro_reporte($nombre);
    return $valor === 'todas' ? '' : $valor;
}

function validar_fecha_reporte(string $fecha, string $campo): void
{
    if ($fecha === '') {
        return;
    }

    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        responder_json([
            'ok' => false,
            'mensaje' => "{$campo} invalido."
        ], 422);
    }
}

$idDocente = normalizar_filtro_reporte('docente');
$sede = normalizar_filtro_reporte('sede');
$programa = normalizar_filtro_reporte('programa');
$estado = normalizar_filtro_reporte('estado');
$desde = obtener_parametro_reporte('desde');
$hasta = obtener_parametro_reporte('hasta');

if ($idDocente !== '') {
    $idDocenteFiltrado = filter_var($idDocente, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$idDocenteFiltrado) {
        responder_json([
            'ok' => false,
            'mensaje' => 'docente invalido.'
        ], 422);
    }

    $idDocente = (int) $idDocenteFiltrado;
}

if ($estado !== '' && !in_array($estado, ['pendiente', 'en curso', 'completada', 'vencida'], true)) {
    responder_json([
        'ok' => false,
        'mensaje' => 'estado invalido.'
    ], 422);
}

validar_fecha_reporte($desde, 'desde');
validar_fecha_reporte($hasta, 'hasta');

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    responder_json([
        'ok' => false,
        'mensaje' => 'El periodo seleccionado es invalido.'
    ], 422);
}

$sqlDocentes = "SELECT id, nombres, apellidos
                FROM docentes
                WHERE activo = 1
                ORDER BY apellidos ASC, nombres ASC, id ASC";
$stmtDocentes = preparar_consulta_reporte($conexion, $sqlDocentes);
$resultadoDocentes = ejecutar_consulta_reporte($stmtDocentes);
$docentes = [];

while ($fila = $resultadoDocentes->fetch_assoc()) {
    $docentes[] = [
        'id' => (int) $fila['id'],
        'nombres' => $fila['nombres'],
        'apellidos' => $fila['apellidos']
    ];
}

$stmtDocentes->close();

$condiciones = [];
$tipos = '';
$parametros = [];

if ($idDocente !== '') {
    $condiciones[] = 'd.id = ?';
    $tipos .= 'i';
    $parametros[] = $idDocente;
}

if ($sede !== '') {
    $condiciones[] = 'c.sede = ?';
    $tipos .= 's';
    $parametros[] = $sede;
}

if ($programa !== '') {
    $condiciones[] = 'c.programa = ?';
    $tipos .= 's';
    $parametros[] = $programa;
}

if ($estado !== '') {
    $condiciones[] = 'i.estado = ?';
    $tipos .= 's';
    $parametros[] = $estado;
}

if ($desde !== '' || $hasta !== '') {
    $condiciones[] = "i.estado = 'completada'";

    if ($desde !== '') {
        $condiciones[] = 'DATE(i.fecha_actualizacion) >= ?';
        $tipos .= 's';
        $parametros[] = $desde;
    }

    if ($hasta !== '') {
        $condiciones[] = 'DATE(i.fecha_actualizacion) <= ?';
        $tipos .= 's';
        $parametros[] = $hasta;
    }
}

$where = $condiciones ? implode(' AND ', $condiciones) : '1=1';
$sqlReporte = "SELECT
                    i.id AS inscripcion_id,
                    i.estado,
                    i.fecha_inscripcion,
                    i.fecha_limite,
                    CASE
                        WHEN i.estado = 'completada' THEN DATE(i.fecha_actualizacion)
                        ELSE NULL
                    END AS fecha_finalizacion,
                    d.id AS docente_id,
                    CONCAT(d.nombres, ' ', d.apellidos) AS docente,
                    c.id AS capacitacion_id,
                    c.titulo AS capacitacion,
                    c.sede,
                    c.programa
                FROM inscripciones i
                INNER JOIN docentes d ON d.id = i.id_docente
                INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
                WHERE {$where}
                ORDER BY i.fecha_inscripcion DESC, i.id DESC";

$stmtReporte = preparar_consulta_reporte($conexion, $sqlReporte);
bind_parametros_reporte($stmtReporte, $tipos, $parametros);
$resultadoReporte = ejecutar_consulta_reporte($stmtReporte);
$registros = [];

while ($fila = $resultadoReporte->fetch_assoc()) {
    $registros[] = [
        'id_inscripcion' => (int) $fila['inscripcion_id'],
        'id_docente' => (int) $fila['docente_id'],
        'id_capacitacion' => (int) $fila['capacitacion_id'],
        'docente' => $fila['docente'],
        'capacitacion' => $fila['capacitacion'],
        'estado' => $fila['estado'],
        'fecha_inscripcion' => $fila['fecha_inscripcion'],
        'fecha_limite' => $fila['fecha_limite'],
        'fecha_finalizacion' => $fila['fecha_finalizacion'],
        'sede' => $fila['sede'],
        'programa' => $fila['programa']
    ];
}

$stmtReporte->close();
$conexion->close();

responder_json([
    'ok' => true,
    'docentes' => $docentes,
    'registros' => $registros
]);

?>
