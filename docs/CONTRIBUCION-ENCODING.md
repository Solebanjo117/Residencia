# Guia de encoding para contribucion

## Regla base
- Todo archivo de codigo y documentacion debe guardarse en UTF-8 sin BOM.
- Mantener fin de linea LF.
- No introducir caracteres corruptos (mojibake) como secuencias tipo `Ã`, `Â` o `ï»¿`.

## Configuracion del repositorio
- El archivo `.editorconfig` ya define:
  - `charset = utf-8`
  - `end_of_line = lf`

## Verificacion rapida en PowerShell
1. Buscar archivos con BOM:
```powershell
$paths = @('app','bootstrap','config','database','docs','resources','routes','tests')
Get-ChildItem -Path $paths -Recurse -File | ForEach-Object {
  $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
  if ($bytes.Length -ge 3 -and $bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191) {
    $_.FullName
  }
}
```

2. Buscar secuencias comunes de texto corrupto:
```powershell
$paths = @('app','resources','routes','docs')
Get-ChildItem -Path $paths -Recurse -File |
  Select-String -Pattern 'Ã|Â|â€™|â€œ|â€|ï»¿' |
  Select-Object Path, LineNumber, Line
```

## Correccion de BOM en archivos detectados
```powershell
$files = @('ruta/archivo1','ruta/archivo2')
$enc = New-Object System.Text.UTF8Encoding($false)
foreach ($f in $files) {
  $text = [System.IO.File]::ReadAllText($f)
  [System.IO.File]::WriteAllText($f, $text, $enc)
}
```
