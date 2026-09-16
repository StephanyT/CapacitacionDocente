<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$payload = payload_request();
$id = id_request($payload);
$datos = datos_capacitacion_validados($payload);

$capacitacionAnterior = obtener_capacitacion_por_id($conexion, $id, false);

if (!$capacitacionAnterior) {
    $conexion->close();
    responder_json([
        'ok' => false,
        'mensaje' => 'Capacitacion no encontrada.'
    ], 404);
}

$sql = 'UPDATE capacitaciones
        SET titulo = ?, fecha = ?, duracion = ?, sede = ?, programa = ?,
            descripcion = ?, instructor = ?, objetivos = ?, temario = ?,
            habilidades = ?, instructor_bio = ?, requisitos = ?, estado = ?,
            activo = ?
        WHERE id = ?';

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo preparar la consulta.'
    ], 500);
}

$stmt->bind_param(
    'sssssssssssssii',
    $datos['titulo'],
    $datos['fecha'],
    $datos['duracion'],
    $datos['sede'],
    $datos['programa'],
    $datos['descripcion'],
    $datos['instructor'],
    $datos['objetivos'],
    $datos['temario'],
    $datos['habilidades'],
    $datos['instructor_bio'],
    $datos['requisitos'],
    $datos['estado'],
    $datos['activo'],
    $id
);
$stmt->execute();
$stmt->close();

// RN-05: si se reprogramo la fecha, desplazar la fecha_limite de los
// docentes que aun no completan la capacitacion por la misma cantidad de
// dias (preserva el margen original en vez de dejar el semaforo
// desincronizado del nuevo cronograma real).
if ($datos['fecha'] !== $capacitacionAnterior['fecha']) {
    $fechaAnterior = new DateTime($capacitacionAnterior['fecha']);
    $fechaNueva = new DateTime($datos['fecha']);
    $diffDias = (int) $fechaAnterior->diff($fechaNueva)->format('%r%a');

    if ($diffDias !== 0) {
        $sqlShift = "UPDATE inscripciones
                     SET fecha_limite = DATE_ADD(fecha_limite, INTERVAL ? DAY)
                     WHERE id_capacitacion = ? AND estado != 'completada' AND fecha_limite IS NOT NULL";
        $stmtShift = $conexion->prepare($sqlShift);
        if ($stmtShift) {
            $stmtShift->bind_param('ii', $diffDias, $id);
            $stmtShift->execute();
            $stmtShift->close();
        }
    }
}

$capacitacion = obtener_capacitacion_por_id($conexion, $id, false);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Capacitacion actualizada correctamente.',
    'capacitacion' => $capacitacion
]);

?>
