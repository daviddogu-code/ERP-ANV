<?php

/**
 * @file
 * Dibuja isometric-supplier-orders-100.png como los iconos de 2024: 100x100,
 * bloques, transparente. Una carpeta de pedidos, distinta del portapapeles de
 * PO CONTROL: esta pantalla es el archivo, no la cola.
 *
 *   php scripts/dibujar-icono-supplier-orders.php
 */

$destino = dirname(__DIR__) . '/sites/default/private/2026-08/isometric-supplier-orders-100.png';
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

$ox = 46.0 * $escala;
$oy = 72.0 * $escala;
$s = 7.4 * $escala;

$carpeta = [color($im, '3EC4B4'), color($im, '2A9A8C'), color($im, '1C7A6E')];
$pestaña = [color($im, '5ED4C4'), color($im, '3AA898'), color($im, '2A8878')];
$papel = [color($im, 'FFF8E8'), color($im, 'E8D9B8'), color($im, 'D4C4A0')];
$papel2 = [color($im, 'F4E8D0'), color($im, 'DCC8A8'), color($im, 'C4B090')];
$cabecera = [color($im, '2A9A8C'), color($im, '1C7A6E'), color($im, '146058')];
$linea = [color($im, '7AD4C8'), color($im, '4AA898'), color($im, '2A8878')];

// Cuerpo de la carpeta.
caja($im, 0.0, 0.0, 0.0, 5.4, 3.8, 1.15, $carpeta, $ox, $oy, $s);

// Pestaña, atras a la izquierda.
caja($im, 0.0, 0.15, 1.15, 2.1, 3.5, 0.55, $pestaña, $ox, $oy, $s);

// Tres hojas apiladas. La de delante lleva el listado encima, no dentro.
caja($im, 0.45, 0.40, 1.70, 4.50, 3.05, 0.18, $papel2, $ox, $oy, $s);
caja($im, 0.65, 0.30, 1.88, 4.50, 3.05, 0.18, $papel, $ox, $oy, $s);
caja($im, 0.85, 0.20, 2.06, 4.40, 3.00, 0.18, $papel, $ox, $oy, $s);

$encima = 2.24;
caja($im, 1.10, 2.35, $encima, 3.90, 0.55, 0.16, $cabecera, $ox, $oy, $s);
foreach ([1.70, 1.15, 0.60] as $yLinea) {
  caja($im, 1.20, $yLinea, $encima, 3.50, 0.28, 0.12, $linea, $ox, $oy, $s);
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
