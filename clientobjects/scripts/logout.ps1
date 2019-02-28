param(
    $user = "",
	$timelimit = 90
)
function Set-LoginTime {
	param(
        $user,
		$timelimit = 90
	)
	$user = (Get-WMIObject -class Win32_ComputerSystem | select username).username

	$file = "C:\Scripts\$user.txt"
	if (!(Test-Path $file)){
		$directory = Split-Path $file
		New-Item -Path $directory -ItemType Directory -Force
		$loginDate = Get-Date
		Write-Output "$loginDate`|$timelimit" | Out-File $file
	}
	$fileContents = Get-Content $file

	$fileContentsArray = $fileContents.split('|')
	$loginDate = [datetime]$fileContentsArray[0]
	$minutesLeft = $fileContentsArray[1]
	if ((Get-Date).date -ne $loginDate.Date){
		$loginDate = Get-Date
		Write-Output "$loginDate`|$timelimit`|active" | Out-File $file
		Write-Output "$loginDate $timelimit"
	}
	
}

function Check-LogOff {
	param(
        $user
	)
	$file = "C:\Scripts\$user.txt"
	if (!(Test-Path $file)){
		exit 0
	}
	$fileContents = Get-Content $file

	$fileContentsArray = $fileContents.split('|')
	$loginDate = [datetime]$fileContentsArray[0]
	$minutesLeft = $fileContentsArray[1]
	$logonStatus = $fileContentsArray[2]
	$currentDate = Get-Date
	if ($logonStatus -eq "inactive" -and $minutesLeft -gt 0){
		Write-Output "$currentDate`|$minutesLeft`|active" | Out-File $file
		exit 0
	}

	$dateDifference = $currentDate - $loginDate
	$newMinutesLeft = $minutesLeft - $dateDifference.TotalMinutes
	if ($newMinutesLeft -le 0){
		$userArray=query session $user.split('\')[1] /SERVER:127.0.0.1|select -skip 1|%{$_.Split(' ',[System.StringSplitOptions]::RemoveEmptyEntries)}
		$ID = $userArray[2]
		logoff $ID
	}
	Write-Output "$currentDate`|$newMinutesLeft`|active" | Out-File $file
	
}
# Check if logged-in session exists
$ErrorActionPreference = 'Stop'
try {
	$userArray=query session $user.split('\')[1] /SERVER:127.0.0.1|select -skip 1|%{$_.Split(' ',[System.StringSplitOptions]::RemoveEmptyEntries)}
    if ($userArray[3] -ne "Active"){
        throw "Not active"
    }
} catch {
	Write-Output "Session for $user does not exist or user is not active"
    $file = "C:\Scripts\$user.txt"
	if (!(Test-Path $file)){
		exit 0
	}
	$fileContents = Get-Content $file

	$fileContentsArray = $fileContents.split('|')
	$minutesLeft = $fileContentsArray[1]
    $logonStatus = $fileContentsArray[2]
    $currentDate = Get-Date
    if ($logonStatus -ne "inactive"){
        Write-Output "$currentDate`|$minutesLeft`|inactive" | Out-File $file
    }
    #Write-Output "$currentDate`|$minutesLeft`|inactive" | Out-File C:\Scripts\test.txt
	exit 0
}
Set-LoginTime $user $timelimit
Check-LogOff $user
