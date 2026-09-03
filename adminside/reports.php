<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: adminlogin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

/* ADMIN INFO */
$stmt = $conn->prepare("SELECT dept_id FROM admin WHERE admin_id=?");
$stmt->bind_param("i",$admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$department_id = $admin['dept_id'];


/* PRIORITY DATA */
$priorityData = ["LOW"=>0,"MEDIUM"=>0,"HIGH"=>0];

$q = $conn->prepare("
SELECT priority, COUNT(*) as total 
FROM issues 
WHERE department_id=? 
GROUP BY priority
");
$q->bind_param("i",$department_id);
$q->execute();
$r=$q->get_result();

while($row=$r->fetch_assoc()){
    $priorityData[$row['priority']]=$row['total'];
}


/* TREND DATA */
$dates=[];
$counts=[];

$q=$conn->prepare("
SELECT DATE(created_at) as d, COUNT(*) as total
FROM issues
WHERE department_id=?
GROUP BY DATE(created_at)
ORDER BY d
");
$q->bind_param("i",$department_id);
$q->execute();
$r=$q->get_result();

while($row=$r->fetch_assoc()){
    $dates[]=$row['d'];
    $counts[]=$row['total'];
}


/* MAP LOCATIONS */
$locations=[];

$q=$conn->prepare("
SELECT latitude, longitude
FROM issues
WHERE department_id=?
AND latitude IS NOT NULL
AND longitude IS NOT NULL
");
$q->bind_param("i",$department_id);
$q->execute();
$r=$q->get_result();

while($row=$r->fetch_assoc()){
    $locations[]=[
        "lat"=>$row['latitude'],
        "lng"=>$row['longitude']
    ];
}


/* TOP ISSUES */
$q=$conn->prepare("
SELECT issue_title, SUM(complaint_count) as total
FROM issues
WHERE department_id=?
GROUP BY issue_title
ORDER BY total DESC
LIMIT 5
");
$q->bind_param("i",$department_id);
$q->execute();
$clusters=$q->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<title>CivicPulse Reports</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- IMPORTANT: Leaflet CSS FIRST -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

<style>
body{
    background:#f5f7fb;
}

.card{
    border:none;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

#map{
    height:420px;
    border-radius:10px;
}
</style>

</head>

<body>

<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">🏙 CivicPulse Smart Analytics</h2>

    <a href="dashboard.php" class="btn btn-outline-primary">
        ← Back
    </a>
</div>

<div class="row">

<!-- PRIORITY -->
<div class="col-md-6">
<div class="card p-3">
<h5>Priority Distribution</h5>
<canvas id="priorityChart"></canvas>
</div>
</div>

<!-- TREND -->
<div class="col-md-6">
<div class="card p-3">
<h5>Issue Trend</h5>
<canvas id="trendChart"></canvas>
</div>
</div>

</div>

<br>

<div class="row">

<!-- MAP -->
<div class="col-md-7">
<div class="card p-3">
<h5>Complaint Locations</h5>
<div id="map"></div>
</div>
</div>

<!-- TOP ISSUES -->
<div class="col-md-5">
<div class="card p-3">
<h5>Top Issue Clusters</h5>

<table class="table">
<thead>
<tr>
<th>Issue</th>
<th>Total</th>
</tr>
</thead>

<tbody>
<?php while($row=$clusters->fetch_assoc()){ ?>
<tr>
<td><?= htmlspecialchars($row['issue_title']) ?></td>
<td><?= $row['total'] ?></td>
</tr>
<?php } ?>
</tbody>

</table>

</div>
</div>

</div>

</div>

<!-- JS LOAD ORDER FIXED -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>

/* ---------- PRIORITY CHART ---------- */

new Chart(document.getElementById('priorityChart'),{
type:'doughnut',
data:{
labels:['Low','Medium','High'],
datasets:[{
data:[
<?= $priorityData['LOW'] ?>,
<?= $priorityData['MEDIUM'] ?>,
<?= $priorityData['HIGH'] ?>
],
backgroundColor:['#27ae60','#f39c12','#e74c3c']
}]
}
});


/* ---------- TREND CHART ---------- */

new Chart(document.getElementById('trendChart'),{
type:'line',
data:{
labels:<?= json_encode($dates) ?>,
datasets:[{
label:'Issues',
data:<?= json_encode($counts) ?>,
borderColor:'#2980b9',
tension:0.3,
fill:false
}]
}
});


/* ---------- MAP FIX ---------- */

var map = L.map('map');

var locations = <?= json_encode($locations) ?>;

// default view (fallback India)
map.setView([11.0168, 76.9558], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);

/* add markers safely */
if(locations.length > 0){

    locations.forEach(function(loc){
        if(loc.lat && loc.lng){
            L.marker([loc.lat, loc.lng]).addTo(map);
        }
    });

    // auto focus for better demo impression
    map.setView([locations[0].lat, locations[0].lng], 12);
}

</script>

</body>
</html>