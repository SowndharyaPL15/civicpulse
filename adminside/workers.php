<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
header("Location: adminlogin.php");
exit;
}

$admin_id = $_SESSION['admin_id'];

/* GET ADMIN INFO */
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id=?");
$stmt->bind_param("i",$admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$dept_id = $admin['dept_id'];

/* ADD WORKER */
if(isset($_POST['add_worker'])){

$name = trim($_POST['name']);
$phone = trim($_POST['phone']);

/* FIX: SAFE NULL HANDLING */
$lat = ($_POST['latitude'] !== '' && isset($_POST['latitude']))
        ? floatval($_POST['latitude'])
        : NULL;

$lng = ($_POST['longitude'] !== '' && isset($_POST['longitude']))
        ? floatval($_POST['longitude'])
        : NULL;

/* Validate inputs */
if(empty($name) || empty($phone)){
    $error = "Name and phone are required.";
} else {
    /* INSERT with prepared statement */
    $stmt = $conn->prepare("
    INSERT INTO workers(name,phone,department_id,status,latitude,longitude)
    VALUES(?,?,?,'Available',?,?)
    ");

    if(!$stmt){
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssidd", $name, $phone, $dept_id, $lat, $lng);

    if(!$stmt->execute()){
        die("Execute failed: " . $stmt->error);
    }

    header("Location: workers.php");
    exit;
}
}

/* TOGGLE STATUS - using prepared statement */
if(isset($_GET['toggle'])){
$id = intval($_GET['toggle']);

$stmt = $conn->prepare("
UPDATE workers
SET status = IF(status='Available','Busy','Available')
WHERE worker_id=? AND department_id=?
");
$stmt->bind_param("ii", $id, $dept_id);
$stmt->execute();

header("Location: workers.php");
exit;
}

/* DELETE WORKER - using prepared statement */
if(isset($_GET['delete'])){
$id = intval($_GET['delete']);

$stmt = $conn->prepare("DELETE FROM workers WHERE worker_id=? AND department_id=?");
$stmt->bind_param("ii", $id, $dept_id);
$stmt->execute();

header("Location: workers.php");
exit;
}

/* FETCH WORKERS */
$stmt = $conn->prepare("
SELECT * FROM workers
WHERE department_id=?
ORDER BY name
");

$stmt->bind_param("i",$dept_id);
$stmt->execute();
$workers = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<title>Workers Management — CivicPulse</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body{background:#eef2f7;font-family:'Inter',sans-serif;}

.sidebar{width:250px;height:100vh;background:#0f172a;position:fixed;padding:25px;color:white;z-index:100;transition:0.3s;}
.sidebar a{display:flex;align-items:center;gap:10px;color:#cbd5f5;padding:10px 14px;text-decoration:none;border-radius:6px;margin-bottom:5px;font-size:14px;transition:0.2s;}
.sidebar a:hover,.sidebar a.active{background:#1e293b;color:white;}
.main{margin-left:270px;padding:30px;}
.card{border:none;border-radius:14px;}
.status-available{background:#16a34a;color:white;padding:4px 10px;border-radius:6px;font-size:12px;}
.status-busy{background:#dc2626;color:white;padding:4px 10px;border-radius:6px;font-size:12px;}
.badge-loc{background:#2563eb;color:white;padding:3px 8px;border-radius:6px;font-size:12px;}
.badge-noloc{background:#6b7280;color:white;padding:3px 8px;border-radius:6px;font-size:12px;}

.brand-text{
background:linear-gradient(90deg,#60a5fa,#34d399);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
font-weight:700;
}

.sidebar-toggle{
display:none;position:fixed;top:15px;left:15px;z-index:200;
background:#0f172a;color:white;border:none;border-radius:8px;padding:8px 12px;font-size:20px;
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

<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
<i class="bi bi-list"></i>
</button>

<div class="sidebar">
<h4><span class="brand-text">CivicPulse</span></h4>
<p><strong><?php echo htmlspecialchars($admin['name']); ?></strong></p>
<hr>
<a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a href="workers.php" class="active"><i class="bi bi-people"></i> Workers</a>
<a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
<a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main">

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
<h3 class="m-0">Department Workers</h3>

<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorker">
<i class="bi bi-plus-lg"></i> Add Worker
</button>
</div>

<?php if(isset($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow p-4">

<div class="table-responsive">
<table class="table table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Name</th>
<th>Phone</th>
<th>Location</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if($workers->num_rows > 0): ?>
<?php while($w = $workers->fetch_assoc()): ?>

<tr>
<td><?= $w['worker_id'] ?></td>
<td><?= htmlspecialchars($w['name']) ?></td>
<td><?= htmlspecialchars($w['phone']) ?></td>

<td>
<?php if($w['latitude'] && $w['longitude']){ ?>
<span class="badge-loc">📍 Set</span>
<?php } else { ?>
<span class="badge-noloc">Not Set</span>
<?php } ?>
</td>

<td>
<?php
echo $w['status']=="Available"
? "<span class='status-available'>Available</span>"
: "<span class='status-busy'>Busy</span>";
?>
</td>

<td>
<a href="?toggle=<?= $w['worker_id'] ?>" class="btn btn-warning btn-sm" title="Toggle Status">
<i class="bi bi-arrow-repeat"></i>
</a>
<a href="?delete=<?= $w['worker_id'] ?>" class="btn btn-danger btn-sm" title="Delete"
   onclick="return confirm('Delete this worker?')">
<i class="bi bi-trash"></i>
</a>
</td>

</tr>

<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="6" class="text-center py-4 text-muted">
<i class="bi bi-people" style="font-size:24px"></i><br>
No workers added yet
</td>
</tr>
<?php endif; ?>

</tbody>

</table>
</div>

</div>
</div>

<!-- ADD WORKER MODAL -->
<div class="modal fade" id="addWorker">
<div class="modal-dialog">
<div class="modal-content" style="border-radius:16px;overflow:hidden;">

<form method="POST">

<div class="modal-header" style="background:#0f172a;color:white;">
<h5 class="modal-title"><i class="bi bi-person-plus"></i> Add Worker</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<div class="mb-3">
<label class="form-label">Name</label>
<input type="text" name="name" class="form-control" placeholder="Worker name" required>
</div>

<div class="mb-3">
<label class="form-label">Phone</label>
<input type="text" name="phone" class="form-control" placeholder="Phone number" required>
</div>

<button type="button" class="btn btn-outline-primary w-100 mb-3" onclick="getLocation()">
📍 Get Current Location
</button>

<div class="row">
<div class="col-6">
<input type="text" id="lat" name="latitude" class="form-control" placeholder="Latitude" readonly>
</div>
<div class="col-6">
<input type="text" id="lng" name="longitude" class="form-control" placeholder="Longitude" readonly>
</div>
</div>

</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button class="btn btn-primary" name="add_worker"><i class="bi bi-check-lg"></i> Save Worker</button>
</div>

</form>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function getLocation(){
    if(navigator.geolocation){
        navigator.geolocation.getCurrentPosition(pos=>{
            document.getElementById('lat').value = pos.coords.latitude;
            document.getElementById('lng').value = pos.coords.longitude;
        }, err=>{
            alert("Could not get location: " + err.message);
        });
    } else {
        alert("Geolocation not supported by your browser");
    }
}
</script>

</body>
</html>