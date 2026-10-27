$ErrorActionPreference = 'Stop'
$workspacePath = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$phpPath = Join-Path $workspacePath '.runtime/php/php.exe'
$testPath = Join-Path $workspacePath 'diente-feliz/tests/Feature/ClinicSecurityTest.php'
$runnerPath = Join-Path $workspacePath 'diente-feliz/vendor/phpunit/phpunit/phpunit'
$configPath = Join-Path $workspacePath 'diente-feliz/phpunit.xml'
$testEnv = Get-Content (Join-Path $workspacePath 'diente-feliz/.env.testing')
if (-not ($testEnv -contains 'DB_DATABASE=diente_feliz_test')) { throw 'Se requiere la base separada diente_feliz_test.' }
$testNames = [regex]::Matches((Get-Content $testPath -Raw), 'function (test_[a-z_]+)\(')
foreach ($match in $testNames) {
    $name = $match.Groups[1].Value
    $outputPath = Join-Path $PSScriptRoot ($name + '.txt')
    & $phpPath $runnerPath --configuration $configPath --filter $name *> $outputPath
    Write-Output ($name + ': exit=' + $LASTEXITCODE)
}
