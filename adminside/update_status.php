<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
header("Location: adminlogin.php");
exit;
}

/* VALIDATE ISSUE ID */

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
die("Invalid Issue ID");
}

$issue_id = $_GET['id'];


/* GET ISSUE DETAILS */

$stmt = $conn->prepare("
SELECT i.*, d.dept_name
FROM issues i
JOIN departments d ON i.department_id=d.dept_id
WHERE i.id=?
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();

$result = $stmt->get_result();
$issue = $result->fetch_assoc();

if(!$issue){
die("Issue not found");
}


/* GET LAST ASSIGNED WORKER */

$stmt = $conn->prepare("
SELECT wa.*, w.name AS worker_name
FROM work_assignments wa
JOIN workers w ON wa.worker_id=w.worker_id
WHERE wa.issue_id=?
ORDER BY wa.assigned_at DESC
LIMIT 1
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();

$assignment_result = $stmt->get_result();
$assignment = $assignment_result->fetch_assoc();


/* UPDATE STATUS */

if(isset($_POST['update'])){

$status = $_POST['status'];
$notes  = $_POST['notes'];

$stmt = $conn->prepare("UPDATE issues SET status=?, resolution_notes=? WHERE id=?");
$stmt->bind_param("ssi",$status,$notes,$issue_id);
$stmt->execute();


/* IF RESOLVED → FREE WORKER */

if($status=="Resolved" && $assignment){

$worker = $assignment['worker_id'];

$conn->query("UPDATE workers SET status='Available' WHERE worker_id=$worker");

$conn->query("UPDATE work_assignments 
SET status='Completed' 
WHERE issue_id=$issue_id");

}

$success = true;

}
?>

<!DOCTYPE html>
<html>
<head>

<title>Update Issue Status</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>

body{
background:#eef2f7;
font-family:Segoe UI;
}

.container-box{
max-width:900px;
margin:auto;
margin-top:60px;
}

.card{
border:none;
border-radius:14px;
}

.priority-high{background:#dc2626;color:white;padding:4px 10px;border-radius:6px}
.priority-medium{background:#f59e0b;color:white;padding:4px 10px;border-radius:6px}
.priority-low{background:#16a34a;color:white;padding:4px 10px;border-radius:6px}

.status-open{color:#dc2626;font-weight:600}
.status-progress{color:#f59e0b;font-weight:600}
.status-resolved{color:#16a34a;font-weight:600}

</style>

</head>

<body>

<div class="container container-box">

<div class="card shadow p-4">

<h4 class="mb-4">
<i class="bi bi-arrow-repeat"></i> Update Issue Status
</h4>

<?php if(isset($success)){ ?>

<div class="alert alert-success">
Status updated successfully!
<a href="dashboard.php" class="btn btn-sm btn-success ms-3">Back to Dashboard</a>
</div>

<?php } ?>

<div class="row">

<div class="col-md-6">

<h6>Issue Details</h6>

<p><strong>Issue:</strong> <?php echo htmlspecialchars($issue['issue_title']); ?></p>

<p><strong>Department:</strong> <?php echo htmlspecialchars($issue['dept_name']); ?></p>

<p><strong>Reports:</strong> <?php echo $issue['complaint_count']; ?></p>

<p>
<strong>Priority:</strong>

<?php
if($issue['priority']=="HIGH")
echo "<span class='priority-high'>HIGH</span>";
elseif($issue['priority']=="MEDIUM")
echo "<span class='priority-medium'>MEDIUM</span>";
else
echo "<span class='priority-low'>LOW</span>";
?>

</p>

<p>
<strong>Current Status:</strong>

<?php
if($issue['status']=="Open")
echo "<span class='status-open'>Open</span>";
elseif($issue['status']=="In Progress")
echo "<span class='status-progress'>In Progress</span>";
else
echo "<span class='status-resolved'>Resolved</span>";
?>

</p>

<?php if($assignment){ ?>

<p>
<strong>Assigned Worker:</strong>
<?php echo htmlspecialchars($assignment['worker_name']); ?>
</p>

<?php } ?>

</div>

<div class="col-md-6">

<form method="POST">

<h6>Change Status</h6>

<select name="status" class="form-select mb-3" required>

<option value="">Select Status</option>

<option value="Open">Open</option>
<option value="In Progress">In Progress</option>
<option value="Resolved">Resolved</option>

</select>

<label class="form-label">Resolution Notes</label>

<textarea name="notes" class="form-control mb-3"
placeholder="Describe repair work done..."></textarea>

<button class="btn btn-primary w-100" name="update">

<i class="bi bi-check-circle"></i>
Update Status

</button>

</form>

</div>

</div>

</div>

</div>

</body>
</html>