<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function columnas_docente(): string
{
    return 'id, nombres, apellidos, correo, activo, dni, telefono,
            especialidad, anios_experiencia, bio, motivo_baja, fecha_baja,
            fecha_creacion';
}

function normalizar_docente(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'nombres' => $fila['nombres'],
        'apellidos' => $fila['apellidos'],
        'correo' => $fila['correo'],
        'activo' => (int) $fila['activo'] === 1,
        'dni' => $fila['dni'],
        'telefono' => $fila['telefono'],
        'especialidad' => $fila['especialidad'],
        'anios_experiencia' => $fila['anios_experiencia'] !== null ? (int) $fila['anios_experiencia'] : 0,
        'bio' => $fila['bio'],
        'motivo_baja' => $fila['motivo_baja'],
        'fecha_baja' => $fila['fecha_baja']
    ];
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

function texto_requerido_docente(array $datos, string $campo, array &$errores, int $max = 0): string
{
    $valor = trim((string) ($datos[$campo] ?? ''));

    if ($valor === '') {
        $errores[] = "El campo {$campo} es obligatorio.";
    }

    if ($max > 0 && strlen($valor) > $max) {
        $errores[] = "El campo {$campo} no debe superar {$max} caracteres.";
    }

    return $valor;
}

function texto_opcional_docente(array $datos, string $campo, int $max = 0): ?string
{
    $valor = trim((string) ($datos[$campo] ?? ''));

    if ($valor === '') {
        return null;
    }

    if ($max > 0 && strlen($valor) > $max) {
        return substr($valor, 0, $max);
    }

    return $valor;
}

function id_request_docente(array $datos = null): int
{
    $fuente = $datos ?? $_GET;
    $id = filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$id) {
        responder_json(['ok' => false, 'mensaje' => 'ID invalido.'], 422);
    }

    return (int) $id;
}

function obtener_docente_por_id(mysqli $conexion, int $id): ?array
{
    $sql = 'SELECT ' . columnas_docente() . ' FROM docentes WHERE id = ? LIMIT 1';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? normalizar_docente($fila) : null;
}

function correo_valido_institucional_docente(string $correo): bool
{
    return (bool) preg_match('/@certus\.edu\.pe$/i', $correo);
}

function correo_duplicado_docente(mysqli $conexion, string $correo, ?int $idExcluir): bool
{
    $sql = 'SELECT id FROM docentes WHERE correo = ?' . ($idExcluir ? ' AND id != ?' : '') . ' LIMIT 1';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    if ($idExcluir) {
        $stmt->bind_param('si', $correo, $idExcluir);
    } else {
        $stmt->bind_param('s', $correo);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

function dni_duplicado_docente(mysqli $conexion, string $dni, ?int $idExcluir): bool
{
    $sql = 'SELECT id FROM docentes WHERE dni = ?' . ($idExcluir ? ' AND id != ?' : '') . ' LIMIT 1';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    if ($idExcluir) {
        $stmt->bind_param('si', $dni, $idExcluir);
    } else {
        $stmt->bind_param('s', $dni);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $existe = (bool) $resultado->fetch_assoc();
    $stmt->close();

    return $existe;
}

?>
