<?php

/**
 * @file
 * Dibuja isometric-patterns-100.png: piezas de pattern sobre mesa de corte.
 *
 *   php scripts/dibujar-icono-patterns.php
 */

$destino = dirname(__DIR__) . '/sites/default/private/2026-09/isometric-patterns-100.png';
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

$ox = 44.0 * $escala;
$oy = 70.0 * $escala;
$s = 7.8 * $escala;

$mesa = [color($im, '3A7A48'), color($im, '2A5C36'), color($im, '1C4428')];
$papel = [color($im, 'FFF4D8'), color($im, 'E8D4B0'), color($im, 'C8B490')];
$papel2 = [color($im, 'F5E8C8'), color($im, 'D8C4A0'), color($im, 'B8A480')];
$linea = color($im, 'C04040');
$alfiler = [color($im, 'E8E8E8'), color($im, 'A8A8A8'), color($im, '707070')];
$codigo = color($im, '5C4A32');

// Mesa de corte.
caja($im, 0.0, 0.0, 0.0, 5.4, 3.6, 0.55, $mesa, $ox, $oy, $s);

// Pieza de atras, corrida.
caja($im, 0.55, 0.75, 0.55, 3.8, 0.28, 4.8, $papel2, $ox, $oy, $s);

// Pieza de delante, el pattern 055.
caja($im, 0.15, 0.15, 0.55, 4.2, 0.32, 5.1, $papel, $ox, $oy, $s);

// Linea de corte punteada (trozos).
foreach ([1.35, 1.95, 2.55, 3.15, 3.75] as $z) {
  caja($im, 0.55, 0.05, $z, 3.4, 0.12, 0.18, [$linea, $linea, $linea], $ox, $oy, $s);
}

// Alfiler rojo en la esquina.
caja($im, 0.45, -0.05, 4.85, 0.35, 0.35, 0.55, $alfiler, $ox, $oy, $s);
caja($im, 0.52, 0.02, 5.35, 0.22, 0.22, 0.35, [color($im, 'E05050'), color($im, 'C03030'), color($im, '902020')], $ox, $oy, $s);

// 055 en la pieza (bloques finos).
caja($im, 1.05, 0.02, 2.15, 0.55, 0.14, 0.55, [$codigo, $codigo, $codigo], $ox, $oy, $s);
caja($im, 1.75, 0.02, 2.15, 0.55, 0.14, 0.55, [$codigo, $codigo, $codigo], $ox, $oy, $s);
caja($im, 2.45, 0.02, 2.15, 0.55, 0.14, 0.55, [$codigo, $codigo, $codigo], $ox, $oy, $s);

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
