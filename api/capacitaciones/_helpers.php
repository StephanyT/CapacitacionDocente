<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

function columnas_capacitacion(): string
{
    return 'id, titulo, fecha, duracion, sede, programa, descripcion, instructor,
            objetivos, temario, habilidades, instructor_bio, requisitos, estado,
            activo, fecha_creacion, fecha_actualizacion';
}

function decodificar_arreglo_json($valor): array
{
    if ($valor === null || $valor === '') {
        return [];
    }

    $datos = json_decode($valor, true);
    return is_array($datos) ? $datos : [];
}

function normalizar_capacitacion(array $fila): array
{
    $fila['id'] = (int) $fila['id'];
    $fila['activo'] = (int) $fila['activo'] === 1;
    $fila['objetivos'] = decodificar_arreglo_json($fila['objetivos'] ?? null);
    $fila['temario'] = decodificar_arreglo_json($fila['temario'] ?? null);
    $fila['habilidades'] = decodificar_arreglo_json($fila['habilidades'] ?? null);

    return $fila;
}

function payload_request(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function texto_requerido(array $datos, string $campo, array &$errores, int $max = 0): string
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

function texto_opcional(array $datos, string $campo, int $max = 0): ?string
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

function arreglo_json_desde_payload(array $datos, string $campo): string
{
    $valor = $datos[$campo] ?? [];

    if (is_string($valor)) {
        $decodificado = json_decode($valor, true);
        if (is_array($decodificado)) {
            $valor = $decodificado;
        } else {
            $valor = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $valor))));
        }
    }

    if (!is_array($valor)) {
        $valor = [];
    }

    $limpio = array_values(array_filter(array_map(function ($item) {
        return trim((string) $item);
    }, $valor), function ($item) {
        return $item !== '';
    }));

    return json_encode($limpio);
}

function datos_capacitacion_validados(array $datos): array
{
    $errores = [];

    $titulo = texto_requerido($datos, 'titulo', $errores, 180);
    $fecha = texto_requerido($datos, 'fecha', $errores, 10);
    $duracion = texto_requerido($datos, 'duracion', $errores, 50);
    $sede = texto_requerido($datos, 'sede', $errores, 120);
    $programa = texto_requerido($datos, 'programa', $errores, 150);
    $descripcion = texto_requerido($datos, 'descripcion', $errores);

    if ($fecha !== '') {
        $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
            $errores[] = 'La fecha debe tener formato YYYY-MM-DD.';
        }
    }

    $estado = trim((string) ($datos['estado'] ?? 'activa'));
    if (!in_array($estado, ['activa', 'inactiva'], true)) {
        $errores[] = 'El estado no es valido.';
    }

    if ($errores) {
        responder_json([
            'ok' => false,
            'mensaje' => 'Datos invalidos.',
            'errores' => $errores
        ], 422);
    }

    return [
        'titulo' => $titulo,
        'fecha' => $fecha,
        'duracion' => $duracion,
        'sede' => $sede,
        'programa' => $programa,
        'descripcion' => $descripcion,
        'instructor' => texto_opcional($datos, 'instructor', 150),
        'objetivos' => arreglo_json_desde_payload($datos, 'objetivos'),
        'temario' => arreglo_json_desde_payload($datos, 'temario'),
        'habilidades' => arreglo_json_desde_payload($datos, 'habilidades'),
        'instructor_bio' => texto_opcional($datos, 'instructor_bio'),
        'requisitos' => texto_opcional($datos, 'requisitos'),
        'estado' => $estado,
        'activo' => $estado === 'activa' ? 1 : 0
    ];
}

function id_request(array $datos = null): int
{
    $fuente = $datos ?? $_GET;
    $id = filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$id) {
        responder_json([
            'ok' => false,
            'mensaje' => 'ID invalido.'
        ], 422);
    }

    return (int) $id;
}

function obtener_capacitacion_por_id(mysqli $conexion, int $id, bool $soloActivas): ?array
{
    $sql = 'SELECT ' . columnas_capacitacion() . ' FROM capacitaciones WHERE id = ?';

    if ($soloActivas) {
        $sql .= ' AND activo = 1';
    }

    $sql .= ' LIMIT 1';

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo preparar la consulta.'
        ], 500);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? normalizar_capacitacion($fila) : null;
}

?>
