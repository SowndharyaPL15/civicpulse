<?php
include "config.php";

$query = "SELECT * FROM issues 
ORDER BY FIELD(priority,'HIGH','MEDIUM','LOW')";
$result = mysqli_query($conn,$query);
?>

<!DOCTYPE html>
<html>
<head>

<title>CivicPulse Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>

body{
background:#eef2f7;
font-family:Segoe UI;
}

.navbar{
background:linear-gradient(90deg,#0f172a,#1e293b);
}

.navbar-brand{
font-weight:600;
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

.assign-btn{
width:100%;
}

</style>

</head>

<body>

<nav class="navbar navbar-dark px-4">
<span class="navbar-brand"><i class="bi bi-buildings"></i> CivicPulse Central Admin</span>
</nav>

<div class="container mt-4">

<div class="row g-3 mb-4">

<!-- Total Issues -->

<div class="col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-list-task fs-2 text-primary"></i>
<h6 class="mt-2">Total Issues</h6>

<h3>

<?php
$q="SELECT COUNT(*) as total FROM issues";
$r=mysqli_query($conn,$q);
$d=mysqli_fetch_assoc($r);
echo $d['total'];
?>

</h3>

</div>
</div>

<!-- Open -->

<div class="col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-exclamation-circle fs-2 text-danger"></i>
<h6 class="mt-2">Open</h6>

<h3>

<?php
$q="SELECT COUNT(*) as total FROM issues WHERE status='Open'";
$r=mysqli_query($conn,$q);
$d=mysqli_fetch_assoc($r);
echo $d['total'];
?>

</h3>

</div>
</div>

<!-- In Progress -->

<div class="col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-tools fs-2 text-warning"></i>
<h6 class="mt-2">In Progress</h6>

<h3>

<?php
$q="SELECT COUNT(*) as total FROM issues WHERE status='In Progress'";
$r=mysqli_query($conn,$q);
$d=mysqli_fetch_assoc($r);
echo $d['total'];
?>

</h3>

</div>
</div>

<!-- Resolved -->

<div class="col-md-3">
<div class="card shadow dashboard-card">
<i class="bi bi-check-circle fs-2 text-success"></i>
<h6 class="mt-2">Resolved</h6>

<h3>

<?php
$q="SELECT COUNT(*) as total FROM issues WHERE status='Resolved'";
$r=mysqli_query($conn,$q);
$d=mysqli_fetch_assoc($r);
echo $d['total'];
?>

</h3>

</div>
</div>

</div>

<div class="card shadow p-4">

<h5 class="mb-3"><i class="bi bi-bar-chart"></i> Prioritized Civic Issues</h5>

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

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['issue_title']; ?></td>

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

<td><?php echo $row['complaint_count']; ?></td>

<td>

<?php

if($row['department_id']==NULL)
echo "<span class='text-muted'>Not Assigned</span>";
else
echo "Dept ID: ".$row['department_id'];

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

<form action="assign_department.php" method="POST">

<input type="hidden" name="issue_id" value="<?php echo $row['id']; ?>">

<select name="department_id" class="form-select form-select-sm">

<option value="1">Road</option>
<option value="2">Sanitation</option>
<option value="3">Electricity</option>
<option value="4">Water</option>
<option value="5">Drainage</option>

</select>

<button class="btn btn-primary btn-sm mt-2 assign-btn">
Assign
</button>

</form>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</body>
</html>