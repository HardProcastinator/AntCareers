$src = Split-Path $PSScriptRoot   # workspace root = parent of .vscode/
$dst = 'C:\xampp\htdocs\AntCareers'

# Initial full sync on startup
robocopy $src $dst /MIR /XD '.git' '.vscode' /XF '.gitignore' | Out-Null
Write-Host "[sync] Initial sync done - watching for changes..."

# Watch for any file change in the workspace
$watcher = New-Object IO.FileSystemWatcher($src, '*.*')
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents   = $true

$action = {
    $data = $Event.MessageData
    robocopy $data.Src $data.Dst /MIR /XD '.git' '.vscode' /XF '.gitignore' | Out-Null
}

$msgData = @{ Src = $src; Dst = $dst }

Register-ObjectEvent $watcher Changed -Action $action -MessageData $msgData | Out-Null
Register-ObjectEvent $watcher Created -Action $action -MessageData $msgData | Out-Null
Register-ObjectEvent $watcher Deleted -Action $action -MessageData $msgData | Out-Null
Register-ObjectEvent $watcher Renamed -Action $action -MessageData $msgData | Out-Null

# Keep the script running indefinitely
while ($true) { Start-Sleep -Seconds 5 }
