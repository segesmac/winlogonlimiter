<?php

include("../connect.php");
$request_method=$_SERVER["REQUEST_METHOD"];

function get_users($username = ""){
	global $conn;
	if ($username != ""){
		$stmt = mysqli_stmt_init($conn);
		if (mysqli_stmt_prepare($stmt, 'SELECT * FROM usertimetable WHERE username=?')){
			mysqli_stmt_bind_param($stmt, "s", $username);
			mysqli_stmt_execute($stmt);
			#mysqli_stmt_bind_result($stmt, $response);
			$result = mysqli_stmt_get_result($stmt);
			mysqli_stmt_close($stmt);
		} else {
			die ("Failed to prepare statement: SELECT * FROM usertimetable WHERE username=?\n");
		}
	} else {
		$query="SELECT * FROM usertimetable";
		$result=mysqli_query($conn, $query);
	}
	$response=array();
	while($row=mysqli_fetch_assoc($result)){
			$response[]=$row;
		}
	header('Content-Type: application/json');
	if (count($response) == 1 && $username != ""){
		$return_response = array(
                        'status' => 1,
                        'status_message' => "$username found successfully!",
			'payload' => $response[0]
                );
		echo json_encode($return_response);
		
	} elseif (count($response) == 0){
		$return_response = array(
			'status' => -1,
                        'status_message' => "User $username doesn't exist!"
		);
		echo json_encode($return_response);
	} else {
		$return_response = array(
                        'status' => 1,
                        'status_message' => "Found users successfully!",
			'payload' => $response
                );
		echo json_encode($return_response);
	}
}

function insert_user(){
	global $conn;
	$data = json_decode(file_get_contents('php://input'), true);
	$username = strval($data["username"]);
	$timelimit = doubleval($data["timelimit"]);
	$stmt = mysqli_stmt_init($conn);
        if (mysqli_stmt_prepare($stmt, "INSERT INTO usertimetable (username, isloggedon, lastlogon, lastheartbeat, timelimitminutes, timeleftminutes, bonustimeminutes) SELECT * FROM (SELECT ? AS username,0 AS isloggedon, NULL AS lastlogon, NULL AS lastheartbeat, ? AS timelimitminutes, ? AS timeleftminutes, 0 AS bonustimeminutes) AS tmp WHERE NOT EXISTS (SELECT username FROM usertimetable WHERE username = ?) LIMIT 1;")){
                mysqli_stmt_bind_param($stmt, "siis", $username, $timelimit, $timelimit, $username);
                mysqli_stmt_execute($stmt);
                #printf("%d Row inserted.\n", mysqli_stmt_affected_rows($stmt));
		$affected_rows = mysqli_stmt_affected_rows($stmt);
		mysqli_stmt_close($stmt);
		if ($affected_rows == 0){
			$response = array(
				'status' => 1,
				'status_message' => "User $username already exists."
			);
		} else {
			$response = array(
				'status' => 1,
				'status_message' => "User $username inserted successfully!"
			);
		}
        } else {
		$response = array(
			'status' => 0,
			'status_message' =>  "Error: " . $sql . "\n<br />\n" . mysqli_error($conn)
		);
        }
	header('Content-Type: application/json');
	echo json_encode($response);

}

function update_user($username = "") {
	global $conn;
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($username)) {
		$username = strval($data["username"]);
	}
        $timelimit = $data["timelimit"];
	$bonusminutesadd = $data["bonusminutesadd"];
	$loginstatus = $data["loginstatus"];
	$bonusminutes = $data["bonusminutes"];
	$return_response = array();

	# Update login status
	if (isset($loginstatus) && $username != ""){
		$stmt = mysqli_stmt_init($conn);
		if (mysqli_stmt_prepare($stmt, "UPDATE usertimetable SET lastrowupdate = NOW(), lastheartbeat = CASE WHEN ? > 0 THEN NOW() ELSE lastheartbeat END, lastlogon = CASE WHEN isloggedon = 0 AND ? > 0 THEN NOW() ELSE lastlogon END, isloggedon = ? WHERE username = ?;")){
			mysqli_stmt_bind_param($stmt, "iiis", $loginstatus, $loginstatus, $loginstatus, $username);
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
                	                'status_message' => "User $username updated successfully!"
                	        );
                	}
			$return_response["loginstatus"] = $response;
		}
	}
	# Set bonus minutes to some value
        if (!empty($username) && isset($bonusminutes)){
                $stmt = mysqli_stmt_init($conn);
                if (mysqli_stmt_prepare($stmt, "UPDATE usertimetable SET lastrowupdate = NOW() + 1, bonustimeminutes = ? WHERE username = ?")){
                        mysqli_stmt_bind_param($stmt, "ds", $bonusminutes, $username);
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
                                        'status' => $bonusminutes,
                                        'status_message' => "Set bonusminutes to $bonusminutes for $username successfully!"
                                );
                        }
                        $return_response["bonusminutes"] = $response;
                }
        }
	# Add minutes to the bonus pool
	if (!empty($username) && isset($bonusminutesadd)){
		$stmt = mysqli_stmt_init($conn);
		if (mysqli_stmt_prepare($stmt, "UPDATE usertimetable SET lastrowupdate = NOW() + 2, bonustimeminutes = bonustimeminutes + ? WHERE username = ?")){
			mysqli_stmt_bind_param($stmt, "ds", $bonusminutesadd, $username);
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
                                        'status_message' => "Added $bonusminutesadd bonus minute(s) to $username successfully!"
                                );
                        }
                        $return_response["bonusminutesadd"] = $response;
		}
	}
	# Update the time limit to some value
        if (!empty($username) && isset($timelimit)){
                $stmt = mysqli_stmt_init($conn);
                if (mysqli_stmt_prepare($stmt, "UPDATE usertimetable SET lastrowupdate = NOW() + 3, timelimitminutes = ? WHERE username = ?")){
                        mysqli_stmt_bind_param($stmt, "ds", $timelimit, $username);
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
                                        'status_message' => "Set timelimitminutes to $timelimit for $username successfully!"
                                );
                        }
                        $return_response["timelimit"] = $response;
                }
        }
        header('Content-Type: application/json');
        echo json_encode($return_response);
}

function delete_user($username) {
	global $conn;
	if (empty($username)) {
		$data = json_decode(file_get_contents('php://input'), true);
                $username = strval($data["username"]);
        }
	if (!empty($username)){
		$stmt = mysqli_stmt_init($conn);
                if (mysqli_stmt_prepare($stmt, "DELETE FROM usertimetable WHERE username = ?")){
                        mysqli_stmt_bind_param($stmt, "s", $username);
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
                                        'status_message' => "Deleted $username successfully!"
                                );
                        }
                } else {
			$response = array(
                        	'status' => 0,
                        	'status_message' =>  "Error: " . $sql . "\n" . mysqli_error($conn)
                	);
		}
        }  else {
        	$response = array(
                	'status' => 0,
                        'status_message' => "You must include a username!"
                );
	}
        header('Content-Type: application/json');
        echo json_encode($response);
}

switch($request_method){
	case 'GET':
		// retrive users
		if(!empty($_GET["username"]))
		{
			$username=strval($_GET["username"]);
			get_users($username);
		}
		else
		{
			get_users();
		}
		break;
	case 'POST':
		insert_user();
		break;
	case 'PUT':
		if (!empty($_GET["username"])){
			$username=strval($_GET["username"]);
			update_user($username);
		} else {
			update_user();
		}
		break;
	case 'DELETE':
		if (!empty($_GET["username"])){
			$username=strval($_GET["username"]);
			delete_user($username);
		} else {
			delete_user();
		}
		break;
	default:
		// Invalid Request Method
		header("HTTP/1.0 405 Method Not Allowed");
		break;
}

mysqli_close($conn);


?>
