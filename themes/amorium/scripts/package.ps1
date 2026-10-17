# Builds the installation package of the Amorium theme and the AmoriumCms.Amorium plugin.
# Every run raises the patch version of the theme (1.6.0 -> 1.6.1); with -Minor the minor version is raised
# instead (1.6.1 -> 1.7.0).
# The marketplace repositories in the amoriumcms folder next to public_html (theme, plugin, shop)
# are updated, committed and tagged; pushing is left to the developer.
# The marketplace reads composer.json from the repository root, so the theme and the plugin have their own
# repositories named theme and plugin.
param(
    [switch] $Minor
)

$ErrorActionPreference = 'Stop'

$themePath = Split-Path $PSScriptRoot -Parent
$publicPath = Split-Path (Split-Path $themePath -Parent) -Parent
$rootPath = Split-Path $publicPath -Parent
$outputPath = Join-Path $rootPath 'setup'
$reposPath = Join-Path $rootPath 'amoriumcms'
$pluginPath = Join-Path $publicPath 'plugins\amoriumcms\amorium'
$shopPath = Join-Path $publicPath 'plugins\amoriumcms\shop'
$setupPath = Join-Path $PSScriptRoot 'setup'
$repoOwner = 'AmoriumCms'
$versionFile = Join-Path $themePath 'version.yaml'

function Get-LatestVersion([string] $file) {
    Select-String -Path $file -Pattern '^v?(\d+)\.(\d+)\.(\d+)\s*:' | ForEach-Object {
        [version]('{0}.{1}.{2}' -f $_.Matches[0].Groups[1].Value, $_.Matches[0].Groups[2].Value, $_.Matches[0].Groups[3].Value)
    } | Sort-Object | Select-Object -Last 1
}

function Copy-Folder([string] $source, [string] $target, [string[]] $excludeDirs = @()) {
    $arguments = @($source, $target, '/MIR', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
    if ($excludeDirs.Count) {
        $arguments += '/XD'
        $arguments += $excludeDirs
    }
    robocopy @arguments | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "Copying $source failed"
    }
}

# Mirrors $source into the repository, then commits and tags it when something changed.
function Update-Repository([string] $name, [string] $source, [string] $tag, [string] $message, [string[]] $extraFiles = @()) {
    $repoPath = Join-Path $reposPath $name
    if (-not (Test-Path (Join-Path $repoPath '.git'))) {
        New-Item -ItemType Directory $repoPath -Force | Out-Null
        git -C $repoPath init --quiet --initial-branch=main
        git -C $repoPath remote add origin "https://github.com/$repoOwner/$name.git"
    }

    Copy-Folder $source $repoPath @('.git', 'node_modules', 'scripts')
    foreach ($file in $extraFiles) {
        Copy-Item $file $repoPath -Force
    }

    git -C $repoPath add -A
    git -C $repoPath diff --cached --quiet
    if ($LASTEXITCODE -ne 0) {
        git -C $repoPath commit -m $message --quiet
        if ($LASTEXITCODE -ne 0) {
            throw "Committing to $name failed"
        }
    }

    git -C $repoPath rev-parse --verify --quiet "refs/tags/$tag" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        git -C $repoPath tag -a $tag -m $message
    }

    return $repoPath
}

$current = Get-LatestVersion $versionFile
if (-not $current) {
    throw "No version found in $versionFile"
}
if ($Minor) {
    $next = '{0}.{1}.0' -f $current.Major, ($current.Minor + 1)
}
else {
    $next = '{0}.{1}.{2}' -f $current.Major, $current.Minor, ($current.Build + 1)
}

Push-Location $themePath
try {
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw 'npm run build failed'
    }
}
finally {
    Pop-Location
}

$stage = Join-Path $env:TEMP "amorium-package-$next"
if (Test-Path $stage) {
    Remove-Item $stage -Recurse -Force
}

$content = [IO.File]::ReadAllText($versionFile).TrimEnd()
[IO.File]::WriteAllText($versionFile, "$content`nv${next}: Package created on $(Get-Date -Format 'yyyy-MM-dd')`n")

$repositories = @()
try {
    Copy-Folder $themePath (Join-Path $stage 'themes\amorium') @('node_modules', 'scripts')
    Copy-Folder $pluginPath (Join-Path $stage 'plugins\amoriumcms\amorium')
    Copy-Item (Join-Path $setupPath '.env.example'), (Join-Path $setupPath 'KURULUM.txt') $stage -Force

    New-Item -ItemType Directory $outputPath -Force | Out-Null
    $zip = Join-Path $outputPath "amorium-$next.zip"
    if (Test-Path $zip) {
        Remove-Item $zip
    }

    # tar writes forward slashes so the archive also extracts correctly on Linux.
    tar -a -c -f $zip -C $stage .env.example KURULUM.txt themes plugins
    if ($LASTEXITCODE -ne 0) {
        throw 'Creating the zip failed'
    }

    $repoFiles = @((Join-Path $setupPath 'README.md'), (Join-Path $setupPath '.gitattributes'))
    $repositories += Update-Repository 'theme' $themePath "v$next" "Amorium $next" $repoFiles

    $pluginVersion = Get-LatestVersion (Join-Path $pluginPath 'updates\version.yaml')
    if (-not $pluginVersion) {
        throw "No version found for the plugin in $pluginPath"
    }
    $repositories += Update-Repository 'plugin' $pluginPath "v$pluginVersion" "AmoriumCms.Amorium $pluginVersion" @((Join-Path $setupPath 'plugin\README.md'), (Join-Path $setupPath '.gitattributes'))

    $shopVersion = Get-LatestVersion (Join-Path $shopPath 'updates\version.yaml')
    if (-not $shopVersion) {
        throw "No version found for the shop plugin in $shopPath"
    }
    $repositories += Update-Repository 'shop' $shopPath "v$shopVersion" "AmoriumCms.Shop $shopVersion" @((Join-Path $setupPath 'shop\README.md'), (Join-Path $setupPath '.gitattributes'))
}
catch {
    [IO.File]::WriteAllText($versionFile, "$content`n")
    throw
}
finally {
    if (Test-Path $stage) {
        Remove-Item $stage -Recurse -Force
    }
}

Write-Host "Amorium $current -> $next"
Write-Host "Package: $zip"
Write-Host 'Repositories (push each with: git -C <path> push -u origin main --follow-tags):'
$repositories | ForEach-Object { Write-Host "  $_" }
