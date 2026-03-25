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
if(!$stmt){ die("SQL Error: ".$conn->error); }

$stmt->bind_param("i",$admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$dept_id = $admin['dept_id'];


/* ADD WORKER */

if(isset($_POST['add_worker'])){

$name = $_POST['name'];
$phone = $_POST['phone'];

$stmt = $conn->prepare("
INSERT INTO workers(name,phone,department_id,status)
VALUES(?,?,?,'Available')
");

if(!$stmt){ die("SQL Error: ".$conn->error); }

$stmt->bind_param("ssi",$name,$phone,$dept_id);
$stmt->execute();

header("Location: workers.php");
exit;
}


/* TOGGLE STATUS */

if(isset($_GET['toggle'])){

$id = intval($_GET['toggle']);

$conn->query("
UPDATE workers
SET status = IF(status='Available','Busy','Available')
WHERE worker_id=$id
");

header("Location: workers.php");
exit;
}


/* DELETE WORKER */

if(isset($_GET['delete'])){

$id = intval($_GET['delete']);

$conn->query("DELETE FROM workers WHERE worker_id=$id");

header("Location: workers.php");
exit;
}


/* FETCH WORKERS */

$stmt = $conn->prepare("
SELECT * FROM workers
WHERE department_id=?
ORDER BY name
");

if(!$stmt){ die("SQL Error: ".$conn->error); }

$stmt->bind_param("i",$dept_id);
$stmt->execute();

$workers = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<title>Workers Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>

body{
background:#eef2f7;
font-family:Segoe UI;
}

.sidebar{
width:250px;
height:100vh;
background:#0f172a;
position:fixed;
padding:25px;
color:white;
}

.sidebar a{
display:block;
color:#cbd5f5;
padding:10px;
text-decoration:none;
border-radius:6px;
margin-bottom:5px;
}

.sidebar a:hover{
background:#1e293b;
color:white;
}

.main{
margin-left:270px;
padding:30px;
}

.card{
border:none;
border-radius:14px;
}

.status-available{
background:#16a34a;
color:white;
padding:4px 10px;
border-radius:6px;
}

.status-busy{
background:#dc2626;
color:white;
padding:4px 10px;
border-radius:6px;
}

</style>

</head>

<body>

<!-- Sidebar -->

<div class="sidebar">

<h4><i class="bi bi-building"></i> CivicPulse</h4>

<p><strong><?php echo $admin['name']; ?></strong></p>

<hr>

<a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>

<a href="workers.php"><i class="bi bi-people"></i> Workers</a>

<a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>

<a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>

</div>


<!-- MAIN CONTENT -->

<div class="main">

<div class="d-flex justify-content-between mb-4">

<h3><i class="bi bi-people"></i> Department Workers</h3>

<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorker">
<i class="bi bi-person-plus"></i> Add Worker
</button>

</div>


<div class="card shadow p-4">

<table class="table table-hover">

<thead class="table-dark">

<tr>
<th>ID</th>
<th>Name</th>
<th>Phone</th>
<th>Status</th>
<th>Actions</th>
</tr>

</thead>

<tbody>

<?php if($workers && $workers->num_rows > 0){ ?>

<?php while($w = $workers->fetch_assoc()){ ?>

<tr>

<td><?php echo $w['worker_id']; ?></td>

<td><?php echo htmlspecialchars($w['name']); ?></td>

<td><?php echo htmlspecialchars($w['phone']); ?></td>

<td>

<?php
if($w['status']=="Available")
echo "<span class='status-available'>Available</span>";
else
echo "<span class='status-busy'>Busy</span>";
?>

</td>

<td>

<a href="?toggle=<?php echo $w['worker_id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-arrow-repeat"></i>

</a>

<a href="?delete=<?php echo $w['worker_id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this worker?')">

<i class="bi bi-trash"></i>

</a>

</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>
<td colspan="5" class="text-center">
No workers added yet
</td>
</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>


<!-- ADD WORKER MODAL -->

<div class="modal fade" id="addWorker">

<div class="modal-dialog">

<div class="modal-content">

<form method="POST">

<div class="modal-header">

<h5 class="modal-title">Add Worker</h5>

<button class="btn-close" data-bs-dismiss="modal"></button>

</div>

<div class="modal-body">

<div class="mb-3">

<label class="form-label">Worker Name</label>

<input type="text" name="name" class="form-control" required>

</div>

<div class="mb-3">

<label class="form-label">Phone</label>

<input type="text" name="phone" class="form-control" required>

</div>

</div>

<div class="modal-footer">

<button class="btn btn-primary" name="add_worker">
Add Worker
</button>

</div>

</form>

</div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>