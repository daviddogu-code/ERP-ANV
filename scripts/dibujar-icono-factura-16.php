<?php

/**
 * @file
 * Dibuja 000-invoice-16.png, el icono de descarga de factura en las listas.
 *
 *   php scripts/dibujar-icono-factura-16.php
 */

$destino = dirname(__DIR__) . '/sites/default/files/000-invoice-16.png';
$escala = 4;
$lienzo = 16 * $escala;

$im = imagecreatetruecolor($lienzo, $lienzo);
imagealphablending($im, FALSE);
imagesavealpha($im, TRUE);
$trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefilledrectangle($im, 0, 0, $lienzo - 1, $lienzo - 1, $trans);
imagealphablending($im, TRUE);

$tinta = imagecolorallocate($im, 70, 70, 70);
$papel = imagecolorallocate($im, 255, 255, 255);
$linea = imagecolorallocate($im, 120, 120, 120);

$s = $escala;
// Hoja.
imagefilledrectangle($im, 3 * $s, 1 * $s, 12 * $s, 14 * $s, $papel);
imagerectangle($im, 3 * $s, 1 * $s, 12 * $s, 14 * $s, $tinta);
// Esquina doblada.
$fold = [
  8 * $s, 1 * $s,
  12 * $s, 1 * $s,
  12 * $s, 5 * $s,
];
imagefilledpolygon($im, $fold, $tinta);
imageline($im, 8 * $s, 1 * $s, 8 * $s, 5 * $s, $papel);
imageline($im, 8 * $s, 5 * $s, 12 * $s, 5 * $s, $papel);
// Lineas del listado.
foreach ([7, 9, 11] as $y) {
  imagefilledrectangle($im, 5 * $s, $y * $s, 10 * $s, ($y * $s) + $s - 1, $linea);
}

$final = imagecreatetruecolor(16, 16);
imagealphablending($final, FALSE);
imagesavealpha($final, TRUE);
imagefilledrectangle($final, 0, 0, 15, 15, imagecolorallocatealpha($final, 0, 0, 0, 127));
imagealphablending($final, TRUE);
imagecopyresampled($final, $im, 0, 0, 0, 0, 16, 16, $lienzo, $lienzo);
imagedestroy($im);

imagepng($final, $destino, 9);
imagedestroy($final);

$info = getimagesize($destino);
printf("OK %s %dx%d %s KB\n", $destino, $info[0], $info[1], number_format(filesize($destino) / 1024, 1));
