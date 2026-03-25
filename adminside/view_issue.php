<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
header("Location: adminlogin.php");
exit;
}

/* ISSUE ID VALIDATION */

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
die("Invalid issue ID");
}

$issue_id = $_GET['id'];


/* GET ISSUE DETAILS */

$stmt = $conn->prepare("
SELECT i.*, d.dept_name
FROM issues i
JOIN departments d ON i.department_id=d.dept_id
WHERE i.id=?
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();
$issue = $stmt->get_result()->fetch_assoc();

if(!$issue){
die("Issue not found");
}


/* GET ASSIGNMENT HISTORY */

$stmt = $conn->prepare("
SELECT wa.*, w.name AS worker_name, w.phone
FROM work_assignments wa
JOIN workers w ON wa.worker_id = w.worker_id
WHERE wa.issue_id=?
ORDER BY wa.assigned_at DESC
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();
$assignments = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<title>Issue Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<style>

body{
background:#eef2f7;
font-family:Segoe UI;
}

.container-box{
max-width:1200px;
margin:auto;
margin-top:40px;
}

.card{
border:none;
border-radius:14px;
}

.priority-high{background:#dc2626;color:white;padding:4px 10px;border-radius:6px}
.priority-medium{background:#f59e0b;color:white;padding:4px 10px;border-radius:6px}
.priority-low{background:#16a34a;color:white;padding:4px 10px;border-radius:6px}

.status-open{color:#dc2626;font-weight:600}
.status-progress{color:#f59e0b;font-weight:600}
.status-resolved{color:#16a34a;font-weight:600}

#map{
height:420px;
border-radius:12px;
}

</style>

</head>

<body>

<div class="container container-box">

<!-- Issue Header -->

<div class="card shadow p-4 mb-4">

<div class="d-flex justify-content-between align-items-center">

<h4>
<i class="bi bi-exclamation-triangle"></i>
Issue Details
</h4>

<div>

<a href="dashboard.php" class="btn btn-secondary me-2">
<i class="bi bi-arrow-left"></i> Back
</a>

<?php if($issue['status'] != "Resolved"){ ?>

<a href="smart_assign.php?issue_id=<?php echo $issue['id']; ?>"
class="btn btn-success">

<i class="bi bi-person-check"></i>
Auto Assign Worker

</a>

<?php } ?>

</div>

</div>

<hr>

<div class="row">

<div class="col-md-6">

<p><strong>Issue Title</strong><br>
<?php echo htmlspecialchars($issue['issue_title']); ?>
</p>

<p><strong>Department</strong><br>
<?php echo htmlspecialchars($issue['dept_name']); ?>
</p>

<p><strong>Reports</strong><br>
<?php echo $issue['complaint_count']; ?>
</p>

<p><strong>Created At</strong><br>
<?php echo $issue['created_at']; ?>
</p>

</div>

<div class="col-md-6">

<p><strong>Priority</strong><br>

<?php
if($issue['priority']=="HIGH")
echo "<span class='priority-high'>HIGH</span>";
elseif($issue['priority']=="MEDIUM")
echo "<span class='priority-medium'>MEDIUM</span>";
else
echo "<span class='priority-low'>LOW</span>";
?>

</p>

<p><strong>Status</strong><br>

<?php
if($issue['status']=="Open")
echo "<span class='status-open'>Open</span>";
elseif($issue['status']=="In Progress")
echo "<span class='status-progress'>In Progress</span>";
else
echo "<span class='status-resolved'>Resolved</span>";
?>

</p>

<p><strong>Resolution Notes</strong><br>

<?php
if(!empty($issue['resolution_notes']))
echo htmlspecialchars($issue['resolution_notes']);
else
echo "<span class='text-muted'>No resolution notes yet</span>";
?>

</p>

</div>

</div>

</div>


<!-- MAP -->

<div class="card shadow p-4 mb-4">

<h5 class="mb-3">
<i class="bi bi-geo-alt"></i>
Issue Location
</h5>

<div id="map"></div>

<?php if($issue['latitude'] && $issue['longitude']){ ?>

<p class="mt-3 text-muted">
Coordinates: <?php echo $issue['latitude']; ?> , <?php echo $issue['longitude']; ?>
</p>

<a class="btn btn-primary"
href="https://www.google.com/maps?q=<?php echo $issue['latitude']; ?>,<?php echo $issue['longitude']; ?>"
target="_blank">

<i class="bi bi-map"></i>
Open in Google Maps

</a>

<?php } else { ?>

<p class="text-muted">Location not available</p>

<?php } ?>

</div>


<!-- ASSIGNMENT HISTORY -->

<div class="card shadow p-4">

<h5 class="mb-3">
<i class="bi bi-clock-history"></i>
Assignment History
</h5>

<table class="table table-hover">

<thead class="table-dark">
<tr>
<th>Worker</th>
<th>Phone</th>
<th>Assigned At</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php if($assignments && $assignments->num_rows > 0){ ?>

<?php while($a=$assignments->fetch_assoc()){ ?>

<tr>

<td><?php echo htmlspecialchars($a['worker_name']); ?></td>
<td><?php echo htmlspecialchars($a['phone']); ?></td>
<td><?php echo $a['assigned_at']; ?></td>
<td><?php echo $a['status']; ?></td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>
<td colspan="4" class="text-center">No worker assigned yet</td>
</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

<script>

var lat = <?php echo $issue['latitude'] ?? 0; ?>;
var lng = <?php echo $issue['longitude'] ?? 0; ?>;

if(lat && lng){

var map = L.map('map').setView([lat,lng],16);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
attribution:'© OpenStreetMap'
}).addTo(map);

var marker = L.marker([lat,lng]).addTo(map);

marker.bindPopup("<b><?php echo addslashes($issue['issue_title']); ?></b><br>Civic Issue Location").openPopup();

}else{

document.getElementById("map").innerHTML =
"<div style='padding:120px;text-align:center;color:#888'>Location not available</div>";

}

</script>

</body>
</html>