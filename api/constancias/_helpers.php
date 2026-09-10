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

function agregar_texto_pdf(array &$ops, string $texto, float $x, float $y, int $tamano, string $fuente = 'F1', string $alineacion = 'left'): void
{
    $textoConvertido = texto_pdf_cp1252($texto);
    $anchoEstimado = strlen($textoConvertido) * $tamano * 0.48;

    if ($alineacion === 'center') {
        $x -= $anchoEstimado / 2;
    } elseif ($alineacion === 'right') {
        $x -= $anchoEstimado;
    }

    $ops[] = 'BT /' . $fuente . ' ' . $tamano . ' Tf 1 0 0 1 '
        . numero_pdf($x) . ' ' . numero_pdf($y) . ' Tm ('
        . escapar_texto_pdf($texto) . ') Tj ET';
}

function agregar_lineas_centradas_pdf(array &$ops, string $texto, float $x, float $y, int $tamano, int $maximoCaracteres, string $fuente = 'F1'): void
{
    $lineas = explode("\n", wordwrap($texto, $maximoCaracteres, "\n", false));

    foreach ($lineas as $indice => $linea) {
        agregar_texto_pdf($ops, trim($linea), $x, $y - ($indice * ($tamano + 8)), $tamano, $fuente, 'center');
    }
}

function generar_pdf_constancia(array $constancia): string
{
    $ops = [];

    $ops[] = 'q';
    $ops[] = '1 1 1 rg 0 0 842 595 re f';
    $ops[] = '0.02 0.32 0.27 RG 4 w 38 38 766 519 re S';
    $ops[] = '0.91 0.72 0.24 RG 1.5 w 52 52 738 491 re S';
    $ops[] = '0.02 0.32 0.27 rg 60 492 722 38 re f';
    $ops[] = '0.91 0.72 0.24 rg 60 78 722 4 re f';

    agregar_texto_pdf($ops, 'CERTUS', 421, 504, 26, 'F2', 'center');
    agregar_texto_pdf($ops, 'CONSTANCIA DE FINALIZACION', 421, 445, 28, 'F2', 'center');
    agregar_texto_pdf($ops, 'Se otorga la presente constancia a:', 421, 407, 14, 'F1', 'center');
    agregar_lineas_centradas_pdf($ops, $constancia['docente'], 421, 366, 26, 38, 'F2');
    agregar_texto_pdf($ops, 'por haber completado satisfactoriamente la capacitacion docente:', 421, 320, 13, 'F1', 'center');
    agregar_lineas_centradas_pdf($ops, $constancia['capacitacion'], 421, 285, 20, 55, 'F2');

    agregar_texto_pdf($ops, 'Duracion:', 190, 220, 12, 'F2');
    agregar_texto_pdf($ops, $constancia['duracion'], 260, 220, 12, 'F1');
    agregar_texto_pdf($ops, 'Fecha de finalizacion:', 190, 196, 12, 'F2');
    agregar_texto_pdf($ops, fecha_constancia($constancia['fecha_finalizacion']), 330, 196, 12, 'F1');
    agregar_texto_pdf($ops, 'Codigo:', 190, 172, 12, 'F2');
    agregar_texto_pdf($ops, $constancia['codigo'], 250, 172, 12, 'F1');

    $ops[] = '0.02 0.32 0.27 RG 1 w 550 158 170 0 m 720 158 l S';
    agregar_texto_pdf($ops, 'Direccion Academica', 635, 138, 11, 'F1', 'center');
    agregar_texto_pdf($ops, 'Instituto CERTUS', 635, 122, 12, 'F2', 'center');
    agregar_texto_pdf($ops, 'Documento generado desde el sistema de capacitacion docente CERTUS.', 421, 92, 9, 'F3', 'center');
    $ops[] = 'Q';

    $contenido = implode("\n", $ops) . "\n";

    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R >> >> /Contents 7 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>',
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
