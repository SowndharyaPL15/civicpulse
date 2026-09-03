<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
header("Location: adminlogin.php");
exit;
}

$admin_id = $_SESSION['admin_id'];

/* ISSUE ID VALIDATION */
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
die("Invalid issue ID");
}

$issue_id = intval($_GET['id']);

/* ============================= */
/* DISTANCE FUNCTION             */
/* ============================= */
function distance($lat1,$lon1,$lat2,$lon2){
    $earth = 6371;

    $dLat = deg2rad($lat2-$lat1);
    $dLon = deg2rad($lon2-$lon1);

    $a = sin($dLat/2)*sin($dLat/2) +
         cos(deg2rad($lat1))*cos(deg2rad($lat2)) *
         sin($dLon/2)*sin($dLon/2);

    $c = 2 * atan2(sqrt($a),sqrt(1-$a));
    return $earth*$c;
}

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

/* ============================= */
/* AUTO ASSIGN (EXPLICIT ACTION) */
/* ============================= */

$assign_msg = '';

if(isset($_GET['auto_assign']) && $_GET['auto_assign'] == '1'){

    if(strtolower($issue['status']) == "open" && empty($issue['assigned_worker_id'])){

        if($issue['latitude'] !== null && $issue['longitude'] !== null){

            $issue_lat = (float)$issue['latitude'];
            $issue_lon = (float)$issue['longitude'];
            $dept_id = (int)$issue['department_id'];

            $stmt = $conn->prepare("SELECT * FROM workers WHERE department_id=? AND status='Available'");
            $stmt->bind_param("i", $dept_id);
            $stmt->execute();
            $workers = $stmt->get_result();

            if($workers && $workers->num_rows > 0){

                $nearest_worker = null;
                $min_distance = PHP_FLOAT_MAX;

                while($w = $workers->fetch_assoc()){
                    if($w['latitude'] === null || $w['longitude'] === null) continue;

                    $dist = distance($issue_lat, $issue_lon, (float)$w['latitude'], (float)$w['longitude']);

                    if($dist < $min_distance){
                        $min_distance = $dist;
                        $nearest_worker = $w;
                    }
                }

                if($nearest_worker){
                    $wid = (int)$nearest_worker['worker_id'];

                    $check = $conn->prepare("SELECT 1 FROM work_assignments WHERE issue_id=? LIMIT 1");
                    $check->bind_param("i", $issue_id);
                    $check->execute();

                    if($check->get_result()->num_rows == 0){

                        $stmt = $conn->prepare("INSERT INTO work_assignments(issue_id,worker_id,assigned_by,status) VALUES(?,?,?,'Assigned')");
                        $stmt->bind_param("iii", $issue_id, $wid, $admin_id);
                        $stmt->execute();

                        $stmt = $conn->prepare("UPDATE issues SET status='In Progress', assigned_worker_id=? WHERE id=?");
                        $stmt->bind_param("ii", $wid, $issue_id);
                        $stmt->execute();

                        $stmt = $conn->prepare("UPDATE workers SET status='Busy' WHERE worker_id=?");
                        $stmt->bind_param("i", $wid);
                        $stmt->execute();

                        /* Record in updates timeline */
                        $msg = "Auto-assigned to worker: " . $nearest_worker['name'];
                        $stmt = $conn->prepare("INSERT INTO issue_updates (issue_id, update_message, updated_by) VALUES (?,?,?)");
                        $stmt->bind_param("isi", $issue_id, $msg, $admin_id);
                        $stmt->execute();

                        $assign_msg = "success";
                    } else {
                        $assign_msg = "already_assigned";
                    }
                } else {
                    $assign_msg = "no_worker_with_location";
                }
            } else {
                $assign_msg = "no_workers";
            }
        } else {
            $assign_msg = "no_location";
        }
    } else {
        $assign_msg = "not_open";
    }

    /* Reload issue data after assignment */
    $stmt = $conn->prepare("SELECT i.*, d.dept_name FROM issues i JOIN departments d ON i.department_id=d.dept_id WHERE i.id=?");
    $stmt->bind_param("i",$issue_id);
    $stmt->execute();
    $issue = $stmt->get_result()->fetch_assoc();
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

/* GET COMPLAINT DETAILS + IMAGES */
$stmt = $conn->prepare("
SELECT c.*, u.name AS reporter_name, u.email AS reporter_email
FROM complaints c
JOIN user u ON c.user_id = u.uid
WHERE c.issue_id=?
ORDER BY c.created_at DESC
");
$stmt->bind_param("i",$issue_id);
$stmt->execute();
$complaints = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<title>Issue Details — CivicPulse</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body{background:#eef2f7;font-family:'Inter',sans-serif;}
.container-box{max-width:1200px;margin:auto;margin-top:30px;margin-bottom:40px;}
.card{border:none;border-radius:14px;}

.priority-high{background:#dc2626;color:white;padding:4px 10px;border-radius:6px;font-size:12px}
.priority-medium{background:#f59e0b;color:white;padding:4px 10px;border-radius:6px;font-size:12px}
.priority-low{background:#16a34a;color:white;padding:4px 10px;border-radius:6px;font-size:12px}

.status-open{color:#dc2626;font-weight:600}
.status-progress{color:#f59e0b;font-weight:600}
.status-resolved{color:#16a34a;font-weight:600}

#map{height:350px;border-radius:12px;}

.evidence-img{
    max-width:200px;
    border-radius:10px;
    border:1px solid #e5e7eb;
    cursor:pointer;
    transition:0.2s;
}
.evidence-img:hover{
    transform:scale(1.05);
    box-shadow:0 5px 15px rgba(0,0,0,0.15);
}
</style>

</head>

<body>

<div class="container container-box">

<!-- HEADER -->
<div class="card shadow p-4 mb-4">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

<h4 class="m-0">
<i class="bi bi-exclamation-triangle"></i>
Issue #<?= $issue_id ?> Details
</h4>

<div class="d-flex gap-2 flex-wrap">

<?php if($issue['status'] == 'Open' && empty($issue['assigned_worker_id'])): ?>
<a href="?id=<?= $issue_id ?>&auto_assign=1" class="btn btn-warning btn-sm" title="Auto-assign nearest worker">
<i class="bi bi-lightning"></i> Auto-Assign
</a>
<?php endif; ?>

<a href="assign_work.php?id=<?= $issue_id ?>" class="btn btn-primary btn-sm">
<i class="bi bi-person-plus"></i> Manual Assign
</a>

<a href="update_status.php?id=<?= $issue_id ?>" class="btn btn-success btn-sm">
<i class="bi bi-check"></i> Update Status
</a>

<a href="dashboard.php" class="btn btn-secondary btn-sm">
<i class="bi bi-arrow-left"></i> Back
</a>
</div>

</div>

<?php if($assign_msg == 'success'): ?>
<div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle"></i> Worker auto-assigned successfully!</div>
<?php elseif($assign_msg == 'no_workers'): ?>
<div class="alert alert-warning mt-3 mb-0">No available workers found in this department.</div>
<?php elseif($assign_msg == 'no_location'): ?>
<div class="alert alert-warning mt-3 mb-0">Issue location missing. Cannot auto-assign.</div>
<?php elseif($assign_msg == 'already_assigned'): ?>
<div class="alert alert-info mt-3 mb-0">This issue already has an assignment.</div>
<?php elseif($assign_msg == 'not_open'): ?>
<div class="alert alert-info mt-3 mb-0">Only open/unassigned issues can be auto-assigned.</div>
<?php endif; ?>

<hr>

<div class="row">

<div class="col-md-6">

<p><strong>Issue Title</strong><br>
<?= htmlspecialchars($issue['issue_title']) ?>
</p>

<p><strong>Department</strong><br>
<?= htmlspecialchars($issue['dept_name']) ?>
</p>

<p><strong>Reports</strong><br>
<?= $issue['complaint_count'] ?>
</p>

<p><strong>Created At</strong><br>
<?= $issue['created_at'] ?>
</p>

<?php if($issue['resolution_notes']): ?>
<p><strong>Resolution Notes</strong><br>
<?= htmlspecialchars($issue['resolution_notes']) ?>
</p>
<?php endif; ?>

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

<?php if($issue['latitude'] && $issue['longitude']): ?>
<p><strong>Coordinates</strong><br>
<?= $issue['latitude'] ?>, <?= $issue['longitude'] ?>
</p>
<?php endif; ?>

</div>

</div>

</div>

<!-- MAP -->
<?php if($issue['latitude'] && $issue['longitude']): ?>
<div class="card shadow p-4 mb-4">
<h5><i class="bi bi-geo-alt"></i> Issue Location</h5>
<div id="map"></div>
</div>
<?php endif; ?>

<!-- COMPLAINT EVIDENCE -->
<?php if($complaints && $complaints->num_rows > 0): ?>
<div class="card shadow p-4 mb-4">

<h5><i class="bi bi-images"></i> Citizen Reports & Evidence</h5>

<div class="table-responsive">
<table class="table table-hover">
<thead class="table-light">
<tr>
<th>Reporter</th>
<th>Description</th>
<th>Evidence</th>
<th>Date</th>
</tr>
</thead>
<tbody>
<?php while($c = $complaints->fetch_assoc()): ?>
<tr>
<td>
<strong><?= htmlspecialchars($c['reporter_name']) ?></strong><br>
<small class="text-muted"><?= htmlspecialchars($c['reporter_email']) ?></small>
</td>
<td><?= htmlspecialchars($c['description']) ?></td>
<td>
<?php if($c['image']): ?>
<img src="../<?= htmlspecialchars($c['image']) ?>" class="evidence-img"
     onerror="if(!this.dataset.tried){this.dataset.tried=1;this.src='../userside/<?= htmlspecialchars($c['image']) ?>';}"
     onclick="window.open(this.src,'_blank')" alt="Evidence">
<?php else: ?>
<span class="text-muted">No image</span>
<?php endif; ?>
</td>
<td><small><?= $c['created_at'] ?></small></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
<?php endif; ?>

<!-- ASSIGNMENT HISTORY -->
<div class="card shadow p-4">

<h5><i class="bi bi-people"></i> Assignment History</h5>

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

<?php if($assignments && $assignments->num_rows > 0): ?>
<?php while($a=$assignments->fetch_assoc()): ?>

<tr>
<td><?= htmlspecialchars($a['worker_name']) ?></td>
<td><?= htmlspecialchars($a['phone']) ?></td>
<td><?= $a['assigned_at'] ?></td>
<td>
<?php
if($a['status']=='Completed') echo "<span class='badge bg-success'>Completed</span>";
elseif($a['status']=='Assigned') echo "<span class='badge bg-primary'>Assigned</span>";
else echo "<span class='badge bg-warning text-dark'>" . htmlspecialchars($a['status']) . "</span>";
?>
</td>
</tr>

<?php endwhile; ?>
<?php else: ?>

<tr>
<td colspan="4" class="text-center py-3 text-muted">No worker assigned yet</td>
</tr>

<?php endif; ?>

</tbody>
</table>

</div>

</div>

<script>
var lat = <?= (float)($issue['latitude'] ?? 0) ?>;
var lng = <?= (float)($issue['longitude'] ?? 0) ?>;

if(lat && lng){
var map = L.map('map').setView([lat,lng],16);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
attribution:'© OpenStreetMap'
}).addTo(map);

L.marker([lat,lng]).addTo(map)
 .bindPopup("<b><?= htmlspecialchars(addslashes($issue['issue_title'])) ?></b>").openPopup();
}
</script>

</body>
</html>