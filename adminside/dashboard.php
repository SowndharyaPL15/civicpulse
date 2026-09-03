<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
    header("Location: adminlogin.php");
    exit;
}

// AI sync handler
if(isset($_GET['run_ai'])){
    $script_path = getenv('PYTHON_SCRIPT_PATH') ?: realpath(__DIR__ . '/../process_complaints.py');
    if($script_path && file_exists($script_path)){
        $python = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'env python' : 'python3';
        
        // Try executing
        exec("$python \"$script_path\" 2>&1", $output_lines, $return_var);
        
        // Fallback for custom python path
        if($return_var !== 0) {
            exec("python3 \"$script_path\" 2>&1", $output_lines, $return_var);
        }
        if($return_var !== 0) {
            exec("python \"$script_path\" 2>&1", $output_lines, $return_var);
        }

        $_SESSION['ai_log'] = implode("\n", $output_lines);
        $_SESSION['ai_status'] = ($return_var === 0) ? 'success' : 'warning';
    } else {
        $_SESSION['ai_log'] = "Error: process_complaints.py not found.";
        $_SESSION['ai_status'] = 'danger';
    }
    header("Location: dashboard.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get admin + department
$stmt = $conn->prepare("SELECT a.*, d.dept_name FROM admin a JOIN departments d ON a.dept_id=d.dept_id WHERE a.admin_id=?");
$stmt->bind_param("i",$admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_dept = $admin['dept_id'];

// Stats - using prepared statements
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues WHERE department_id=?");
$stmt->bind_param("i",$admin_dept);
$stmt->execute();
$totalIssues = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues WHERE department_id=? AND status='Open'");
$stmt->bind_param("i",$admin_dept);
$stmt->execute();
$openIssues = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues WHERE department_id=? AND status='In Progress'");
$stmt->bind_param("i",$admin_dept);
$stmt->execute();
$progressIssues = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM issues WHERE department_id=? AND status='Resolved'");
$stmt->bind_param("i",$admin_dept);
$stmt->execute();
$resolvedIssues = $stmt->get_result()->fetch_assoc()['c'];

// Filter
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = "AND 1=1";

if($filter=='open') $where .= " AND i.status='Open'";
if($filter=='progress') $where .= " AND i.status='In Progress'";
if($filter=='resolved') $where .= " AND i.status='Resolved'";

// Search support
$search_param = '';
if(!empty($search)){
    $where .= " AND (i.issue_title LIKE ? OR i.id = ?)";
    $search_param = "%$search%";
}

// Issues Query - using prepared statement
$query = "SELECT i.*, d.dept_name FROM issues i
JOIN departments d ON i.department_id=d.dept_id
WHERE i.department_id=? $where
ORDER BY FIELD(i.priority,'HIGH','MEDIUM','LOW'), i.complaint_count DESC";

$stmt = $conn->prepare($query);

if(!empty($search)){
    $search_id = intval($search);
    $stmt->bind_param("isi", $admin_dept, $search_param, $search_id);
} else {
    $stmt->bind_param("i", $admin_dept);
}

$stmt->execute();
$issues = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>CivicPulse Department Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
background:#f1f5f9;
font-family:'Inter',sans-serif;
}

.sidebar{
width:250px;
height:100vh;
position:fixed;
background:#0f172a;
padding:25px;
color:white;
z-index:100;
transition:0.3s;
}

.sidebar h4{margin-bottom:25px;}

.sidebar a{
display:flex;
align-items:center;
gap:10px;
padding:10px 14px;
color:#cbd5f5;
text-decoration:none;
border-radius:8px;
margin-bottom:8px;
transition:0.2s;
font-size:14px;
}

.sidebar a:hover,.sidebar a.active{background:#1e293b;color:white;}

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
transition:0.2s;
}

.stat:hover{transform:translateY(-3px);}

.stat i{font-size:28px;margin-bottom:10px;}

.priority-high{background:#dc2626;color:white;padding:5px 10px;border-radius:6px;font-size:12px}
.priority-medium{background:#f59e0b;color:white;padding:5px 10px;border-radius:6px;font-size:12px}
.priority-low{background:#16a34a;color:white;padding:5px 10px;border-radius:6px;font-size:12px}

.status-open{color:#dc2626;font-weight:600}
.status-progress{color:#f59e0b;font-weight:600}
.status-resolved{color:#16a34a;font-weight:600}

.high-row{background:#fee2e2}

.table{background:white;border-radius:10px;overflow:hidden}

.brand-text{
background:linear-gradient(90deg,#60a5fa,#34d399);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
font-weight:700;
}

/* Mobile sidebar toggle */
.sidebar-toggle{
display:none;
position:fixed;
top:15px;
left:15px;
z-index:200;
background:#0f172a;
color:white;
border:none;
border-radius:8px;
padding:8px 12px;
font-size:20px;
}

@media(max-width:768px){
.sidebar{
    transform:translateX(-100%);
}
.sidebar.show{
    transform:translateX(0);
}
.main{
    margin-left:0;
    padding:15px;
    padding-top:60px;
}
.sidebar-toggle{display:block;}
.table-responsive{font-size:13px;}
}

</style>
</head>

<body>

<!-- Mobile Toggle -->
<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
<i class="bi bi-list"></i>
</button>

<div class="sidebar">

<h4><span class="brand-text">CivicPulse</span></h4>

<p><strong><?php echo htmlspecialchars($admin['name']); ?></strong></p>
<p style="font-size:13px">Dept: <?php echo htmlspecialchars($admin['dept_name']); ?></p>

<hr>

<a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a href="workers.php"><i class="bi bi-people"></i> Workers</a>
<a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
<a href="../userside/about.php"><i class="bi bi-info-circle"></i> About</a>
<a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>

</div>

<div class="main">

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="m-0">Department Operations Dashboard</h2>
    <a href="?run_ai=1" class="btn btn-dark btn-sm" style="border-radius:10px; padding:10px 15px;">
        <i class="bi bi-cpu"></i> Run AI Grouping / Sync
    </a>
</div>

<?php if(isset($_SESSION['ai_status'])): ?>
<div class="alert alert-<?= htmlspecialchars($_SESSION['ai_status']) ?> alert-dismissible fade show shadow-sm" role="alert" style="border-radius:12px;">
    <strong><i class="bi bi-robot"></i> AI Grouping Execution Log:</strong>
    <pre class="mt-2 p-2 bg-light border rounded text-dark" style="font-size:12px; max-height:150px; overflow-y:auto; font-family:monospace;"><?= htmlspecialchars($_SESSION['ai_log']) ?></pre>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php 
unset($_SESSION['ai_status']);
unset($_SESSION['ai_log']);
endif; 
?>

<div class="row g-3 mb-4">

<div class="col-6 col-md-3">
<div class="card shadow stat">
<i class="bi bi-list-task text-primary"></i>
<h6>Total Issues</h6>
<h4><?php echo $totalIssues ?></h4>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card shadow stat">
<i class="bi bi-exclamation-circle text-danger"></i>
<h6>Open</h6>
<h4><?php echo $openIssues ?></h4>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card shadow stat">
<i class="bi bi-tools text-warning"></i>
<h6>In Progress</h6>
<h4><?php echo $progressIssues ?></h4>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card shadow stat">
<i class="bi bi-check-circle text-success"></i>
<h6>Resolved</h6>
<h4><?php echo $resolvedIssues ?></h4>
</div>
</div>

</div>

<div class="card shadow p-4">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

<h5 class="m-0"><i class="bi bi-exclamation-triangle"></i> Civic Issues</h5>

<div class="d-flex flex-wrap gap-2 align-items-center">

<!-- Search Box -->
<form method="GET" class="d-flex gap-2">
<input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
<input type="text" name="search" class="form-control form-control-sm" placeholder="Search by title or ID..."
       value="<?= htmlspecialchars($search) ?>" style="min-width:180px;border-radius:8px;">
<button class="btn btn-sm btn-primary" style="border-radius:8px;">
<i class="bi bi-search"></i>
</button>
</form>

<div>
<a href="?filter=all" class="btn btn-sm <?= $filter=='all'?'btn-secondary':'btn-outline-secondary' ?>">All</a>
<a href="?filter=open" class="btn btn-sm <?= $filter=='open'?'btn-danger':'btn-outline-danger' ?>">Open</a>
<a href="?filter=progress" class="btn btn-sm <?= $filter=='progress'?'btn-warning':'btn-outline-warning' ?>">In Progress</a>
<a href="?filter=resolved" class="btn btn-sm <?= $filter=='resolved'?'btn-success':'btn-outline-success' ?>">Resolved</a>
</div>

</div>

</div>

<div class="table-responsive">
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

<td><?php echo htmlspecialchars($row['issue_title']) ?></td>

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

<a href="view_issue.php?id=<?php echo $row['id'] ?>" class="btn btn-sm btn-info" title="View">
<i class="bi bi-eye"></i>
</a>

<a href="assign_work.php?id=<?php echo $row['id'] ?>" class="btn btn-sm btn-primary" title="Assign">
<i class="bi bi-person-plus"></i>
</a>

<a href="update_status.php?id=<?php echo $row['id'] ?>" class="btn btn-sm btn-success" title="Update">
<i class="bi bi-check"></i>
</a>

</td>

</tr>

<?php }} else { ?>

<tr>
<td colspan="7" class="text-center py-4 text-muted">
<i class="bi bi-inbox" style="font-size:24px"></i><br>
No issues found
</td>
</tr>

<?php } ?>

</tbody>

</table>
</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
