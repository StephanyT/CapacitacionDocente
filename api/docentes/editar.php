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
    'nombres',
    'apellidos',
    'dni',
    'correo',
    'telefono',
    'especialidad',
    'anios_experiencia',
    'bio'
]);

$idDocente = id_docente_request($datos);
rechazar_si_docente_no_existe($conexion, $idDocente);

$docente = datos_docente_formulario($datos);
validar_duplicados_docente($conexion, $docente['correo'], $docente['dni'], $idDocente);

$sql = "UPDATE usuarios
        SET nombres = ?,
            apellidos = ?,
            dni = ?,
            correo = ?,
            telefono = ?,
            especialidad = ?,
            anios_experiencia = ?,
            bio = ?
        WHERE id = ? AND rol = 'docente'";

$stmt = preparar_consulta_docentes($conexion, $sql);
$stmt->bind_param(
    'ssssssisi',
    $docente['nombres'],
    $docente['apellidos'],
    $docente['dni'],
    $docente['correo'],
    $docente['telefono'],
    $docente['especialidad'],
    $docente['anios_experiencia'],
    $docente['bio'],
    $idDocente
);

if (!$stmt->execute()) {
    $codigoError = (int) $stmt->errno;
    $stmt->close();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe un usuario registrado con ese correo.'
        ], 409);
    }

    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo editar el docente.'
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
    'mensaje' => 'Docente editado correctamente.',
    'docente' => normalizar_docente($fila)
]);

?>
