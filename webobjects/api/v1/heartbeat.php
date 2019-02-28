<?php

include("../connect.php");
$request_method=$_SERVER["REQUEST_METHOD"];

function update_heartbeat($username = "") {
	global $conn;
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($username)) {
		$username = strval($data["username"]);
	}
	$loginstatus = $data["loginstatus"];
	$return_response = array();

	# Update login status
	if (isset($loginstatus) && $username != ""){
		$stmt = mysqli_stmt_init($conn);
		if (mysqli_stmt_prepare($stmt, "UPDATE usertimetable  SET  bonustimeminutes = CASE WHEN isloggedon > 0 AND timeleftminutes <= 0 AND bonustimeminutes > 0 THEN bonustimeminutes - ROUND((TIME_TO_SEC(TIMEDIFF(NOW(),lastheartbeat))/60),2) ELSE bonustimeminutes END , timeleftminutes = CASE WHEN isloggedon > 0 AND timeleftminutes > 0 THEN timeleftminutes - ROUND((TIME_TO_SEC(TIMEDIFF(NOW(),lastheartbeat))/60),2) ELSE timeleftminutes END , lastrowupdate = NOW() , lastheartbeat = NOW() , isloggedon = ? WHERE username = ?;")){
			mysqli_stmt_bind_param($stmt, "is", $loginstatus, $username);
                	mysqli_stmt_execute($stmt);
                	$affected_rows = mysqli_stmt_affected_rows($stmt);
                	mysqli_stmt_close($stmt);
                	if ($affected_rows == 0){
                	        $response = array(
                	                'status' => 0,
                	                'status_message' => "User $username doesn't exist!"
                	        );
                	} else {
                	        $response = array(
                	                'status' => 1,
                	                'status_message' => "User $username heartbeat updated successfully!"
                	        );
                	}
			$return_response["heartbeat"] = $response;
		}
	}
        header('Content-Type: application/json');
        echo json_encode($return_response);
}

switch($request_method){
	case 'PUT':
		if (!empty($_GET["username"])){
			$username=strval($_GET["username"]);
			update_heartbeat($username);
		} else {
			update_heartbeat();
		}
		break;
	default:
		// Invalid Request Method
		header("HTTP/1.0 405 Method Not Allowed");
		break;
}

mysqli_close($conn);


?>
