<#
  Copia de seguridad completa del ERP.

  Se ejecuta asi, desde la raiz del proyecto:
    powershell -ExecutionPolicy Bypass -File scripts\copia-de-seguridad.ps1 -Etiqueta antes-de-la-11

  Guarda cinco piezas, porque con menos no se puede volver atras del todo:

    bd.sql.gz          la base de datos entera
    arbol.tar.gz       el proyecto sin nucleo, sin vendor y sin ficheros subidos
    core-vendor.tar.gz el nucleo y las librerias de composer
    ficheros.tar.gz    lo que han subido los usuarios, publico y privado
    historia.tar.gz    el .git, con todos los commits

  La historia va aparte y no dentro del arbol porque son cosas distintas: el
  arbol es como esta el proyecto hoy, y la historia es como se llego hasta
  aqui. Se guarda aunque haya un GitHub detras, porque mientras haya commits
  sin subir el unico sitio donde existen es este disco.

  Al terminar comprueba que las cuatro existen y pesan lo que deberian. Una
  copia que no se ha comprobado no es una copia.
#>

param(
  [Parameter(Mandatory = $true)]
  [string]$Etiqueta
)

# A proposito no se para al primer aviso. Drush y tar escriben cosas por el
# canal de error que no son fallos, y pararse ahi dejaba la copia a medias
# fingiendo que habia un problema. Lo que decide si la copia vale es la
# comprobacion del final, no el ruido del camino.
$ErrorActionPreference = 'Continue'

$raiz = Split-Path -Parent $PSScriptRoot
$sello = Get-Date -Format 'yyyyMMdd-HHmmss'
$destino = Join-Path 'C:\laragon\backups' "$Etiqueta-$sello"

New-Item -ItemType Directory -Path $destino -Force | Out-Null

# mysqldump para el volcado y gzip para comprimirlo, que drush los busca en
# el PATH y aqui no estan de serie.
$env:PATH = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;C:\laragon\bin\git\usr\bin;' + $env:PATH

# El tar de Windows, no el de Git. El de Git es GNU tar y lee C:\algo como si
# C fuese un servidor remoto, asi que falla con cualquier ruta de Windows.
$tar = "$env:SystemRoot\System32\tar.exe"

Write-Host ''
Write-Host "  Copiando a $destino"
Write-Host ''

# --- 1. La base de datos --------------------------------------------------
# Tiene que ser drush.php y no drush. En Windows, `vendor/bin/drush` es un
# guion de shell (#!/usr/bin/env sh), asi que darselo a php no dumpea nada: lo
# escupe como texto y termina con exito aparente. Lo unico que lo delataba era
# la comprobacion del final. Paso el 13 de agosto de 2026 al subir Drush a la
# 13.7.6, y la copia del 14 salio sin base de datos por esto.
Write-Host '  1 de 5   la base de datos...'
Push-Location $raiz
php vendor\bin\drush.php sql:dump --gzip --result-file="$destino/bd.sql" 2>&1 | Out-Null
Pop-Location

# --- 2. El arbol del proyecto ---------------------------------------------
# Fuera lo que se puede reconstruir con composer y lo que va en otra pieza.
Write-Host '  2 de 5   el arbol del proyecto...'
& $tar -czf "$destino\arbol.tar.gz" -C $raiz `
  --exclude ./vendor `
  --exclude ./core `
  --exclude ./node_modules `
  --exclude ./sites/default/files `
  --exclude ./sites/default/private `
  --exclude ./.git `
  . 2>$null

# --- 3. El nucleo y las librerias -----------------------------------------
Write-Host '  3 de 5   el nucleo y vendor...'
& $tar -czf "$destino\core-vendor.tar.gz" -C $raiz core vendor 2>$null

# --- 4. Los ficheros subidos ----------------------------------------------
Write-Host '  4 de 5   los ficheros subidos...'
$carpetas = @()
foreach ($c in @('sites/default/files', 'sites/default/private')) {
  if (Test-Path (Join-Path $raiz $c)) {
    $carpetas += $c
  }
}
& $tar -czf "$destino\ficheros.tar.gz" -C $raiz $carpetas 2>$null

# --- 5. La historia de git ------------------------------------------------
Write-Host '  5 de 5   la historia de git...'
& $tar -czf "$destino\historia.tar.gz" -C $raiz .git 2>$null

# Y de paso se avisa si hay trabajo que solo existe aqui.
Push-Location $raiz
$sinSubir = (git rev-list --count '@{u}..HEAD' 2>$null)
Pop-Location
if ($sinSubir -and [int]$sinSubir -gt 0) {
  Write-Host ''
  Write-Host "  AVISO: hay $sinSubir commits sin subir a GitHub. Solo estan en este disco"
  Write-Host '         y en esta copia.'
}

# --- Y ahora se comprueba -------------------------------------------------
# Los minimos son holgados a proposito. No buscan medir con precision, sino
# cazar el caso de verdad peligroso: una pieza vacia que parece estar ahi.
$minimos = @{
  'bd.sql.gz'          = 3MB
  'arbol.tar.gz'       = 50MB
  'core-vendor.tar.gz' = 50MB
  'ficheros.tar.gz'    = 10MB
  'historia.tar.gz'    = 10MB
}

Write-Host ''
Write-Host '  pieza                       tamano        estado'
Write-Host '  ----------------------------------------------------------'

$fallos = 0
foreach ($pieza in @('bd.sql.gz', 'arbol.tar.gz', 'core-vendor.tar.gz', 'ficheros.tar.gz', 'historia.tar.gz')) {
  $ruta = Join-Path $destino $pieza

  if (-not (Test-Path $ruta)) {
    Write-Host ('  {0,-26} {1,10}    NO SE CREO' -f $pieza, '-')
    $fallos++
    continue
  }

  $peso = (Get-Item $ruta).Length
  $minimo = $minimos[$pieza]

  if ($peso -lt $minimo) {
    Write-Host ('  {0,-26} {1,7:N1} MB    DEMASIADO PEQUENA, minimo {2:N0} MB' -f $pieza, ($peso / 1MB), ($minimo / 1MB))
    $fallos++
  }
  else {
    Write-Host ('  {0,-26} {1,7:N1} MB    bien' -f $pieza, ($peso / 1MB))
  }
}

Write-Host ''
if ($fallos) {
  Write-Host "  $fallos piezas mal. NO se puede seguir con esta copia."
  Write-Host ''
  exit 1
}

Write-Host '  Las cinco piezas estan y pesan lo que deberian.'
Write-Host ''
