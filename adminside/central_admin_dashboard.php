<?php
session_start();
include "config.php";

/* Auth check - central admin needs to be logged in */
if(!isset($_SESSION['admin_id'])){
    header("Location: adminlogin.php");
    exit;
}

/* Fetch all issues using prepared statement */
$stmt = $conn->prepare("SELECT i.*, d.dept_name FROM issues i LEFT JOIN departments d ON i.department_id=d.dept_id ORDER BY FIELD(i.priority,'HIGH','MEDIUM','LOW')");
$stmt->execute();
$result = $stmt->get_result();

/* Fetch departments for assignment dropdown */
$dept_stmt = $conn->prepare("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
$dept_stmt->execute();
$departments = $dept_stmt->get_result();
$dept_list = [];
while($d = $departments->fetch_assoc()){
    $dept_list[] = $d;
}

/* Stats using prepared statements */
$total = $conn->query("SELECT COUNT(*) as total FROM issues")->fetch_assoc()['total'];
$open_count = $conn->query("SELECT COUNT(*) as total FROM issues WHERE status='Open'")->fetch_assoc()['total'];
$progress_count = $conn->query("SELECT COUNT(*) as total FROM issues WHERE status='In Progress'")->fetch_assoc()['total'];
$resolved_count = $conn->query("SELECT COUNT(*) as total FROM issues WHERE status='Resolved'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>

<title>CivicPulse Central Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
background:#eef2f7;
font-family:'Inter',sans-serif;
}

.navbar{
background:linear-gradient(90deg,#0f172a,#1e293b);
}

.navbar-brand{
font-weight:700;
font-size:20px;
}

.card{
border:none;
border-radius:14px;
}

.dashboard-card{
padding:20px;
text-align:center;
transition:0.2s;
}

.dashboard-card:hover{
transform:translateY(-3px);
}

.table{
background:white;
border-radius:10px;
overflow:hidden;
}

.badge{
font-size:12px;
padding:6px 10px;
}

</style>

</head>

<body>

<nav class="navbar navbar-dark px-4">
<span class="navbar-brand"><i class="bi bi-buildings"></i> CivicPulse Central Admin</span>
<a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
</nav>

<div class="container mt-4">

<div class="row g-3 mb-4">

<div class="col-6 col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-list-task fs-2 text-primary"></i>
<h6 class="mt-2">Total Issues</h6>
<h3><?= $total ?></h3>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-exclamation-circle fs-2 text-danger"></i>
<h6 class="mt-2">Open</h6>
<h3><?= $open_count ?></h3>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-tools fs-2 text-warning"></i>
<h6 class="mt-2">In Progress</h6>
<h3><?= $progress_count ?></h3>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-check-circle fs-2 text-success"></i>
<h6 class="mt-2">Resolved</h6>
<h3><?= $resolved_count ?></h3>
</div>
</div>

</div>

<div class="card shadow p-4">

<h5 class="mb-3"><i class="bi bi-bar-chart"></i> Prioritized Civic Issues</h5>

<div class="table-responsive">
<table class="table table-hover align-middle">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Issue</th>
<th>Priority</th>
<th>Reports</th>
<th>Department</th>
<th>Status</th>
<th>Assign</th>
</tr>
</thead>

<tbody>

<?php while($row = $result->fetch_assoc()): ?>

<tr>

<td><?= $row['id'] ?></td>

<td><?= htmlspecialchars($row['issue_title']) ?></td>

<td>
<?php
if($row['priority']=="HIGH")
echo "<span class='badge bg-danger'>HIGH</span>";
else if($row['priority']=="MEDIUM")
echo "<span class='badge bg-warning text-dark'>MEDIUM</span>";
else
echo "<span class='badge bg-success'>LOW</span>";
?>
</td>

<td><?= $row['complaint_count'] ?></td>

<td>
<?php
echo $row['dept_name'] ? htmlspecialchars($row['dept_name']) : "<span class='text-muted'>Not Assigned</span>";
?>
</td>

<td>
<?php
if($row['status']=="Resolved")
echo "<span class='badge bg-success'>Resolved</span>";
else if($row['status']=="In Progress")
echo "<span class='badge bg-warning text-dark'>In Progress</span>";
else
echo "<span class='badge bg-danger'>Open</span>";
?>
</td>

<td>
<form action="assign_department.php" method="POST" class="d-flex gap-1">
<input type="hidden" name="issue_id" value="<?= $row['id'] ?>">

<select name="department_id" class="form-select form-select-sm" style="min-width:120px;">
<?php foreach($dept_list as $dept): ?>
<option value="<?= $dept['dept_id'] ?>" <?= ($row['department_id']==$dept['dept_id'])?'selected':'' ?>>
<?= htmlspecialchars($dept['dept_name']) ?>
</option>
<?php endforeach; ?>
</select>

<button class="btn btn-primary btn-sm">
<i class="bi bi-send"></i>
</button>
</form>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>
</div>

</div>

</div>

</body>
</html>