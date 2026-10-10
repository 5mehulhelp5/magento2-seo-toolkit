# Interleaved A/B of one Magento config value (needs only a cache flush, e.g. dev/js/move_script_to_bottom).
# Usage: .\ab-setting.ps1 -Path dev/js/move_script_to_bottom -Label movejs -Pages pages-cls.json -Rounds 2
param([Parameter(Mandatory)][string]$Path, [Parameter(Mandatory)][string]$Label,
      [string]$Pages = 'pages-key.json', [int]$Rounds = 2)
Set-Location $PSScriptRoot
# Override via environment: WSL_DISTRO (default Ubuntu), STORE_DIR (docker-magento project inside WSL)
$Distro = if ($env:WSL_DISTRO) { $env:WSL_DISTRO } else { 'Ubuntu' }
$StoreDir = if ($env:STORE_DIR) { $env:STORE_DIR } else { '~/Sites/magento-seo-baseline' }
$env:RUNS = '1'
for ($r = 1; $r -le $Rounds; $r++) {
    foreach ($v in 0, 1) {
        Write-Output "=== round $r, $Path = $v"
        wsl -d $Distro -- bash -c "cd $StoreDir && bin/clinotty bin/magento config:set $Path $v </dev/null && bin/clinotty bin/magento cache:flush </dev/null >/dev/null"
        if ($LASTEXITCODE -ne 0) { Write-Output 'TOGGLE FAILED'; exit 1 }
        $env:COOKIE = ((node cart-session.mjs) -split '; ' | Where-Object { $_ -notlike 'mage-messages=*' }) -join '; '
        $env:THROTTLE = 'devtools'
        $env:REPORT_PREFIX = "ab-$Label-$v-r$r-"
        node measure.mjs $Pages "ab-$Label-$v-r$r.json"
    }
}
Write-Output 'AB_SETTING_DONE'
