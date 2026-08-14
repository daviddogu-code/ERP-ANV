# Pasa a UTF-8 sin marca de orden los ficheros que se hayan guardado en UTF-16.
#
# El editor de este puesto guarda a veces en UTF-16LE sin marca de orden, y en
# ese caso PHP no reconoce ni la etiqueta de apertura: en lugar de ejecutar el
# script, lo escupe por pantalla. Como no hay marca de orden, los conversores
# automaticos tampoco lo detectan y hay que decirles la codificacion a mano.
#
# Uso: .\scripts\a-utf8.ps1 scripts\mi-script.php [mas ficheros...]
#      .\scripts\a-utf8.ps1 scripts\*.php

param(
  [Parameter(Mandatory = $true, ValueFromRemainingArguments = $true)]
  [string[]]$Ficheros
)

$convertidos = 0
$intactos = 0

foreach ($patron in $Ficheros) {
  foreach ($fichero in (Get-ChildItem -Path $patron -File)) {
    $ruta = $fichero.FullName
    $bytes = [System.IO.File]::ReadAllBytes($ruta)

    # Un fichero de texto en UTF-16LE tiene un cero en el segundo byte, porque
    # el primer caracter es ASCII y se guarda en dos bytes.
    if ($bytes.Length -gt 1 -and $bytes[1] -eq 0) {
      $texto = [System.Text.Encoding]::Unicode.GetString($bytes)
      $texto = $texto -replace "^\uFEFF", ""
      [System.IO.File]::WriteAllText($ruta, $texto, (New-Object System.Text.UTF8Encoding $false))
      Write-Host "  convertido  $($fichero.Name)"
      $convertidos++
    }
    else {
      $intactos++
    }
  }
}

Write-Host ""
Write-Host "Convertidos: $convertidos. Ya estaban bien: $intactos."
