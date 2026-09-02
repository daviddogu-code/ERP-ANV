<?php

/**
 * @file
 * Dibuja isometric-invoices-100.png como los iconos de 2024: 100x100, bloques,
 * transparente. Una ใบกำกับ de pie, distinta del portapapeles naranja de PO
 * CONTROL: esta pantalla es el libro 036x, no la cola de compras.
 *
 *   php scripts/dibujar-icono-invoices.php
 */

$destino = dirname(__DIR__) . '/sites/default/private/2026-09/isometric-invoices-100.png';
$escala = 4;
$lienzo = 100 * $escala;

function proyectar(float $x, float $y, float $z, float $ox, float $oy, float $s): array {
  return [
    (int) round($ox + ($x - $y) * $s),
    (int) round($oy + ($x + $y) * ($s / 2) - $z * $s),
  ];
}

function color(GdImage $im, string $hex): int {
  $hex = ltrim($hex, '#');
  return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function poligono(GdImage $im, array $puntos, int $c): void {
  $flat = [];
  foreach ($puntos as $p) {
    $flat[] = $p[0];
    $flat[] = $p[1];
  }
  imagefilledpolygon($im, $flat, $c);
}

function caja(GdImage $im, float $x, float $y, float $z, float $w, float $d, float $h, array $colores, float $ox, float $oy, float $s): void {
  $c000 = proyectar($x, $y, $z, $ox, $oy, $s);
  $c100 = proyectar($x + $w, $y, $z, $ox, $oy, $s);
  $c010 = proyectar($x, $y + $d, $z, $ox, $oy, $s);
  $c110 = proyectar($x + $w, $y + $d, $z, $ox, $oy, $s);
  $c001 = proyectar($x, $y, $z + $h, $ox, $oy, $s);
  $c101 = proyectar($x + $w, $y, $z + $h, $ox, $oy, $s);
  $c011 = proyectar($x, $y + $d, $z + $h, $ox, $oy, $s);
  $c111 = proyectar($x + $w, $y + $d, $z + $h, $ox, $oy, $s);

  poligono($im, [$c101, $c111, $c110, $c100], $colores[2]);
  poligono($im, [$c011, $c111, $c110, $c010], $colores[1]);
  poligono($im, [$c001, $c101, $c111, $c011], $colores[0]);
}

$im = imagecreatetruecolor($lienzo, $lienzo);
imagealphablending($im, FALSE);
imagesavealpha($im, TRUE);
$trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefilledrectangle($im, 0, 0, $lienzo - 1, $lienzo - 1, $trans);
imagealphablending($im, TRUE);

$ox = 48.0 * $escala;
$oy = 74.0 * $escala;
$s = 7.6 * $escala;

$atras = [color($im, 'E8D9B8'), color($im, 'C4B090'), color($im, 'A89070')];
$papel = [color($im, 'FFF8E8'), color($im, 'E8D9B8'), color($im, 'D4C4A0')];
$cabecera = [color($im, '3AA348'), color($im, '2A7A34'), color($im, '1C5A26')];
$linea = [color($im, 'C4B090'), color($im, 'A89070'), color($im, '8A7458')];
$sello = [color($im, 'E07070'), color($im, 'C04040'), color($im, '902828')];
$total = [color($im, '5ECF6A'), color($im, '3AA348'), color($im, '2A7A34')];

// Hoja de detras, un poco corrida, el libro no es un solo papel.
caja($im, -0.35, 0.45, 0.0, 4.6, 0.32, 7.5, $atras, $ox, $oy, $s);

// Hoja de delante.
caja($im, 0.0, 0.0, 0.0, 4.7, 0.38, 7.6, $papel, $ox, $oy, $s);

// Franja del titulo TAX INVOICE.
caja($im, 0.25, -0.02, 6.35, 4.20, 0.22, 0.95, $cabecera, $ox, $oy, $s);

// Lineas de importe.
caja($im, 0.45, -0.02, 5.05, 3.80, 0.18, 0.32, $linea, $ox, $oy, $s);
caja($im, 0.45, -0.02, 4.15, 3.15, 0.18, 0.32, $linea, $ox, $oy, $s);
caja($im, 0.45, -0.02, 3.25, 3.80, 0.18, 0.32, $linea, $ox, $oy, $s);

// Total.
caja($im, 0.45, -0.02, 1.85, 3.80, 0.20, 0.55, $total, $ox, $oy, $s);

// Sello, como el de la ใบกำกับ.
caja($im, 2.85, -0.12, 0.45, 1.45, 0.55, 1.10, $sello, $ox, $oy, $s);

$final = imagecreatetruecolor(100, 100);
imagealphablending($final, FALSE);
imagesavealpha($final, TRUE);
imagefilledrectangle($final, 0, 0, 99, 99, imagecolorallocatealpha($final, 0, 0, 0, 127));
imagealphablending($final, TRUE);
imagecopyresampled($final, $im, 0, 0, 0, 0, 100, 100, $lienzo, $lienzo);
imagedestroy($im);

if (!is_dir(dirname($destino))) {
  mkdir(dirname($destino), 0775, TRUE);
}

imagepng($final, $destino, 9);
imagedestroy($final);

$info = getimagesize($destino);
printf("OK %s %dx%d %s KB\n", $destino, $info[0], $info[1], number_format(filesize($destino) / 1024, 1));
