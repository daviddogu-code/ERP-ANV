# Comprueba que todos los parches de composer.json estan de verdad en el codigo.
#
#   powershell -File scripts\estan-los-parches.ps1
#
# Un parche esta puesto si se puede deshacer. Eso es lo que mide esto, en vez de
# buscar textos sueltos, que se equivoca en cuanto el modulo mueve una linea.
#
# Hace falta porque un parche puede dejar de aplicar al subir el modulo, y hay
# dos formas de que eso pase desapercibido: que Composer lo salte con un aviso
# entre cientos de lineas, o que el arreglo ya venga incluido de serie y el
# parche sobre. La primera deja un fallo suelto en produccion; la segunda deja
# basura en el repositorio. Las dos se ven aqui.
#
# Solo lee.

$ErrorActionPreference = 'Stop'
$env:PATH = "C:\laragon\bin\git\cmd;C:\laragon\bin\git\usr\bin;" + $env:PATH

$raiz = Split-Path -Parent $PSScriptRoot
$json = Get-Content (Join-Path $raiz 'composer.json') -Raw | ConvertFrom-Json

$carpetas = @{}
foreach ($tipo in @('modules\contrib', 'themes\contrib', 'profiles\contrib')) {
  Get-ChildItem (Join-Path $raiz $tipo) -Directory -ErrorAction SilentlyContinue |
    ForEach-Object { $carpetas["drupal/$($_.Name)"] = $_.FullName }
}

$bien = 0
$mal = 0
$sobran = 0

Write-Host ""
foreach ($paquete in $json.extra.patches.PSObject.Properties) {
  $destino = $carpetas[$paquete.Name]
  if (-not $destino) {
    Write-Host ("  {0,-34} la carpeta del modulo no esta" -f $paquete.Name)
    $mal++
    continue
  }

  Write-Host ("  {0}" -f $paquete.Name)
  foreach ($parche in $paquete.Value.PSObject.Properties) {
    $ruta = Join-Path $raiz $parche.Value
    if (-not (Test-Path $ruta)) {
      Write-Host ("      FALTA EL FICHERO  {0}" -f $parche.Value)
      $mal++
      continue
    }

    # Si se puede deshacer, es que esta puesto.
    git -C $destino apply --reverse --check --unsafe-paths --directory=. $ruta 2>$null
    if ($LASTEXITCODE -eq 0) {
      Write-Host ("      puesto            {0}" -f $parche.Name)
      $bien++
      continue
    }

    # Si no se puede deshacer pero tampoco se puede aplicar, el arreglo ya viene
    # de serie o el fichero ha cambiado tanto que el parche ya no encaja.
    git -C $destino apply --check --unsafe-paths --directory=. $ruta 2>$null
    if ($LASTEXITCODE -eq 0) {
      Write-Host ("      NO ESTA PUESTO    {0}" -f $parche.Name)
      $mal++
    }
    else {
      Write-Host ("      ya no encaja      {0}" -f $parche.Name)
      Write-Host  "                        (mirar si el arreglo ya viene incluido; si es asi, quitarlo de composer.json)"
      $sobran++
    }
  }
}

Write-Host ""
Write-Host ("  {0} puestos, {1} sin poner, {2} que ya no encajan" -f $bien, $mal, $sobran)
Write-Host ""
if ($mal -gt 0 -or $sobran -gt 0) { exit 1 }
