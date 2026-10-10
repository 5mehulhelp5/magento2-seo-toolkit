# Measures the key pages with real (devtools) throttling for one optimisation stage.
# Usage: .\stage.ps1 1-vanilla
param([Parameter(Mandatory)][string]$Stage)
Set-Location $PSScriptRoot
# Override via environment: WSL_DISTRO (default Ubuntu), STORE_DIR (docker-magento project inside WSL)
$Distro = if ($env:WSL_DISTRO) { $env:WSL_DISTRO } else { 'Ubuntu' }
$StoreDir = if ($env:STORE_DIR) { $env:STORE_DIR } else { '~/Sites/magento-seo-baseline' }
$env:COOKIE = ((node cart-session.mjs) -split '; ' | Where-Object { $_ -notlike 'mage-messages=*' }) -join '; '
if (-not $env:COOKIE) { throw 'could not create a cart session' }
$env:THROTTLE = 'devtools'
$env:REPORT_PREFIX = "stage-$Stage-"
node measure.mjs pages-key.json "stage-$Stage.json"
