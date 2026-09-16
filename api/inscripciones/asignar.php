<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json([
        'ok' => false,
        'mensaje' => 'Metodo no permitido.'
    ], 405);
}

$raw = file_get_contents('php://input');
$datos = json_decode($raw, true);

if (!is_array($datos)) {
    $datos = $_POST;
}

$permitidos = ['id_docente' => true, 'id_capacitacion' => true];
$erroresCampos = [];

foreach (array_keys($datos) as $campo) {
    if (!isset($permitidos[$campo])) {
        $erroresCampos[] = "El campo {$campo} no puede enviarse.";
    }
}

if ($erroresCampos) {
    responder_json([
        'ok' => false,
        'mensaje' => 'La solicitud contiene campos no permitidos.',
        'errores' => $erroresCampos
    ], 422);
}

$idDocente = filter_var($datos['id_docente'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idDocente) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_docente invalido.'
    ], 422);
}

$idCapacitacion = filter_var($datos['id_capacitacion'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$idCapacitacion) {
    responder_json([
        'ok' => false,
        'mensaje' => 'id_capacitacion invalido.'
    ], 422);
}

$conexion->begin_transaction();

try {
    $stmt = preparar_consulta_inscripciones(
        $conexion,
        "SELECT id FROM usuarios WHERE id = ? AND rol = 'docente' AND activo = 1 LIMIT 1"
    );
    $stmt->bind_param('i', $idDocente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $docente = $resultado->fetch_assoc();
    $stmt->close();

    if (!$docente) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'Docente no encontrado o inactivo.'
        ], 404);
    }

    $stmt = preparar_consulta_inscripciones(
        $conexion,
        "SELECT id FROM capacitaciones WHERE id = ? AND activo = 1 AND estado = 'activa' LIMIT 1"
    );
    $stmt->bind_param('i', $idCapacitacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $capacitacion = $resultado->fetch_assoc();
    $stmt->close();

    if (!$capacitacion) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'Capacitacion no encontrada o no disponible.'
        ], 404);
    }

    $stmt = preparar_consulta_inscripciones(
        $conexion,
        'SELECT id FROM inscripciones WHERE id_docente = ? AND id_capacitacion = ? LIMIT 1 FOR UPDATE'
    );
    $stmt->bind_param('ii', $idDocente, $idCapacitacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $existente = $resultado->fetch_assoc();
    $stmt->close();

    if ($existente) {
        $conexion->rollback();
        $conexion->close();
        responder_json([
            'ok' => false,
            'mensaje' => 'El docente ya tiene una inscripcion para esta capacitacion.'
        ], 409);
    }

    $estadoInicial = 'pendiente';
    $stmt = preparar_consulta_inscripciones(
        $conexion,
        'INSERT INTO inscripciones (id_docente, id_capacitacion, estado) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('iis', $idDocente, $idCapacitacion, $estadoInicial);

    if (!$stmt->execute()) {
        $errno = $stmt->errno ?: $conexion->errno;
        $stmt->close();
        $conexion->rollback();
        $conexion->close();

        if ((int) $errno === 1062) {
            responder_json([
                'ok' => false,
                'mensaje' => 'El docente ya tiene una inscripcion para esta capacitacion.'
            ], 409);
        }

        responder_json([
            'ok' => false,
            'mensaje' => 'No se pudo crear la inscripcion.'
        ], 500);
    }

    $idInscripcion = (int) $conexion->insert_id;
    $stmt->close();

    $sqlBuscar = 'SELECT
                      i.id,
                      i.estado,
                      i.fecha_inscripcion,
                      i.fecha_limite,
                      i.fecha_actualizacion,
                      u.id AS docente_id,
                      u.nombres AS docente_nombres,
                      u.apellidos AS docente_apellidos,
                      u.correo AS docente_correo,
                      c.id AS capacitacion_id,
                      c.titulo AS capacitacion_titulo
                  FROM inscripciones i
                  INNER JOIN usuarios u ON u.id = i.id_docente
                  INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
                  WHERE i.id = ?
                  LIMIT 1';

    $stmt = preparar_consulta_inscripciones($conexion, $sqlBuscar);
    $stmt->bind_param('i', $idInscripcion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $inscripcion = $resultado->fetch_assoc();
    $stmt->close();

    $conexion->commit();
    $conexion->close();

    responder_json([
        'ok' => true,
        'mensaje' => 'Inscripcion creada correctamente.',
        'inscripcion' => normalizar_inscripcion_admin($inscripcion)
    ], 201);
} catch (Throwable $error) {
    $conexion->rollback();
    $conexion->close();

    responder_json([
        'ok' => false,
        'mensaje' => 'No se pudo crear la inscripcion.'
    ], 500);
}

?>
