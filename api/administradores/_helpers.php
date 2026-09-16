<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function columnas_administrador(): string
{
    return 'id, nombres, apellidos, correo, activo, telefono, motivo_baja, fecha_baja, fecha_creacion';
}

function normalizar_administrador(array $fila): array
{
    return [
        'id' => (int) $fila['id'],
        'nombres' => $fila['nombres'],
        'apellidos' => $fila['apellidos'],
        'correo' => $fila['correo'],
        'activo' => (int) $fila['activo'] === 1,
        'telefono' => $fila['telefono'],
        'motivo_baja' => $fila['motivo_baja'],
        'fecha_baja' => $fila['fecha_baja']
    ];
}

function payload_administrador_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function texto_requerido_administrador(array $datos, string $campo, array &$errores, int $max = 0): string
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

function texto_opcional_administrador(array $datos, string $campo, int $max = 0): ?string
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

function id_request_administrador(array $datos = null): int
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

function obtener_administrador_por_id(mysqli $conexion, int $id): ?array
{
    $sql = 'SELECT ' . columnas_administrador() . ' FROM administradores WHERE id = ? LIMIT 1';
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? normalizar_administrador($fila) : null;
}

function correo_valido_institucional_administrador(string $correo): bool
{
    return (bool) preg_match('/@certus\.edu\.pe$/i', $correo);
}

function correo_duplicado_administrador(mysqli $conexion, string $correo, ?int $idExcluir): bool
{
    $sql = 'SELECT id FROM administradores WHERE correo = ?' . ($idExcluir ? ' AND id != ?' : '') . ' LIMIT 1';
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

function generar_password_temporal_administrador(int $longitud = 8): string
{
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $longitud; $i++) {
        $password .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $password;
}

?>
