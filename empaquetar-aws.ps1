$ErrorActionPreference = 'Stop'
$sourcePath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot 'diente-feliz-aws'))
$zipPath = Join-Path $PSScriptRoot 'diente-feliz-aws.zip'
if (Test-Path -LiteralPath $zipPath) { throw 'El ZIP ya existe. Consérvalo o renómbralo antes de generar otro.' }
$files = Get-ChildItem -LiteralPath $sourcePath -File -Recurse -Force
$manifest = foreach ($file in $files) {
    $relativePath = [IO.Path]::GetRelativePath($sourcePath,$file.FullName).Replace('\','/')
    if (($file.Attributes -band [IO.FileAttributes]::ReparsePoint) -or $relativePath -match '(^|/)(vendor|node_modules|pgdata|\.git)/|(^|/)\.env($|\.testing$)|\.(log|sql|dump|sqlite|pem)$|^storage/.+|^bootstrap/cache/.+') {
        throw "Archivo no permitido para distribución: $relativePath"
    }
    if ($relativePath -like '.env*' -and $relativePath -notin @('.env.example','.env.aws.example')) { throw 'Archivo de entorno privado detectado.' }
    if ($relativePath -ne 'MANIFEST-SHA256.json') { [PSCustomObject]@{path=$relativePath;sha256=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash} }
}
$manifest | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath (Join-Path $sourcePath 'MANIFEST-SHA256.json')
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory($sourcePath,$zipPath,[IO.Compression.CompressionLevel]::Optimal,$true)
Write-Output 'Paquete AWS creado sin entornos privados, datos, dependencias instaladas ni cachés.'
