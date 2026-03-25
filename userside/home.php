<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id'])){
header("Location: login.php");
exit;
}

$user_id=$_SESSION['user_id'];

/* USER INFO */

$stmt=$conn->prepare("SELECT name,email FROM user WHERE uid=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$user=$stmt->get_result()->fetch_assoc();

/* DASHBOARD STATS */

$total=$conn->query("SELECT COUNT(*) c FROM complaints WHERE user_id=$user_id")->fetch_assoc()['c'];

$open=$conn->query("
SELECT COUNT(*) c
FROM complaints c
JOIN issues i ON c.issue_id=i.id
WHERE c.user_id=$user_id AND i.status='Open'
")->fetch_assoc()['c'];

$progress=$conn->query("
SELECT COUNT(*) c
FROM complaints c
JOIN issues i ON c.issue_id=i.id
WHERE c.user_id=$user_id AND i.status='In Progress'
")->fetch_assoc()['c'];

$resolved=$conn->query("
SELECT COUNT(*) c
FROM complaints c
JOIN issues i ON c.issue_id=i.id
WHERE c.user_id=$user_id AND i.status='Resolved'
")->fetch_assoc()['c'];

/* RECENT COMPLAINTS */

$stmt=$conn->prepare("
SELECT c.*,d.dept_name,it.issue_name,i.status
FROM complaints c
JOIN departments d ON c.department_id=d.dept_id
JOIN issue_types it ON c.issue_type_id=it.type_id
LEFT JOIN issues i ON c.issue_id=i.id
WHERE c.user_id=?
ORDER BY c.created_at DESC
LIMIT 5
");

$stmt->bind_param("i",$user_id);
$stmt->execute();
$complaints=$stmt->get_result();

/* MAP DATA */

$res=$conn->query("
SELECT c.*,d.dept_name,it.issue_name,i.status
FROM complaints c
JOIN departments d ON c.department_id=d.dept_id
JOIN issue_types it ON c.issue_type_id=it.type_id
LEFT JOIN issues i ON c.issue_id=i.id
");

$map=[];
while($r=$res->fetch_assoc()){
$map[]=$r;
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>CivicPulse Dashboard</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
font-family:Poppins;
background:#f1f5f9;
}

/* SIDEBAR */

.sidebar{
height:100vh;
background:#0f172a;
color:white;
position:fixed;
width:230px;
padding-top:20px;
}

.sidebar a{
display:block;
padding:12px 20px;
color:#cbd5f5;
text-decoration:none;
}

.sidebar a:hover{
background:#1e293b;
color:white;
}

/* MAIN AREA */

.main{
margin-left:230px;
padding:30px;
}

/* CARDS */

.stat-card{
border:none;
border-radius:12px;
box-shadow:0 6px 18px rgba(0,0,0,0.08);
}

.stat-card h5{
color:#64748b;
}

.stat-card h2{
font-weight:700;
}

/* TABLE */

.table-card{
border-radius:12px;
box-shadow:0 6px 18px rgba(0,0,0,0.08);
overflow:hidden;
}

/* MAP */

.map-card{
border-radius:12px;
box-shadow:0 6px 18px rgba(0,0,0,0.08);
}

#map{
height:420px;
}

/* BADGES */

.badge-high{background:#ef4444;}
.badge-medium{background:#f59e0b;}
.badge-low{background:#22c55e;}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar navbar-light bg-white shadow-sm px-4">

<span class="navbar-brand fw-bold text-primary">CivicPulse</span>

<div class="fw-semibold">
<?php echo htmlspecialchars($user['name']); ?>
</div>

</nav>

<!-- SIDEBAR -->

<div class="sidebar">

<h5 class="text-center mb-4">Menu</h5>

<a href="home.php">Dashboard</a>
<a href="complaint.php">Submit Complaint</a>
<a href="track.php">Track Complaints</a>
<a href="profile.php">Profile</a>
<a href="logout.php">Logout</a>

</div>

<!-- MAIN CONTENT -->

<div class="main">

<h3 class="mb-4">Citizen Dashboard</h3>

<!-- STATS -->

<div class="row g-4 mb-4">

<div class="col-md-3">
<div class="card stat-card text-center p-3">
<h5>Total Complaints</h5>
<h2><?php echo $total; ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="card stat-card text-center p-3">
<h5>Open</h5>
<h2 class="text-danger"><?php echo $open; ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="card stat-card text-center p-3">
<h5>In Progress</h5>
<h2 class="text-warning"><?php echo $progress; ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="card stat-card text-center p-3">
<h5>Resolved</h5>
<h2 class="text-success"><?php echo $resolved; ?></h2>
</div>
</div>

</div>

<!-- ACTION BUTTONS -->

<div class="mb-4">

<a class="btn btn-primary me-2" href="complaint.php">Submit Complaint</a>
<a class="btn btn-outline-primary" href="track.php">Track Complaints</a>

</div>

<!-- RECENT COMPLAINTS -->

<div class="card table-card mb-4">

<div class="card-header bg-white fw-semibold">
Recent Complaints
</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead class="table-light">

<tr>
<th>Department</th>
<th>Issue</th>
<th>Priority</th>
<th>Status</th>
<th>Date</th>
</tr>

</thead>

<tbody>

<?php while($row=$complaints->fetch_assoc()): ?>

<tr>

<td><?php echo $row['dept_name']; ?></td>
<td><?php echo $row['issue_name']; ?></td>

<td>

<?php
if($row['priority']=="HIGH")
echo "<span class='badge badge-high'>HIGH</span>";
elseif($row['priority']=="MEDIUM")
echo "<span class='badge badge-medium'>MEDIUM</span>";
else
echo "<span class='badge badge-low'>LOW</span>";
?>

</td>

<td>

<?php
$status=$row['status'] ?? "Pending";

if($status=="Resolved")
echo "<span class='badge bg-success'>$status</span>";

elseif($status=="In Progress")
echo "<span class='badge bg-warning text-dark'>$status</span>";

else
echo "<span class='badge bg-secondary'>$status</span>";
?>

</td>

<td><?php echo $row['created_at']; ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- MAP -->

<div class="card map-card">

<div class="card-header bg-white fw-semibold">
Complaint Map
</div>

<div class="card-body p-0">
<div id="map"></div>
</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<script>

const map=L.map('map').setView([20.5937,78.9629],5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

const cluster=L.markerClusterGroup();

const complaints=<?php echo json_encode($map); ?>;

complaints.forEach(c=>{

if(!c.latitude || !c.longitude) return;

let color="green";

if(c.priority==="HIGH") color="red";
if(c.priority==="MEDIUM") color="orange";

const marker=L.circleMarker([c.latitude,c.longitude],{
radius:8,
color:color
});

marker.bindPopup(
"<b>"+c.dept_name+" - "+c.issue_name+"</b><br>"+
c.description+"<br>"+
"Priority:"+c.priority+"<br>"+
"Status:"+ (c.status ?? "Pending")
);

cluster.addLayer(marker);

});

map.addLayer(cluster);

</script>

</body>
</html>