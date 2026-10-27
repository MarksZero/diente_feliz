$ErrorActionPreference = 'Stop'
$taskRoot = $PSScriptRoot
$appPath = Join-Path $taskRoot 'diente-feliz'
$envPath = Join-Path $appPath '.env'
$dbSecret = (Get-Content (Join-Path $taskRoot '.runtime/pg-password') -Raw).Trim()
$demoSecret = 'Demo-DienteFeliz!2026'
$existingKey = ((Get-Content $envPath | Where-Object { $_ -like 'APP_KEY=*' }) -replace '^APP_KEY=','')
$config = @"
APP_NAME="Diente Feliz"
APP_ENV=local
APP_KEY=$existingKey
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_ES
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=diente_feliz
DB_USERNAME=postgres
DB_PASSWORD=$dbSecret
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_FROM_ADDRESS=no-reply@dientefeliz.test
MAIL_FROM_NAME="Diente Feliz"
LOG_CHANNEL=single
LOG_LEVEL=warning
BCRYPT_ROUNDS=12
DEMO_PASSWORD=$demoSecret
"@
[IO.File]::WriteAllText($envPath,$config)
[IO.File]::WriteAllText((Join-Path $appPath '.env.testing'),$config.Replace('APP_ENV=local','APP_ENV=testing').Replace('DB_DATABASE=diente_feliz','DB_DATABASE=diente_feliz_test').Replace('BCRYPT_ROUNDS=12','BCRYPT_ROUNDS=4'))
