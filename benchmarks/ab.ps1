# Interleaved A/B: module off/on alternated per round, 1 run per page per arm per round,
# so background noise on this machine hits both arms equally. Aggregate with: node ab-compare.mjs
param([int]$Rounds = 3)
Set-Location $PSScriptRoot
# Override via environment: WSL_DISTRO (default Ubuntu), STORE_DIR (docker-magento project inside WSL)
$Distro = if ($env:WSL_DISTRO) { $env:WSL_DISTRO } else { 'Ubuntu' }
$StoreDir = if ($env:STORE_DIR) { $env:STORE_DIR } else { '~/Sites/magento-seo-baseline' }
$env:RUNS = '1'
for ($r = 1; $r -le $Rounds; $r++) {
    foreach ($arm in @(@{ Name = 'off'; Flag = 0 }, @{ Name = 'on'; Flag = 1 })) {
        Write-Output "=== round $r, module $($arm.Name)"
        wsl -d $Distro -- bash -c "cd $StoreDir && bin/clinotty bin/magento config:set softcode_lumaspeed/general/enabled $($arm.Flag) </dev/null && bin/clinotty bin/magento cache:flush </dev/null >/dev/null"
        if ($LASTEXITCODE -ne 0) { Write-Output 'TOGGLE FAILED'; exit 1 }
        & "$PSScriptRoot\stage.ps1" "ab-$($arm.Name)-r$r"
    }
}
Write-Output 'AB_DONE'
