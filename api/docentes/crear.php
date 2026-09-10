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
    'nombres',
    'apellidos',
    'dni',
    'correo',
    'telefono',
    'especialidad',
    'anios_experiencia',
    'bio',
    'password'
]);

$password = (string) ($datos['password'] ?? '');

if ($password === '') {
    responder_json([
        'ok' => false,
        'mensaje' => 'El campo password es obligatorio.'
    ], 422);
}

$docente = datos_docente_formulario($datos);
validar_duplicados_docente($conexion, $docente['correo'], $docente['dni']);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$rol = 'docente';
$activo = 1;

$sql = 'INSERT INTO usuarios
        (nombres, apellidos, dni, correo, telefono, especialidad, anios_experiencia, bio, password, rol, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = preparar_consulta_docentes($conexion, $sql);
$stmt->bind_param(
    'ssssssisssi',
    $docente['nombres'],
    $docente['apellidos'],
    $docente['dni'],
    $docente['correo'],
    $docente['telefono'],
    $docente['especialidad'],
    $docente['anios_experiencia'],
    $docente['bio'],
    $passwordHash,
    $rol,
    $activo
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
        'mensaje' => 'No se pudo crear el docente.'
    ], 500);
}

$idDocente = (int) $conexion->insert_id;
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
    'mensaje' => 'Docente creado correctamente.',
    'docente' => normalizar_docente($fila)
], 201);

?>
