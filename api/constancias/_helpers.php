<?php

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../conexion.php';

$conexion->set_charset('utf8mb4');

function preparar_consulta_constancias(mysqli $conexion, string $sql): mysqli_stmt
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

function codigo_constancia(int $idDocente, int $idInscripcion): string
{
    return 'CERTUS-DC-' . str_pad((string) $idDocente, 4, '0', STR_PAD_LEFT)
        . '-' . str_pad((string) $idInscripcion, 6, '0', STR_PAD_LEFT);
}

function normalizar_constancia(array $fila): array
{
    $idDocente = (int) $fila['docente_id'];
    $idInscripcion = (int) $fila['inscripcion_id'];
    $docente = trim($fila['docente_nombres'] . ' ' . $fila['docente_apellidos']);

    return [
        'id_inscripcion' => $idInscripcion,
        'codigo' => codigo_constancia($idDocente, $idInscripcion),
        'docente' => $docente,
        'capacitacion' => $fila['capacitacion_titulo'],
        'duracion' => $fila['capacitacion_duracion'],
        'sede' => $fila['capacitacion_sede'],
        'programa' => $fila['capacitacion_programa'],
        'fecha_inscripcion' => $fila['fecha_inscripcion'],
        'fecha_limite' => $fila['fecha_limite'],
        'fecha_finalizacion' => $fila['fecha_finalizacion']
    ];
}

function obtener_constancia_docente(mysqli $conexion, int $idDocente, int $idInscripcion): ?array
{
    $sql = "SELECT
                i.id AS inscripcion_id,
                i.fecha_inscripcion,
                i.fecha_limite,
                DATE(i.fecha_actualizacion) AS fecha_finalizacion,
                u.id AS docente_id,
                u.nombres AS docente_nombres,
                u.apellidos AS docente_apellidos,
                c.titulo AS capacitacion_titulo,
                c.duracion AS capacitacion_duracion,
                c.sede AS capacitacion_sede,
                c.programa AS capacitacion_programa
            FROM inscripciones i
            INNER JOIN usuarios u ON u.id = i.id_docente
            INNER JOIN capacitaciones c ON c.id = i.id_capacitacion
            WHERE i.id = ?
              AND i.id_docente = ?
              AND i.estado = 'completada'
              AND u.rol = 'docente'
            LIMIT 1";

    $stmt = preparar_consulta_constancias($conexion, $sql);
    $stmt->bind_param('ii', $idInscripcion, $idDocente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila ? normalizar_constancia($fila) : null;
}

function fecha_constancia(?string $fecha): string
{
    if (!$fecha) {
        return '-';
    }

    $timestamp = strtotime($fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : $fecha;
}

function fecha_constancia_larga(?string $fecha): string
{
    if (!$fecha) {
        return '-';
    }

    $timestamp = strtotime($fecha);
    if (!$timestamp) {
        return $fecha;
    }

    $meses = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    ];

    return date('j', $timestamp) . ' de ' . $meses[(int) date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
}

function duracion_academica_constancia(string $duracion): string
{
    $duracion = trim($duracion);

    if ($duracion === '') {
        return '-';
    }

    if (stripos($duracion, 'academ') !== false) {
        return $duracion;
    }

    return $duracion . ' académicas';
}

function numero_pdf(float $numero): string
{
    return rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');
}

function texto_pdf_cp1252(string $texto): string
{
    $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
    return $convertido === false ? $texto : $convertido;
}

function escapar_texto_pdf(string $texto): string
{
    $texto = texto_pdf_cp1252($texto);
    $texto = str_replace('\\', '\\\\', $texto);
    $texto = str_replace('(', '\\(', $texto);
    $texto = str_replace(')', '\\)', $texto);
    return str_replace(["\r", "\n"], ' ', $texto);
}

function agregar_texto_pdf(array &$ops, string $texto, float $x, float $y, int $tamano, string $fuente = 'F1', string $alineacion = 'left', ?string $color = null): void
{
    $textoConvertido = texto_pdf_cp1252($texto);
    $anchoEstimado = strlen($textoConvertido) * $tamano * 0.48;

    if ($alineacion === 'center') {
        $x -= $anchoEstimado / 2;
    } elseif ($alineacion === 'right') {
        $x -= $anchoEstimado;
    }

    if ($color !== null) {
        $ops[] = $color . ' rg';
    }

    $ops[] = 'BT /' . $fuente . ' ' . $tamano . ' Tf 1 0 0 1 '
        . numero_pdf($x) . ' ' . numero_pdf($y) . ' Tm ('
        . escapar_texto_pdf($texto) . ') Tj ET';
}

function ancho_estimado_pdf(string $texto, int $tamano): float
{
    return strlen(texto_pdf_cp1252($texto)) * $tamano * 0.48;
}

function tamano_texto_ajustado_pdf(string $texto, int $tamanoMaximo, int $tamanoMinimo, float $anchoMaximo): int
{
    for ($tamano = $tamanoMaximo; $tamano >= $tamanoMinimo; $tamano--) {
        if (ancho_estimado_pdf($texto, $tamano) <= $anchoMaximo) {
            return $tamano;
        }
    }

    return $tamanoMinimo;
}

function lineas_pdf(string $texto, int $tamano, float $anchoMaximo, int $maximoLineas = 0): array
{
    $palabras = preg_split('/\s+/', trim($texto));
    $lineas = [];
    $linea = '';

    foreach ($palabras as $palabra) {
        if ($palabra === '') {
            continue;
        }

        $candidata = $linea === '' ? $palabra : $linea . ' ' . $palabra;

        if ($linea !== '' && ancho_estimado_pdf($candidata, $tamano) > $anchoMaximo) {
            $lineas[] = $linea;
            $linea = $palabra;

            if ($maximoLineas > 0 && count($lineas) >= $maximoLineas) {
                break;
            }
        } else {
            $linea = $candidata;
        }
    }

    if ($linea !== '' && ($maximoLineas === 0 || count($lineas) < $maximoLineas)) {
        $lineas[] = $linea;
    }

    if ($maximoLineas > 0 && count($lineas) > $maximoLineas) {
        $lineas = array_slice($lineas, 0, $maximoLineas);
    }

    return $lineas ?: [''];
}

function agregar_lineas_centradas_pdf(array &$ops, string $texto, float $x, float $y, int $tamano, int $maximoCaracteres, string $fuente = 'F1'): void
{
    $lineas = explode("\n", wordwrap($texto, $maximoCaracteres, "\n", false));

    foreach ($lineas as $indice => $linea) {
        agregar_texto_pdf($ops, trim($linea), $x, $y - ($indice * ($tamano + 8)), $tamano, $fuente, 'center');
    }
}

function agregar_parrafo_centrado_pdf(array &$ops, string $texto, float $x, float $y, float $anchoMaximo, int $tamano, int $altoLinea, string $fuente = 'F1', int $maximoLineas = 0): int
{
    $lineas = lineas_pdf($texto, $tamano, $anchoMaximo, $maximoLineas);

    foreach ($lineas as $indice => $linea) {
        agregar_texto_pdf($ops, $linea, $x, $y - ($indice * $altoLinea), $tamano, $fuente, 'center');
    }

    return count($lineas);
}

function agregar_elipse_pdf(array &$ops, float $cx, float $cy, float $rx, float $ry, string $color, bool $relleno = true): void
{
    $k = 0.5522847498;
    $ops[] = $color . ($relleno ? ' rg' : ' RG');
    $ops[] = numero_pdf($cx - $rx) . ' ' . numero_pdf($cy) . ' m '
        . numero_pdf($cx - $rx) . ' ' . numero_pdf($cy + ($k * $ry)) . ' '
        . numero_pdf($cx - ($k * $rx)) . ' ' . numero_pdf($cy + $ry) . ' '
        . numero_pdf($cx) . ' ' . numero_pdf($cy + $ry) . ' c '
        . numero_pdf($cx + ($k * $rx)) . ' ' . numero_pdf($cy + $ry) . ' '
        . numero_pdf($cx + $rx) . ' ' . numero_pdf($cy + ($k * $ry)) . ' '
        . numero_pdf($cx + $rx) . ' ' . numero_pdf($cy) . ' c '
        . numero_pdf($cx + $rx) . ' ' . numero_pdf($cy - ($k * $ry)) . ' '
        . numero_pdf($cx + ($k * $rx)) . ' ' . numero_pdf($cy - $ry) . ' '
        . numero_pdf($cx) . ' ' . numero_pdf($cy - $ry) . ' c '
        . numero_pdf($cx - ($k * $rx)) . ' ' . numero_pdf($cy - $ry) . ' '
        . numero_pdf($cx - $rx) . ' ' . numero_pdf($cy - ($k * $ry)) . ' '
        . numero_pdf($cx - $rx) . ' ' . numero_pdf($cy) . ' c ' . ($relleno ? 'f' : 'S');
}

function agregar_capsula_pdf(array &$ops, float $x, float $y, float $w, float $h, string $color): void
{
    $r = $h / 2;
    $k = 0.5522847498;
    $ops[] = $color . ' rg';
    $ops[] = numero_pdf($x + $r) . ' ' . numero_pdf($y) . ' m '
        . numero_pdf($x + $w - $r) . ' ' . numero_pdf($y) . ' l '
        . numero_pdf($x + $w - $r + ($k * $r)) . ' ' . numero_pdf($y) . ' '
        . numero_pdf($x + $w) . ' ' . numero_pdf($y + $r - ($k * $r)) . ' '
        . numero_pdf($x + $w) . ' ' . numero_pdf($y + $r) . ' c '
        . numero_pdf($x + $w) . ' ' . numero_pdf($y + $r + ($k * $r)) . ' '
        . numero_pdf($x + $w - $r + ($k * $r)) . ' ' . numero_pdf($y + $h) . ' '
        . numero_pdf($x + $w - $r) . ' ' . numero_pdf($y + $h) . ' c '
        . numero_pdf($x + $r) . ' ' . numero_pdf($y + $h) . ' l '
        . numero_pdf($x + $r - ($k * $r)) . ' ' . numero_pdf($y + $h) . ' '
        . numero_pdf($x) . ' ' . numero_pdf($y + $r + ($k * $r)) . ' '
        . numero_pdf($x) . ' ' . numero_pdf($y + $r) . ' c '
        . numero_pdf($x) . ' ' . numero_pdf($y + $r - ($k * $r)) . ' '
        . numero_pdf($x + $r - ($k * $r)) . ' ' . numero_pdf($y) . ' '
        . numero_pdf($x + $r) . ' ' . numero_pdf($y) . ' c f';
}

function generar_pdf_constancia(array $constancia): string
{
    $ops = [];
    $fechaFinalizacion = fecha_constancia_larga($constancia['fecha_finalizacion']);
    $duracionAcademica = duracion_academica_constancia($constancia['duracion']);
    $tamanoDocente = tamano_texto_ajustado_pdf($constancia['docente'], 29, 20, 390);
    $tamanoCapacitacion = tamano_texto_ajustado_pdf($constancia['capacitacion'], 17, 13, 500);
    $lineasCapacitacion = lineas_pdf($constancia['capacitacion'], $tamanoCapacitacion, 520, 2);
    $altoCapacitacion = count($lineasCapacitacion) > 1 ? 17 : 0;

    $ops[] = 'q';
    $ops[] = '1 1 1 rg 0 0 842 595 re f';

    $ops[] = '0.02 0.16 0.44 RG 1.2 w 10 10 822 575 re S';
    $ops[] = '0.02 0.13 0.34 rg 0 0 108 595 re f';
    agregar_elipse_pdf($ops, -38, 498, 88, 110, '0.01 0.07 0.20');
    agregar_elipse_pdf($ops, 14, 390, 82, 108, '0.02 0.20 0.55');
    agregar_elipse_pdf($ops, -20, 298, 96, 138, '0.00 0.35 0.82');
    agregar_elipse_pdf($ops, 28, 210, 82, 130, '0.02 0.18 0.48');
    $ops[] = '1 1 1 rg 108 0 m 130 0 116 68 108 94 c 94 133 94 456 108 506 c 117 540 128 595 128 595 c 108 595 l f';

    agregar_texto_pdf($ops, 'PERSONAS', 26, 330, 7, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'QUE', 26, 318, 7, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'INSPIRAN', 26, 306, 7, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'EL CAMBIO', 26, 294, 7, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'CERTUS', 24, 44, 9, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'certus.edu.pe', 24, 31, 6, 'F1', 'left', '1 1 1');

    agregar_elipse_pdf($ops, 780, 345, 126, 146, '0.93 0.96 0.98');
    agregar_elipse_pdf($ops, 805, 345, 83, 103, '1 1 1');
    $ops[] = '0.02 0.18 0.55 rg 792 15 m 832 15 l 832 92 l 805 73 l 792 42 l 792 15 l f';
    agregar_texto_pdf($ops, 'Aprende.', 803, 54, 7, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'Practica.', 803, 43, 7, 'F2', 'left', '1 1 1');
    agregar_texto_pdf($ops, 'Avanza.', 803, 32, 7, 'F2', 'left', '1 1 1');

    agregar_capsula_pdf($ops, 132, 515, 145, 32, '0.02 0.13 0.34');
    agregar_texto_pdf($ops, 'CERTUS', 204.5, 524, 19, 'F2', 'center', '1 1 1');
    $ops[] = '0.02 0.13 0.34 rg';
    agregar_texto_pdf($ops, 'Potencia tu futuro', 204.5, 505, 8, 'F2', 'center');
    agregar_texto_pdf($ops, 'INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PRIVADO', 421, 490, 7, 'F2', 'center');
    agregar_texto_pdf($ops, 'CERTUS', 421, 479, 7, 'F2', 'center');
    agregar_texto_pdf($ops, 'EDUCACIÓN', 681, 536, 7, 'F2');
    agregar_texto_pdf($ops, 'PARA UN MUNDO', 681, 524, 7, 'F2');
    agregar_texto_pdf($ops, 'EN EVOLUCIÓN', 681, 512, 7, 'F2');

    agregar_texto_pdf($ops, 'CONSTANCIA DE FINALIZACIÓN', 452, 435, 30, 'F2', 'center');
    agregar_texto_pdf($ops, 'Se otorga la presente constancia a:', 452, 403, 9, 'F1', 'center');
    agregar_texto_pdf($ops, $constancia['docente'], 452, 369, $tamanoDocente, 'F2', 'center');
    $ops[] = '0.12 0.42 0.75 RG 1 w 250 353 m 654 353 l S';
    agregar_texto_pdf($ops, 'Por haber completado satisfactoriamente la capacitación docente:', 452, 335, 9, 'F1', 'center');

    foreach ($lineasCapacitacion as $indice => $linea) {
        agregar_texto_pdf($ops, $linea, 452, 309 - ($indice * 18), $tamanoCapacitacion, 'F2', 'center');
    }

    $descripcionY = 283 - $altoCapacitacion;
    agregar_parrafo_centrado_pdf(
        $ops,
        'Desarrollada por el Instituto CERTUS, en el marco de su programa de formación docente, con una duración de ' . $duracionAcademica . '.',
        452,
        $descripcionY,
        500,
        9,
        12,
        'F1',
        2
    );

    $ops[] = '0.76 0.84 0.92 RG 0.8 w 268 214 m 268 248 l S';
    $ops[] = '0.76 0.84 0.92 RG 0.8 w 461 214 m 461 248 l S';

    $ops[] = '0.02 0.18 0.55 RG 1.5 w 148 230 14 12 re S';
    $ops[] = '0.02 0.18 0.55 RG 1.5 w 150 245 m 160 245 l S';
    agregar_texto_pdf($ops, 'FECHA DE FINALIZACIÓN', 174, 239, 7, 'F2');
    agregar_texto_pdf($ops, $fechaFinalizacion, 174, 225, 8, 'F1');

    agregar_elipse_pdf($ops, 303, 235, 8, 8, '0.02 0.18 0.55', false);
    $ops[] = '0.02 0.18 0.55 RG 1 w 303 235 m 303 241 l S';
    $ops[] = '0.02 0.18 0.55 RG 1 w 303 235 m 308 235 l S';
    agregar_texto_pdf($ops, 'DURACIÓN', 321, 239, 7, 'F2');
    agregar_texto_pdf($ops, $duracionAcademica, 321, 225, 8, 'F1');

    $ops[] = '0.02 0.18 0.55 RG 1.4 w 497 225 12 18 re S';
    $ops[] = '0.02 0.18 0.55 RG 0.8 w 500 237 m 506 237 l S 500 232 m 506 232 l S';
    agregar_texto_pdf($ops, 'CÓDIGO DE CONSTANCIA', 519, 239, 7, 'F2');
    agregar_texto_pdf($ops, $constancia['codigo'], 519, 225, 8, 'F1');

    agregar_texto_pdf($ops, 'Lima, ' . $fechaFinalizacion, 452, 185, 9, 'F1', 'center');

    $ops[] = '0.02 0.18 0.55 RG 1 w 220 121 m 365 121 l S';
    agregar_texto_pdf($ops, 'Dirección Académica', 292.5, 101, 8, 'F2', 'center');
    agregar_texto_pdf($ops, 'Instituto CERTUS', 292.5, 89, 7, 'F1', 'center');

    $ops[] = '0.02 0.18 0.55 RG 1 w 485 121 m 647 121 l S';
    agregar_texto_pdf($ops, 'Coordinación de Formación Docente', 566, 101, 8, 'F2', 'center');
    agregar_texto_pdf($ops, 'Instituto CERTUS', 566, 89, 7, 'F1', 'center');

    agregar_texto_pdf($ops, 'Código de verificación:', 700, 112, 7, 'F2', 'center');
    agregar_texto_pdf($ops, $constancia['codigo'], 700, 99, 6, 'F1', 'center');
    agregar_texto_pdf($ops, 'Verifica la validez', 700, 81, 6, 'F1', 'center');
    agregar_texto_pdf($ops, 'de esta constancia', 700, 72, 6, 'F1', 'center');
    $ops[] = 'Q';

    $contenido = implode("\n", $ops) . "\n";

    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R >> >> /Contents 7 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>',
        7 => '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "endstream"
    ];

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];

    foreach ($objetos as $numero => $objeto) {
        $offsets[$numero] = strlen($pdf);
        $pdf .= $numero . " 0 obj\n" . $objeto . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objetos); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    return $pdf;
}

?>
