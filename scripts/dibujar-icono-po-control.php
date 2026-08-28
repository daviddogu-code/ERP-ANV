<?php

/**
 * @file
 * Dibuja isometric-po-control-100.png como los iconos de 2024: 100x100, bloques, transparente.
 *
 *   php scripts/dibujar-icono-po-control.php
 */

$destino = dirname(__DIR__) . '/sites/default/private/2026-08/isometric-po-control-100.png';
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

$ox = 43.5 * $escala;
$oy = 68.0 * $escala;
$s = 8.0 * $escala;

$naranja = [color($im, 'F0B060'), color($im, 'D4893A'), color($im, 'B86A22')];
$papel = [color($im, 'FFF8E8'), color($im, 'E8D9B8'), color($im, 'D4C4A0')];
$clip = [color($im, 'D8DCE0'), color($im, '8A9098'), color($im, '5C636C')];
$marca = [color($im, '5ECF6A'), color($im, '3AA348'), color($im, '2A7A34')];
$vacio = [color($im, 'E8D9B8'), color($im, 'C4B090'), color($im, 'A89070')];
$linea = [color($im, 'E0A050'), color($im, 'C48438'), color($im, 'A06820')];

// Base, como el documento de Orders.
caja($im, 0.0, 0.0, 0.0, 5.2, 3.4, 0.7, $naranja, $ox, $oy, $s);

// Tablero vertical.
caja($im, 0.0, 2.3, 0.7, 5.2, 1.1, 8.4, $naranja, $ox, $oy, $s);

// Hoja.
caja($im, 0.35, 2.22, 1.15, 4.5, 0.35, 7.4, $papel, $ox, $oy, $s);

// Clip.
caja($im, 1.6, 1.85, 8.35, 2.0, 1.5, 1.15, $clip, $ox, $oy, $s);

// Casillas: arriba hecha, las otras vacias.
caja($im, 0.7, 2.12, 6.55, 0.85, 0.28, 0.85, $marca, $ox, $oy, $s);
caja($im, 0.7, 2.12, 4.55, 0.85, 0.28, 0.85, $vacio, $ox, $oy, $s);
caja($im, 0.7, 2.12, 2.55, 0.85, 0.28, 0.85, $vacio, $ox, $oy, $s);

foreach ([6.85, 4.85, 2.85] as $zLinea) {
  caja($im, 1.8, 2.12, $zLinea, 2.6, 0.2, 0.28, $linea, $ox, $oy, $s);
}

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
