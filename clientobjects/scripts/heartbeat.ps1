# Check if logged-in session exists

Function Get-ComputerSessions {
<#
.SYNOPSIS
    Retrieves tall user sessions from local or remote server/s
.DESCRIPTION
    Retrieves tall user sessions from local or remote server/s
.PARAMETER computer
    Name of computer/s to run session query against.
.NOTES
    Name: Get-ComputerSessions
    Author: Boe Prox
    DateCreated: 01Nov2010
 
.LINK
    https://boeprox.wordpress.org
    https://learn-powershell.net/2010/11/01/quick-hit-find-currently-logged-on-users/
.EXAMPLE
Get-ComputerSessions -computer "server1"
 
Description
-----------
This command will query all current user sessions on 'server1'.
 
#>
[cmdletbinding(
    DefaultParameterSetName = 'session',
    ConfirmImpact = 'low'
)]
    Param(
        [Parameter(
            Mandatory = $false,
            Position = 0,
            ValueFromPipeline = $True)]
            [string[]]$computer = "localhost"
            )
Begin {
    $report = @()
    }
Process {
    ForEach($c in $computer) {
        # Parse 'query session' and store in $sessions:
        $sessions = query session /server:$c
            1..($sessions.count -1) | % {
                $temp = "" | Select Computer,SessionName, Username, Id, State, Type, Device
                $temp.Computer = $c
                $temp.SessionName = $sessions[$_].Substring(1,18).Trim()
                $temp.Username = $sessions[$_].Substring(19,20).Trim()
                $temp.Id = $sessions[$_].Substring(39,9).Trim()
                $temp.State = $sessions[$_].Substring(48,8).Trim()
                $temp.Type = $sessions[$_].Substring(56,12).Trim()
                $temp.Device = $sessions[$_].Substring(68).Trim()
                $report += $temp
            }
        }
    }
End {
    $report
    }
}


function Set-Permissions {

    Param(
        [Parameter(
            Mandatory = $false,
            Position = 0,
            ValueFromPipeline = $True)]
            [string]$scripts_folder = "C:\scripts"
            )

    # Gets the Access Conrol List from the scripts folder
    Write-Output "Getting ACL from $scripts_folder."
    $acl = Get-Acl $scripts_folder

    # Check to see if the correct permissions are applied already
    $admin_access = $acl.Access | Where IsInherited -eq $false | Where FileSystemRights -eq "FullControl" | Where IdentityReference -eq "BUILTIN\Administrators"

    $superuser_access = $acl.Access | Where IsInherited -eq $false | Where FileSystemRights -eq "FullControl" | Where IdentityReference -eq "$env:USERDOMAIN\$env:USERNAME"

    # If some permissions are missing, add them
    if (!$admin_access -or !$superuser_access){
        Write-Output "Removing inheritence and associated permissions."
        # Removes inheritence and any inherited permissions (first parameter, if true, blocks inheritence. second parameter, if false, removes inherited permissions)
        $acl.SetAccessRuleProtection($true,$false)

        if (!$admin_access) {
            Write-Output "Adding Builtin\Administrators access."
            $accessrule = New-Object  system.security.accesscontrol.filesystemaccessrule("BUILTIN\Administrators","FullControl","Allow")
            $acl.SetAccessRule($accessrule)
        }

        if (!$superuser_access) {
            Write-Output "Adding Superuser access."
            $accessrule = New-Object  system.security.accesscontrol.filesystemaccessrule("$env:USERDOMAIN\$env:USERNAME","FullControl","Allow")
            $acl.SetAccessRule($accessrule)
        }

        Write-Output "Committing changes."
        $acl | Set-Acl $scripts_folder

    } else {
        Write-Output "Permissions are already set appropriately."
    }

}

$uri = "http://192.168.2.115/api/v1"
$scripts_folder = "C:\scripts"
$superuser_path = "$scripts_folder\superusers.json"
$permissions_path = "$scripts_folder\permissions_done.json"

if (!(Test-Path $scripts_folder)){
    New-Item -ItemType Directory -Path "$scripts_folder"
}


if (!(Test-Path $permissions_path)){
     ConvertTo-Json $false | Out-File $permissions_path
}

$permissions = Get-Content $permissions_path | ConvertFrom-Json

$active_user = Get-ComputerSessions | select | where SessionName -eq "console" | where State -eq "Active"
# if there is no active user, then exit gracefully
if (!$active_user){
    exit 0
}


if (!(Test-Path $superuser_path)){
    ConvertTo-Json @() | Out-File $superuser_path
}

if (!$permissions){
    foreach ($file in Get-ChildItem "C:\scripts" -Recurse){
        Set-Permissions $file.FullName
    }
    Set-Permissions
    $permissions = $true
    ConvertTo-Json $permissions | Out-File $permissions_path
}

$superusers = Get-Content $superuser_path | ConvertFrom-Json

# if user is in the superuser list, then exit gracefully
if ($active_user.Username -in $superusers){
    exit 0
}

# Get user info from api
$result = Invoke-RestMethod -Uri "$uri/users.php?username=$($active_user.Username)" -Method GET -ContentType 'application/json' -TimeoutSec 5
# if the user doesn't exist, insert them as a user with no limits
if ($result.status_message -eq "User $($active_user.Username) doesn't exist!"){
    $userObj = @{
        username = $active_user.Username
        timelimit = -1
    }
    $body = $userObj | ConvertTo-Json
    Invoke-RestMethod -Uri "$uri/users.php" -Method POST -ContentType 'application/json' -Body $body -TimeoutSec 5

    if ($active_user.Username -notin $superusers){
        $superusers += $active_user.Username
        ConvertTo-Json $superusers | Out-File $superuser_path
    }
} else {
    # if the user does exist, make sure they aren't a user with no limits (indicated by -1 for limit)
    # then check to see if they have any time left
    if ([int]$result.payload.timelimitminutes -ne -1){
        # if they have time left, run the heartbeat.  Otherwise, log them off
        if ([double]$result.payload.timeleftminutes + [double]$result.payload.bonustimeminutes -gt 0){
            $userObj = @{
                username = $active_user.Username
                loginstatus = 1
            }
            $body = $userObj | ConvertTo-Json
            Invoke-RestMethod -Uri "$uri/heartbeat.php" -Method PUT -ContentType 'application/json' -Body $body -TimeoutSec 5
        } else {
            logoff $active_user.Id
        }
    } else {
        if ($active_user.Username -notin $superusers){
            $superusers += $active_user.Username
            ConvertTo-Json $superusers | Out-File $superuser_path
        }
    }
}

