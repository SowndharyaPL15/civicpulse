<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
die("Unauthorized");
}

$admin_id = $_SESSION['admin_id'];
$issue_id = $_GET['issue_id'] ?? 0;


/* GET ISSUE LOCATION */

$stmt = $conn->prepare("
SELECT latitude, longitude, department_id
FROM issues
WHERE id=?
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();
$issue = $stmt->get_result()->fetch_assoc();

if(!$issue){
die("Issue not found");
}

$issue_lat = $issue['latitude'];
$issue_lon = $issue['longitude'];
$dept_id = $issue['department_id'];


/* GET AVAILABLE WORKERS */

$stmt = $conn->prepare("
SELECT * FROM workers
WHERE department_id=? AND status='Available'
");

$stmt->bind_param("i",$dept_id);
$stmt->execute();
$workers = $stmt->get_result();


$nearest_worker = null;
$min_distance = 999999;


/* HAVERSINE DISTANCE */

function distance($lat1,$lon1,$lat2,$lon2){

$earth = 6371;

$dLat = deg2rad($lat2-$lat1);
$dLon = deg2rad($lon2-$lon1);

$a =
sin($dLat/2)*sin($dLat/2) +
cos(deg2rad($lat1))*cos(deg2rad($lat2)) *
sin($dLon/2)*sin($dLon/2);

$c = 2 * atan2(sqrt($a),sqrt(1-$a));

return $earth*$c;

}


/* FIND NEAREST WORKER */

while($w=$workers->fetch_assoc()){

if(!$w['latitude'] || !$w['longitude']) continue;

$dist = distance(
$issue_lat,
$issue_lon,
$w['latitude'],
$w['longitude']
);

if($dist < $min_distance){

$min_distance = $dist;
$nearest_worker = $w;

}

}


/* ASSIGN WORKER */

if($nearest_worker){

$worker_id = $nearest_worker['worker_id'];


/* INSERT ASSIGNMENT */

$stmt = $conn->prepare("
INSERT INTO work_assignments
(issue_id,worker_id,assigned_by,status)
VALUES(?,?,?,'Assigned')
");

$stmt->bind_param("iii",$issue_id,$worker_id,$admin_id);
$stmt->execute();


/* UPDATE WORKER */

$stmt = $conn->prepare("
UPDATE workers SET status='Busy' WHERE worker_id=?
");

$stmt->bind_param("i",$worker_id);
$stmt->execute();


/* UPDATE ISSUE */

$stmt = $conn->prepare("
UPDATE issues SET status='In Progress' WHERE id=?
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();


echo "<script>
alert('Nearest worker assigned successfully');
window.location='../view_issue.php?id=$issue_id';
</script>";

}else{

echo "<script>
alert('No available worker found');
window.location='../view_issue.php?id=$issue_id';
</script>";

}
?>