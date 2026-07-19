param(
    [string]$StudioCommand = '',
    [string]$SitePath = ''
)

$ErrorActionPreference = 'Stop'

if ($StudioCommand -eq '') {
    $StudioCommand = (Get-Command studio -ErrorAction Stop).Source
}

if (-not (Test-Path -LiteralPath $StudioCommand)) {
    throw "WordPress Studio CLI was not found: $StudioCommand"
}

if ($SitePath -eq '') {
    throw 'Pass -SitePath with the path to a dedicated WordPress Studio test site.'
}

if (-not (Test-Path -LiteralPath $SitePath)) {
    throw "WordPress Studio test site was not found: $SitePath"
}

$repoRoot = Split-Path -Parent $PSScriptRoot
$pluginSource = Join-Path $repoRoot 'dist\slug-free-permalinks'
$pluginTarget = Join-Path $SitePath 'wp-content\plugins\slug-free-permalinks'
$testSource = Join-Path $repoRoot 'tests\studio\smoke.php'
$testTarget = Join-Path $SitePath 'wp-content\slug-free-permalinks-smoke.php'

Push-Location $repoRoot
try {
    node scripts/build-dist.mjs

    if (Test-Path -LiteralPath $pluginTarget) {
        Remove-Item -LiteralPath $pluginTarget -Recurse -Force
    }

    Copy-Item -LiteralPath $pluginSource -Destination $pluginTarget -Recurse
    Copy-Item -LiteralPath $testSource -Destination $testTarget -Force

    & $StudioCommand wp --path $SitePath plugin activate slug-free-permalinks
    & $StudioCommand wp --path $SitePath eval-file wp-content/slug-free-permalinks-smoke.php
} finally {
    Pop-Location
}
