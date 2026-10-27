$ErrorActionPreference = 'Stop'
$workspacePath = [IO.Path]::GetFullPath($PSScriptRoot)
$removedFiles = [Collections.Generic.List[object]]::new()
function Remove-VerifiedFile([string]$relativePath) {
    $targetPath = [IO.Path]::GetFullPath((Join-Path $workspacePath $relativePath))
    if (-not $targetPath.StartsWith($workspacePath + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Destino fuera del proyecto.' }
    if (-not (Test-Path -LiteralPath $targetPath)) { return }
    $item = Get-Item -LiteralPath $targetPath
    if ($item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw "Se esperaba un archivo normal: $relativePath" }
    $removedFiles.Add([PSCustomObject]@{path=$relativePath; bytes=$item.Length; sha256=(Get-FileHash -LiteralPath $targetPath -Algorithm SHA256).Hash})
    Remove-Item -LiteralPath $targetPath
}
$duplicates = @{
    'audit-dependencies.json'='auditoria/2026-10-26/dependencies.json'
    'audit-original-junit.xml'='auditoria/2026-10-26/suite-original.xml'
    'audit-original-tests.txt'='auditoria/2026-10-26/suite-original.txt'
}
foreach ($source in $duplicates.Keys) {
    if (Test-Path -LiteralPath (Join-Path $workspacePath $source)) {
        $a=(Get-FileHash -LiteralPath (Join-Path $workspacePath $source)).Hash
        $b=(Get-FileHash -LiteralPath (Join-Path $workspacePath $duplicates[$source])).Hash
        if ($a -ne $b) { throw "Las copias difieren: $source" }
        Remove-VerifiedFile $source
    }
}
if ((Test-Path -LiteralPath (Join-Path $workspacePath '.runtime/php/php.exe')) -and (Test-Path -LiteralPath (Join-Path $workspacePath '.runtime/pgsql/bin/postgres.exe'))) {
    Remove-VerifiedFile '.runtime/php.zip'
    Remove-VerifiedFile '.runtime/postgres.zip'
    Remove-VerifiedFile 'php-releases.json'
}
$unused = @(
    'diente-feliz/resources/views/welcome.blade.php',
    'diente-feliz/resources/css/app.css',
    'diente-feliz/resources/js/app.js',
    'diente-feliz/package.json',
    'diente-feliz/vite.config.js',
    'diente-feliz/.npmrc',
    'diente-feliz/.phpunit.result.cache'
)
foreach ($file in $unused) { Remove-VerifiedFile $file }
$sqlitePath=Join-Path $workspacePath 'diente-feliz/database/database.sqlite'
if ((Test-Path -LiteralPath $sqlitePath) -and (Get-Item -LiteralPath $sqlitePath).Length -eq 0) { Remove-VerifiedFile 'diente-feliz/database/database.sqlite' }
# No recursive deletion: only generated PHP views in the verified cache folder.
$viewCache=Join-Path $workspacePath 'diente-feliz/storage/framework/views'
if (Test-Path -LiteralPath $viewCache) {
    foreach ($file in Get-ChildItem -LiteralPath $viewCache -File -Filter '*.php') {
        Remove-VerifiedFile ([IO.Path]::GetRelativePath($workspacePath,$file.FullName))
    }
}
$reportPath=Join-Path $workspacePath 'LIMPIEZA-2026-10-27.json'
if (-not (Test-Path -LiteralPath $reportPath)) { $removedFiles | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath $reportPath }
Write-Output ('Archivos eliminados: '+$removedFiles.Count)
Write-Output ('Bytes liberados: '+(($removedFiles | Measure-Object -Property bytes -Sum).Sum))
