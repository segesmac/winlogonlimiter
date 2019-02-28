$userObj = @{
    username = "zachary"
    #timelimit = 90
    #timeleft = 90
    #bonusminutesadd = 30
    loginstatus = 1
}
$body = $userObj | ConvertTo-Json
Invoke-RestMethod -Uri "http://192.168.2.115/api/v1/heartbeat.php" -Method PUT -ContentType 'application/json' -Body $body

