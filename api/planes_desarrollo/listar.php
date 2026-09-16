<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

$sql = 'SELECT id, id_docente, periodo FROM planes_desarrollo';
$stmt = preparar_consulta_planes($conexion, $sql);
$stmt->execute();
$resultado = $stmt->get_result();
$planes = [];

while ($fila = $resultado->fetch_assoc()) {
    $planes[] = [
        'id' => (int) $fila['id'],
        'id_docente' => (int) $fila['id_docente'],
        'periodo' => $fila['periodo'],
        'metas' => []
    ];
}
$stmt->close();

if ($planes) {
    $ids = array_column($planes, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));

    $sqlMetas = "SELECT id, id_plan, texto, estado FROM metas_desarrollo WHERE id_plan IN ($marcadores) ORDER BY id ASC";
    $stmt = preparar_consulta_planes($conexion, $sqlMetas);
    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $porPlan = [];
    while ($m = $resultado->fetch_assoc()) {
        $porPlan[(int) $m['id_plan']][] = normalizar_meta($m);
    }
    $stmt->close();

    foreach ($planes as &$p) {
        $p['metas'] = $porPlan[$p['id']] ?? [];
    }
}

$conexion->close();

responder_json([
    'ok' => true,
    'planes' => $planes
]);

?>
