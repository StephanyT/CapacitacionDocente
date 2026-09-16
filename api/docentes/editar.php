<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = payload_docente_request();
$id = id_request_docente($payload);

if (!obtener_docente_por_id($conexion, $id)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Docente no encontrado.'], 404);
}

$errores = [];
$nombres = texto_requerido_docente($payload, 'nombres', $errores, 100);
$apellidos = texto_requerido_docente($payload, 'apellidos', $errores, 100);
$dni = texto_requerido_docente($payload, 'dni', $errores, 20);
$correo = texto_requerido_docente($payload, 'correo', $errores, 150);
$telefono = texto_opcional_docente($payload, 'telefono', 20);
$especialidad = texto_requerido_docente($payload, 'especialidad', $errores, 150);
$aniosExperiencia = filter_var($payload['anios_experiencia'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 0]
]);
$bio = texto_opcional_docente($payload, 'bio');

if ($correo !== '' && !correo_valido_institucional_docente($correo)) {
    $errores[] = 'El correo debe terminar en @certus.edu.pe.';
}

if ($errores) {
    responder_json(['ok' => false, 'mensaje' => 'Datos invalidos.', 'errores' => $errores], 422);
}

if (correo_duplicado_docente($conexion, $correo, $id)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya existe un docente registrado con ese correo.'], 409);
}

if (dni_duplicado_docente($conexion, $dni, $id)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya existe un docente registrado con ese DNI.'], 409);
}

$sql = 'UPDATE docentes SET nombres=?, apellidos=?, correo=?, dni=?, telefono=?, especialidad=?, anios_experiencia=?, bio=?
        WHERE id=?';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->bind_param('ssssssisi', $nombres, $apellidos, $correo, $dni, $telefono, $especialidad, $aniosExperiencia, $bio, $id);

if (!$stmt->execute()) {
    $codigoError = (int) $stmt->errno;
    $stmt->close();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json(['ok' => false, 'mensaje' => 'Ya existe un docente con ese correo o DNI.'], 409);
    }

    responder_json(['ok' => false, 'mensaje' => 'No se pudo actualizar el docente.'], 500);
}

$stmt->close();

$docente = obtener_docente_por_id($conexion, $id);
$conexion->close();

responder_json(['ok' => true, 'mensaje' => 'Docente actualizado correctamente.', 'docente' => $docente]);

?>
