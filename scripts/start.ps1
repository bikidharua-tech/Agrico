param(
    [switch]$StartPhp,
    [switch]$OpenBrowser,
    [string]$SiteUrl = "",
    [string]$PhpHost = "127.0.0.1",
    [int]$PhpPort = 8081,
    [string]$UvicornHost = "127.0.0.1",
    [int]$UvicornPort = 8001
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$pythonApiDir = Join-Path $repoRoot "python_api"
$publicDir = Join-Path $repoRoot "public"
if (-not (Test-Path $pythonApiDir)) {
    throw "Missing python_api directory: $pythonApiDir"
}

if (-not $PSBoundParameters.ContainsKey("StartPhp")) {
    $StartPhp = $true
}
if (-not $PSBoundParameters.ContainsKey("OpenBrowser")) {
    $OpenBrowser = $true
}

$venvPython = Join-Path $pythonApiDir ".venv\\Scripts\\python.exe"
$venvUvicorn = Join-Path $pythonApiDir ".venv\\Scripts\\uvicorn.exe"
function Get-PhpExecutable {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd) {
        return $phpCmd.Source
    }

    $candidatePaths = @(
        (Join-Path $repoRoot "tools\\php\\php.exe"),
        "C:\\xampp\\php\\php.exe",
        "C:\\php\\php.exe"
    )

    foreach ($candidate in $candidatePaths) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

$python = if (Test-Path $venvPython) { $venvPython } else { "python" }
if (-not (Get-Command $python -ErrorAction SilentlyContinue)) {
    throw "Python executable not found. Expected: $venvPython"
}

$tmpDir = Join-Path $pythonApiDir ".tmp"
if (-not (Test-Path $tmpDir)) {
    New-Item -ItemType Directory -Path $tmpDir | Out-Null
}
$env:TEMP = $tmpDir
$env:TMP = $tmpDir

Write-Host "Checking Python dependencies..." -ForegroundColor Cyan
$uvicornCheck = Start-Process `
    -FilePath $python `
    -ArgumentList @("-m", "uvicorn", "--version") `
    -WorkingDirectory $pythonApiDir `
    -NoNewWindow `
    -PassThru `
    -Wait

if ($uvicornCheck.ExitCode -ne 0) {
    Write-Warning "uvicorn not available. Preparing pip and installing python_api requirements..."
    $pipCheck = Start-Process `
        -FilePath $python `
        -ArgumentList @("-m", "pip", "--version") `
        -WorkingDirectory $pythonApiDir `
        -NoNewWindow `
        -PassThru `
        -Wait

    if ($pipCheck.ExitCode -ne 0) {
        Write-Warning "pip not available. Bootstrapping with ensurepip..."
        $ensurePip = Start-Process `
            -FilePath $python `
            -ArgumentList @("-m", "ensurepip", "--upgrade") `
            -WorkingDirectory $pythonApiDir `
            -NoNewWindow `
            -PassThru `
            -Wait

        if ($ensurePip.ExitCode -ne 0) {
            throw "Failed to bootstrap pip for python_api virtual environment."
        }
    }

    $pipInstall = Start-Process `
        -FilePath $python `
        -ArgumentList @("-m", "pip", "install", "-r", "requirements.txt") `
        -WorkingDirectory $pythonApiDir `
        -NoNewWindow `
        -PassThru `
        -Wait

    if ($pipInstall.ExitCode -ne 0) {
        throw "Failed to install python_api dependencies. Activate your environment and run: pip install -r python_api/requirements.txt"
    }
}

Write-Host "Starting Python API (FastAPI)..." -ForegroundColor Cyan
if (Test-Path $venvUvicorn) {
    $apiProc = Start-Process `
        -FilePath $venvUvicorn `
        -ArgumentList @("main:app", "--host", $UvicornHost, "--port", $UvicornPort) `
        -WorkingDirectory $pythonApiDir `
        -NoNewWindow `
        -PassThru
} else {
    $apiProc = Start-Process `
        -FilePath $python `
        -ArgumentList @("-m", "uvicorn", "main:app", "--host", $UvicornHost, "--port", $UvicornPort) `
        -WorkingDirectory $pythonApiDir `
        -NoNewWindow `
        -PassThru
}

Write-Host ("Python API PID: {0}" -f $apiProc.Id)
Write-Host ("Python API URL: http://{0}:{1}" -f $UvicornHost, $UvicornPort)

$webUrl = ""
if ($StartPhp) {
    $phpExe = Get-PhpExecutable
    if (-not $phpExe) {
        Write-Warning "php not found on PATH. Skipping PHP built-in server startup."
    } else {
        if (-not (Test-Path $publicDir)) {
            throw "Missing public directory: $publicDir"
        }

        Write-Host "Starting PHP built-in server..." -ForegroundColor Cyan
        $phpProc = Start-Process `
            -FilePath $phpExe `
            -ArgumentList @("-S", ("{0}:{1}" -f $PhpHost, $PhpPort), "-t", $publicDir) `
            -WorkingDirectory $repoRoot `
            -NoNewWindow `
            -PassThru

        Write-Host ("PHP server PID: {0}" -f $phpProc.Id)
        $webUrl = "http://{0}:{1}" -f $PhpHost, $PhpPort
        Write-Host ("PHP URL: {0}" -f $webUrl)
    }
}

if (-not $webUrl) {
    $configPath = Join-Path $repoRoot "config\\config.php"
    if (Test-Path $configPath) {
        try {
            $config = . $configPath
            if ($config -and $config['base_url']) {
                $webUrl = [string]$config['base_url']
            }
        } catch {
            # Ignore parse errors and continue to fallback.
        }
    }
}

if (-not $webUrl) {
    $webUrl = "http://{0}:{1}" -f $PhpHost, $PhpPort
}
if ($SiteUrl -ne "") {
    $webUrl = $SiteUrl
}

if ($OpenBrowser) {
    Start-Sleep -Milliseconds 700
    Write-Host ("Opening website: {0}" -f $webUrl) -ForegroundColor Green
    try {
        Start-Process $webUrl | Out-Null
    } catch {
        Write-Warning "Could not auto-open browser in this environment. Open manually: $webUrl"
    }
}
