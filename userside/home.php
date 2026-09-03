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

/* DASHBOARD STATS - using prepared statements */
$stmt=$conn->prepare("SELECT COUNT(*) c FROM complaints WHERE user_id=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$total=$stmt->get_result()->fetch_assoc()['c'];

$stmt=$conn->prepare("
SELECT COUNT(*) c
FROM complaints c2
JOIN issues i ON c2.issue_id=i.id
WHERE c2.user_id=? AND i.status='Open'
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$open=$stmt->get_result()->fetch_assoc()['c'];

$stmt=$conn->prepare("
SELECT COUNT(*) c
FROM complaints c2
JOIN issues i ON c2.issue_id=i.id
WHERE c2.user_id=? AND i.status='In Progress'
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$progress=$stmt->get_result()->fetch_assoc()['c'];

$stmt=$conn->prepare("
SELECT COUNT(*) c
FROM complaints c2
JOIN issues i ON c2.issue_id=i.id
WHERE c2.user_id=? AND i.status='Resolved'
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$resolved=$stmt->get_result()->fetch_assoc()['c'];

/* Pending (not yet grouped into issues) */
$stmt=$conn->prepare("SELECT COUNT(*) c FROM complaints WHERE user_id=? AND issue_id IS NULL");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$pending=$stmt->get_result()->fetch_assoc()['c'];

/* RECENT COMPLAINTS */
$stmt=$conn->prepare("
SELECT c.*,d.dept_name,it.issue_name,i.status AS issue_status, i.priority
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

/* MAP DATA - with confirm counts and user confirmation status */
$stmt=$conn->prepare("
SELECT c.*, d.dept_name, it.issue_name, i.status AS issue_status, i.priority,
    (SELECT COUNT(*) FROM issue_confirmations ic WHERE ic.issue_id=c.issue_id) AS confirm_count,
    (SELECT COUNT(*) FROM issue_confirmations ic2 WHERE ic2.issue_id=c.issue_id AND ic2.user_id=?) AS already_confirmed
FROM complaints c
JOIN departments d ON c.department_id=d.dept_id
JOIN issue_types it ON c.issue_type_id=it.type_id
LEFT JOIN issues i ON c.issue_id=i.id
WHERE c.issue_id IS NOT NULL
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res=$stmt->get_result();

$map=[];
while($r=$res->fetch_assoc()){
$map[]=$r;
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CivicPulse Dashboard</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
font-family:'Inter',sans-serif;
background:#f5f7fb;
}

/* NAVBAR */
.navbar{
border-bottom:1px solid #e5e7eb;
}

/* SIDEBAR */
.sidebar{
height:100vh;
background:#111827;
color:white;
position:fixed;
width:230px;
padding-top:20px;
z-index:100;
transition:0.3s;
}

.sidebar h5{
color:#9ca3af;
}

.sidebar a{
display:flex;
align-items:center;
gap:12px;
padding:12px 20px;
color:#d1d5db;
text-decoration:none;
border-radius:8px;
margin:5px 10px;
transition:0.25s;
font-size:14px;
}

.sidebar a i{
font-size:18px;
}

.sidebar a:hover{
background:#1f2937;
color:white;
transform:translateX(5px);
}

.sidebar a.active{
background:#2563eb;
color:white;
}

/* MAIN */
.main{
margin-left:230px;
padding:30px;
}

/* CARDS */
.stat-card{
border:none;
border-radius:16px;
box-shadow:0 10px 25px rgba(0,0,0,0.06);
transition:0.3s;
}

.stat-card:hover{
transform:translateY(-4px);
}

.stat-card h5{
color:#6b7280;
font-size:14px;
}

.stat-card h2{
font-weight:700;
}

/* BUTTONS */
.btn-main{
background:#2563eb;
color:#fff;
border:none;
border-radius:10px;
padding:10px 16px;
font-weight:500;
margin-right:10px;
transition:0.25s;
}

.btn-main:hover{
background:#1e40af;
transform:translateY(-1px);
}

/* TABLE */
.table-card{
border-radius:16px;
box-shadow:0 10px 25px rgba(0,0,0,0.06);
overflow:hidden;
}

/* MAP */
.map-card{
border-radius:16px;
box-shadow:0 10px 25px rgba(0,0,0,0.06);
}

#map{
height:420px;
}

/* BADGES */
.badge-high{background:#ef4444;}
.badge-medium{background:#f59e0b;}
.badge-low{background:#22c55e;}

/* Mobile */
.sidebar-toggle{
display:none;position:fixed;top:15px;left:15px;z-index:200;
background:#111827;color:white;border:none;border-radius:8px;padding:8px 12px;font-size:20px;
}

@media(max-width:768px){
.sidebar{transform:translateX(-100%);}
.sidebar.show{transform:translateX(0);}
.main{margin-left:0;padding:15px;padding-top:60px;}
.sidebar-toggle{display:block;}
}

</style>

</head>

<body>

<!-- MOBILE TOGGLE -->
<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
<i class="bi bi-list"></i>
</button>

<!-- NAVBAR -->
<nav class="navbar navbar-light bg-white shadow-sm px-4">
<span class="navbar-brand fw-bold text-primary">CivicPulse</span>
<div class="fw-semibold">
<i class="bi bi-person-circle"></i>
<?php echo htmlspecialchars($user['name']); ?>
</div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar">

<h5 class="text-center mb-4">Menu</h5>

<a href="home.php" class="active">
<i class="bi bi-speedometer2"></i> Dashboard
</a>

<a href="complaint.php">
<i class="bi bi-pencil-square"></i> Submit Complaint
</a>

<a href="track.php">
<i class="bi bi-search"></i> Track Complaints
</a>

<a href="profile.php">
<i class="bi bi-person"></i> Profile
</a>

<a href="about.php">
<i class="bi bi-info-circle"></i> About
</a>

<a href="logout.php">
<i class="bi bi-box-arrow-right"></i> Logout
</a>

</div>

<!-- MAIN CONTENT -->
<div class="main">

<h3 class="mb-4">Citizen Dashboard</h3>

<!-- STATS -->
<div class="row g-3 mb-4">

<div class="col-6 col-lg-3">
<div class="card stat-card text-center p-3">
<h5>Total Complaints</h5>
<h2><?php echo $total; ?></h2>
</div>
</div>

<div class="col-6 col-lg-3">
<div class="card stat-card text-center p-3">
<h5>Open</h5>
<h2 class="text-danger"><?php echo $open; ?></h2>
</div>
</div>

<div class="col-6 col-lg-3">
<div class="card stat-card text-center p-3">
<h5>In Progress</h5>
<h2 class="text-warning"><?php echo $progress; ?></h2>
</div>
</div>

<div class="col-6 col-lg-3">
<div class="card stat-card text-center p-3">
<h5>Resolved</h5>
<h2 class="text-success"><?php echo $resolved; ?></h2>
</div>
</div>

</div>

<!-- RECENT COMPLAINTS -->
<div class="card table-card mb-4">

<div class="card-header bg-white fw-semibold">
<i class="bi bi-clock-history"></i> Recent Complaints
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

<?php if($complaints->num_rows > 0): ?>
<?php while($row=$complaints->fetch_assoc()): ?>

<tr>

<td><?php echo htmlspecialchars($row['dept_name']); ?></td>
<td><?php echo htmlspecialchars($row['issue_name']); ?></td>

<td>
<?php
$p = $row['priority'] ?? 'LOW';
if($p=="HIGH")
echo "<span class='badge badge-high'>HIGH</span>";
elseif($p=="MEDIUM")
echo "<span class='badge badge-medium'>MEDIUM</span>";
else
echo "<span class='badge badge-low'>LOW</span>";
?>
</td>

<td>
<?php
$status=$row['issue_status'] ?? "Pending";

if($status=="Resolved")
echo "<span class='badge bg-success'>$status</span>";
elseif($status=="In Progress")
echo "<span class='badge bg-warning text-dark'>$status</span>";
else
echo "<span class='badge bg-secondary'>$status</span>";
?>
</td>

<td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>

</tr>

<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="5" class="text-center py-4 text-muted">
<i class="bi bi-inbox" style="font-size:24px"></i><br>
No complaints submitted yet. <a href="complaint.php">Submit one now</a>
</td>
</tr>
<?php endif; ?>

</tbody>

</table>

</div>

</div>

<!-- MAP -->
<div class="card map-card">

<div class="card-header bg-white fw-semibold">
<i class="bi bi-geo-alt"></i> Complaint Map
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

marker.bindPopup(`
<div style="min-width:220px">

<b>${c.dept_name} - ${c.issue_name}</b><br>
${c.description}<br><br>

<b>Priority:</b> ${c.priority || 'LOW'}<br>
<b>Status:</b> ${c.issue_status ?? "Pending"}<br><br>

<div id="count-${c.issue_id}" style="font-size:13px;">
👥 ${c.confirm_count || 0} people confirmed
</div>

<div id="btn-${c.issue_id}">
${
c.already_confirmed > 0
? `<span style="color:green;font-weight:600;">✔ Confirmed</span>`
: `<button onclick="confirmIssue(${c.issue_id})"
    style="margin-top:8px;background:#2563eb;color:white;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;">
    👍 I also face this
    </button>`
}
</div>

</div>
`);

cluster.addLayer(marker);

});

map.addLayer(cluster);

function confirmIssue(issueId){

    fetch("confirm_issue.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "issue_id=" + issueId
    })
    .then(res => res.json())
    .then(data => {

        if(data.status === "success"){

            document.getElementById("count-"+issueId).innerHTML =
                "👥 " + data.count + " people confirmed";

            document.getElementById("btn-"+issueId).innerHTML =
                "<span style='color:green;font-weight:600;'>✔ Confirmed</span>";

        } else {
            alert(data.message);
        }

    })
    .catch(err => {
        console.error(err);
        alert("Something went wrong");
    });
}

</script>

</body>
</html>