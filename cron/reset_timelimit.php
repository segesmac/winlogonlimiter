<?php

include("/var/www/password.php");
include("/var/www/html/api/connect.php");

function update_timelimits(){

	global $conn;
	$query="UPDATE usertimetable SET timeleftminutes = timelimitminutes;";
	$result=mysqli_query($conn, $query);
	#$affected_rows = mysqli_affected_rows($result);
	mysqli_close($conn);
}

update_timelimits();

?>
