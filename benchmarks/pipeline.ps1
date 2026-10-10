# Steps 2-4: apply each change, then measure it with real throttling. Stops on the first failure.
Set-Location $PSScriptRoot
# Override via environment: WSL_DISTRO (default Ubuntu), STORE_DIR (docker-magento project inside WSL)
$Distro = if ($env:WSL_DISTRO) { $env:WSL_DISTRO } else { 'Ubuntu' }
$StoreDir = if ($env:STORE_DIR) { $env:STORE_DIR } else { '~/Sites/magento-seo-baseline' }
# this folder as seen from WSL, for the bash scripts in store-setup/
$BenchWsl = (wsl -d $Distro -- wslpath -a ($PSScriptRoot -replace '\\', '/')).Trim()
$steps = @(
    @{ Script = 'store-setup/apply.sh';         Stage = '2-realistic' },
    @{ Script = 'store-setup/native-config.sh'; Stage = '3-native-config' },
    @{ Script = 'store-setup/enable-module.sh'; Stage = '4-luma-speed' }
)
foreach ($s in $steps) {
    Write-Output "=== applying $($s.Script)"
    $sh = "$BenchWsl/$($s.Script)"
    wsl -d $Distro -- bash -c "tr -d '\r' < $sh > /tmp/step.sh && BENCH_DIR=$BenchWsl TOOLKIT_DIR=$BenchWsl/.. STORE_DIR=$StoreDir bash /tmp/step.sh"
    if ($LASTEXITCODE -ne 0) { Write-Output "STEP FAILED: $($s.Script)"; exit 1 }
    Write-Output "=== measuring $($s.Stage)"
    & "$PSScriptRoot\stage.ps1" $s.Stage
    if ($LASTEXITCODE -ne 0) { Write-Output "MEASURE FAILED: $($s.Stage)"; exit 1 }
}
Write-Output 'PIPELINE_DONE'
