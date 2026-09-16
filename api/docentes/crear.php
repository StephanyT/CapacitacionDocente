<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = payload_docente_request();
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

if (correo_duplicado_docente($conexion, $correo, null)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya existe un docente registrado con ese correo.'], 409);
}

if (dni_duplicado_docente($conexion, $dni, null)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya existe un docente registrado con ese DNI.'], 409);
}

// Password inicial: el DNI (patron institucional habitual). El docente
// puede cambiarla despues desde su perfil (fuera del alcance actual).
$passwordHash = password_hash($dni, PASSWORD_DEFAULT);

$sql = 'INSERT INTO docentes (nombres, apellidos, correo, password, activo, dni, telefono, especialidad, anios_experiencia, bio)
        VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->bind_param(
    'sssssssis',
    $nombres, $apellidos, $correo, $passwordHash, $dni, $telefono, $especialidad, $aniosExperiencia, $bio
);
$stmt->execute();

if ($stmt->errno) {
    $codigoError = (int) $stmt->errno;
    $stmt->close();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json(['ok' => false, 'mensaje' => 'Ya existe un docente con ese correo o DNI.'], 409);
    }

    responder_json(['ok' => false, 'mensaje' => 'No se pudo crear el docente.'], 500);
}

$idNuevo = (int) $conexion->insert_id;
$stmt->close();

$docente = obtener_docente_por_id($conexion, $idNuevo);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => 'Docente creado correctamente. Contrasena inicial: su DNI.',
    'docente' => $docente
]);

?>
