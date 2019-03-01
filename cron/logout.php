<?php

include("/var/www/password.php");
include("/var/www/html/api/connect.php");

function update_logoffs(){

        global $conn;
        $query="UPDATE usertimetable SET isloggedon = 0 WHERE ROUND((TIME_TO_SEC(TIMEDIFF(NOW(),lastheartbeat))/60),2) > 1 AND timelimitminutes >= 0;";
        $result=mysqli_query($conn, $query);
        #$affected_rows = mysqli_affected_rows($result);
        mysqli_close($conn);
}

update_logoffs();

?>

