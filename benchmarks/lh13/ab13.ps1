# Interleaved A/B with Lighthouse 13.5 (PSI-equivalent): sets a config path to value A or B per round
# (bash-quoted, so newlines can be passed as $'a\nb'), flushes caches, measures warm pages once per arm per round.
# Usage: .\ab13.ps1 -Path softcode_lumaspeed/css/inline_stylesheets -A "''" -B "`$'css/styles-m.\nmedia/styles.css'" -Label inlinecss
param([Parameter(Mandatory)][string]$Path, [Parameter(Mandatory)][string]$A, [Parameter(Mandatory)][string]$B,
      [Parameter(Mandatory)][string]$Label, [int]$Rounds = 3, [string]$Pages = 'home,plp,pdp')
Set-Location $PSScriptRoot
$Distro = if ($env:WSL_DISTRO) { $env:WSL_DISTRO } else { 'Ubuntu' }
$env:RUNS = '1'; $env:PAGES = $Pages
for ($r = 1; $r -le $Rounds; $r++) {
    foreach ($arm in @(@{ Name = 'A'; Value = $A }, @{ Name = 'B'; Value = $B })) {
        Write-Output "=== round $r arm $($arm.Name)"
        wsl -d $Distro -- bash -c "cd ~/Sites/magento-seo-baseline && bin/clinotty bin/magento config:set $Path $($arm.Value) </dev/null >/dev/null && bin/clinotty bin/magento cache:flush </dev/null >/dev/null"
        if ($LASTEXITCODE -ne 0) { Write-Output 'TOGGLE FAILED'; exit 1 }
        node psi-local.mjs "ab-$Label-$($arm.Name)-r$r" warm
    }
}
Write-Output 'AB13_DONE'
