<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$datos = payload_docente_request();
rechazar_campos_no_permitidos_docente($datos, [
    'id_docente',
    'activo'
]);

$idDocente = id_docente_request($datos);
$activo = activo_docente_request($datos);

rechazar_si_docente_no_existe($conexion, $idDocente);

$stmt = preparar_consulta_docentes(
    $conexion,
    "UPDATE usuarios SET activo = ? WHERE id = ? AND rol = 'docente'"
);
$stmt->bind_param('ii', $activo, $idDocente);

if (!$stmt->execute()) {
    $stmt->close();
    $conexion->close();

    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo cambiar el estado del docente.'
    ], 500);
}

$stmt->close();

$stmt = preparar_consulta_docentes(
    $conexion,
    "SELECT id, nombres, apellidos, dni, correo, telefono,
            especialidad, anios_experiencia, bio, activo, fecha_creacion
     FROM usuarios
     WHERE id = ? AND rol = 'docente'
     LIMIT 1"
);
$stmt->bind_param('i', $idDocente);
$stmt->execute();
$resultado = $stmt->get_result();
$fila = $resultado->fetch_assoc();
$stmt->close();
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => $activo === 1 ? 'Docente activado correctamente.' : 'Docente desactivado correctamente.',
    'docente' => normalizar_docente($fila)
]);

?>
