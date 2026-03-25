<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: adminlogin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get admin + department
$stmt = $conn->prepare("SELECT a.*, d.dept_name FROM admin a JOIN departments d ON a.dept_id=d.dept_id WHERE a.admin_id=?");
$stmt->bind_param("i",$admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_dept = $admin['dept_id'];

// Stats
$totalIssues = $conn->query("SELECT COUNT(*) as c FROM issues WHERE department_id=$admin_dept")->fetch_assoc()['c'];
$openIssues = $conn->query("SELECT COUNT(*) as c FROM issues WHERE department_id=$admin_dept AND status='Open'")->fetch_assoc()['c'];
$progressIssues = $conn->query("SELECT COUNT(*) as c FROM issues WHERE department_id=$admin_dept AND status='In Progress'")->fetch_assoc()['c'];
$resolvedIssues = $conn->query("SELECT COUNT(*) as c FROM issues WHERE department_id=$admin_dept AND status='Resolved'")->fetch_assoc()['c'];

// Filter
$filter = $_GET['filter'] ?? 'all';
$where = "";

if($filter=='open') $where="AND i.status='Open'";
if($filter=='progress') $where="AND i.status='In Progress'";
if($filter=='resolved') $where="AND i.status='Resolved'";

// Issues Query
$query="SELECT i.*,d.dept_name FROM issues i
JOIN departments d ON i.department_id=d.dept_id
WHERE i.department_id=$admin_dept $where
ORDER BY FIELD(i.priority,'HIGH','MEDIUM','LOW'),i.complaint_count DESC";

$issues=$conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
<title>CivicPulse Department Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>

body{
background:#f1f5f9;
font-family:Segoe UI;
}

.sidebar{
width:250px;
height:100vh;
position:fixed;
background:#0f172a;
padding:25px;
color:white;
}

.sidebar h4{margin-bottom:25px;}

.sidebar a{
display:block;
padding:10px;
color:#cbd5f5;
text-decoration:none;
border-radius:8px;
margin-bottom:8px;
}

.sidebar a:hover{background:#1e293b;color:white;}

.main{
margin-left:270px;
padding:30px;
}

.card{
border:none;
border-radius:14px;
}

.stat{
padding:20px;
text-align:center;
}

.stat i{font-size:28px;margin-bottom:10px;}

.priority-high{background:#dc2626;color:white;padding:5px 10px;border-radius:6px;font-size:12px}
.priority-medium{background:#f59e0b;color:white;padding:5px 10px;border-radius:6px;font-size:12px}
.priority-low{background:#16a34a;color:white;padding:5px 10px;border-radius:6px;font-size:12px}

.status-open{color:#dc2626;font-weight:600}
.status-progress{color:#f59e0b;font-weight:600}
.status-resolved{color:#16a34a;font-weight:600}

.high-row{background:#fee2e2}

.table{background:white;border-radius:10px;overflow:hidden}

</style>
</head>

<body>

<div class="sidebar">

<h4><i class="bi bi-building"></i> CivicPulse</h4>

<p><strong><?php echo $admin['name']; ?></strong></p>
<p style="font-size:13px">Department: <?php echo $admin['dept_name']; ?></p>

<hr>

<a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a href="workers.php"><i class="bi bi-people"></i> Workers</a>
<a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
<a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>

</div>

<div class="main">

<h2 class="mb-4">Department Operations Dashboard</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card shadow stat">
<i class="bi bi-list-task text-primary"></i>
<h6>Total Issues</h6>
<h4><?php echo $totalIssues ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="card shadow stat">
<i class="bi bi-exclamation-circle text-danger"></i>
<h6>Open</h6>
<h4><?php echo $openIssues ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="card shadow stat">
<i class="bi bi-tools text-warning"></i>
<h6>In Progress</h6>
<h4><?php echo $progressIssues ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="card shadow stat">
<i class="bi bi-check-circle text-success"></i>
<h6>Resolved</h6>
<h4><?php echo $resolvedIssues ?></h4>
</div>
</div>

</div>

<div class="card shadow p-4">

<div class="d-flex justify-content-between mb-3">

<h5><i class="bi bi-exclamation-triangle"></i> Civic Issues</h5>

<div>
<a href="?filter=all" class="btn btn-sm btn-secondary">All</a>
<a href="?filter=open" class="btn btn-sm btn-danger">Open</a>
<a href="?filter=progress" class="btn btn-sm btn-warning">In Progress</a>
<a href="?filter=resolved" class="btn btn-sm btn-success">Resolved</a>
</div>

</div>

<table class="table table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Issue</th>
<th>Reports</th>
<th>Priority</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if($issues->num_rows>0){ while($row=$issues->fetch_assoc()){ ?>

<tr class="<?php if($row['priority']=='HIGH') echo 'high-row'; ?>">

<td><?php echo $row['id'] ?></td>

<td><?php echo $row['issue_title'] ?></td>

<td><?php echo $row['complaint_count'] ?></td>

<td>
<?php
if($row['priority']=='HIGH') echo "<span class='priority-high'>HIGH</span>";
elseif($row['priority']=='MEDIUM') echo "<span class='priority-medium'>MEDIUM</span>";
else echo "<span class='priority-low'>LOW</span>";
?>
</td>

<td>
<?php
if($row['status']=='Open') echo "<span class='status-open'>Open</span>";
elseif($row['status']=='In Progress') echo "<span class='status-progress'>In Progress</span>";
else echo "<span class='status-resolved'>Resolved</span>";
?>
</td>

<td><?php echo $row['created_at'] ?></td>

<td>

<a href="view_issue.php?id=<?php echo $row['id'] ?>" class="btn btn-sm btn-info">
<i class="bi bi-eye"></i>
</a>

<a href="assign_work.php?id=<?php echo $row['id'] ?>" class="btn btn-sm btn-primary">
<i class="bi bi-person-plus"></i>
</a>

<a href="update_status.php?id=<?php echo $row['id'] ?>" class="btn btn-sm btn-success">
<i class="bi bi-check"></i>
</a>

</td>

</tr>

<?php }} else { ?>

<tr>
<td colspan="7" class="text-center">No issues found</td>
</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</body>
</html>
