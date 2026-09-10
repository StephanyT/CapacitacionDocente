<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

$conexion->set_charset('utf8mb4');

function preparar_consulta_docentes(mysqli $conexion, string $sql): mysqli_stmt
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

function payload_docente_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function rechazar_campos_no_permitidos_docente(array $datos, array $permitidos): void
{
    $permitidosMapa = array_fill_keys($permitidos, true);
    $errores = [];

    foreach (array_keys($datos) as $campo) {
        if (!isset($permitidosMapa[$campo])) {
            $errores[] = "El campo {$campo} no puede enviarse.";
        }
    }

    if ($errores) {
        responder_json([
            'ok' => false,
            'mensaje' => 'La solicitud contiene campos no permitidos.',
            'errores' => $errores
        ], 422);
    }
}

function valor_texto_docente(array $datos, string $campo, bool $obligatorio = false): ?string
{
    $valor = trim((string) ($datos[$campo] ?? ''));

    if ($obligatorio && $valor === '') {
        responder_json([
            'ok' => false,
            'mensaje' => "El campo {$campo} es obligatorio."
        ], 422);
    }

    return $valor === '' ? null : $valor;
}

function validar_correo_docente(?string $correo): string
{
    if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'correo invalido.'
        ], 422);
    }

    return strtolower($correo);
}

function validar_anios_experiencia_docente($valor): int
{
    $anios = filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0]
    ]);

    if ($anios === false) {
        responder_json([
            'ok' => false,
            'mensaje' => 'anios_experiencia invalido.'
        ], 422);
    }

    return (int) $anios;
}

function id_docente_request(array $datos): int
{
    if (!array_key_exists('id_docente', $datos)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'El campo id_docente es obligatorio.'
        ], 422);
    }

    $id = filter_var($datos['id_docente'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$id) {
        responder_json([
            'ok' => false,
            'mensaje' => 'id_docente invalido.'
        ], 422);
    }

    return (int) $id;
}

function activo_docente_request(array $datos): int
{
    if (!array_key_exists('activo', $datos)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'El campo activo es obligatorio.'
        ], 422);
    }

    $activo = filter_var($datos['activo'], FILTER_VALIDATE_INT);

    if ($activo !== 0 && $activo !== 1) {
        responder_json([
            'ok' => false,
            'mensaje' => 'activo invalido.'
        ], 422);
    }

    return (int) $activo;
}

function existe_docente(mysqli $conexion, int $idDocente): bool
{
    $stmt = preparar_consulta_docentes(
        $conexion,
        "SELECT id FROM usuarios WHERE id = ? AND rol = 'docente' LIMIT 1"
    );
    $stmt->bind_param('i', $idDocente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

function rechazar_si_docente_no_existe(mysqli $conexion, int $idDocente): void
{
    if (!existe_docente($conexion, $idDocente)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Docente no encontrado.'
        ], 404);
    }
}

function existe_correo_usuario(mysqli $conexion, string $correo, ?int $excluirId = null): bool
{
    if ($excluirId !== null) {
        $stmt = preparar_consulta_docentes(
            $conexion,
            'SELECT id FROM usuarios WHERE correo = ? AND id <> ? LIMIT 1'
        );
        $stmt->bind_param('si', $correo, $excluirId);
    } else {
        $stmt = preparar_consulta_docentes(
            $conexion,
            'SELECT id FROM usuarios WHERE correo = ? LIMIT 1'
        );
        $stmt->bind_param('s', $correo);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

function existe_dni_docente(mysqli $conexion, ?string $dni, ?int $excluirId = null): bool
{
    if ($dni === null) {
        return false;
    }

    if ($excluirId !== null) {
        $stmt = preparar_consulta_docentes(
            $conexion,
            "SELECT id FROM usuarios WHERE dni = ? AND rol = 'docente' AND id <> ? LIMIT 1"
        );
        $stmt->bind_param('si', $dni, $excluirId);
    } else {
        $stmt = preparar_consulta_docentes(
            $conexion,
            "SELECT id FROM usuarios WHERE dni = ? AND rol = 'docente' LIMIT 1"
        );
        $stmt->bind_param('s', $dni);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

function validar_duplicados_docente(mysqli $conexion, string $correo, ?string $dni, ?int $excluirId = null): void
{
    if (existe_correo_usuario($conexion, $correo, $excluirId)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe un usuario registrado con ese correo.'
        ], 409);
    }

    if (existe_dni_docente($conexion, $dni, $excluirId)) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Ya existe un docente registrado con ese DNI.'
        ], 409);
    }
}

function datos_docente_formulario(array $datos): array
{
    $correo = validar_correo_docente(valor_texto_docente($datos, 'correo', true));

    return [
        'nombres' => valor_texto_docente($datos, 'nombres', true),
        'apellidos' => valor_texto_docente($datos, 'apellidos', true),
        'dni' => valor_texto_docente($datos, 'dni'),
        'correo' => $correo,
        'telefono' => valor_texto_docente($datos, 'telefono'),
        'especialidad' => valor_texto_docente($datos, 'especialidad', true),
        'anios_experiencia' => validar_anios_experiencia_docente($datos['anios_experiencia'] ?? 0),
        'bio' => valor_texto_docente($datos, 'bio')
    ];
}

function normalizar_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'nombres' => $fila['nombres'],
        'apellidos' => $fila['apellidos'],
        'dni' => $fila['dni'],
        'correo' => $fila['correo'],
        'telefono' => $fila['telefono'],
        'especialidad' => $fila['especialidad'],
        'anios_experiencia' => (int) $fila['anios_experiencia'],
        'bio' => $fila['bio'],
        'activo' => (int) $fila['activo'],
        'fecha_creacion' => $fila['fecha_creacion']
    ];
}

?>
