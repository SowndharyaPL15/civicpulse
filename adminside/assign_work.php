<?php
session_start();
include "config.php";

/* ADMIN AUTH CHECK */

if(!isset($_SESSION['admin_id'])){
header("Location: adminlogin.php");
exit;
}

$admin_id = $_SESSION['admin_id'];


/* ISSUE ID VALIDATION */

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
die("Invalid Issue ID");
}

$issue_id = $_GET['id'];


/* GET ISSUE DETAILS */

$stmt = $conn->prepare("
SELECT i.*, d.dept_name
FROM issues i
JOIN departments d ON i.department_id = d.dept_id
WHERE i.id = ?
");

$stmt->bind_param("i",$issue_id);
$stmt->execute();
$result = $stmt->get_result();
$issue = $result->fetch_assoc();

if(!$issue){
die("Issue not found");
}

$dept_id = $issue['department_id'];


/* GET AVAILABLE WORKERS */

$stmt = $conn->prepare("
SELECT * FROM workers
WHERE department_id = ? AND status = 'Available'
ORDER BY name
");

$stmt->bind_param("i",$dept_id);
$stmt->execute();
$workers = $stmt->get_result();


/* ASSIGN WORK */

if(isset($_POST['assign'])){

$worker_id = $_POST['worker'] ?? 0;

if($worker_id == 0){
$error = "Please select a worker";
}
else{

/* INSERT ASSIGNMENT */

$stmt = $conn->prepare("
INSERT INTO work_assignments(issue_id,worker_id,assigned_by,status)
VALUES(?,?,?,'Assigned')
");

$stmt->bind_param("iii",$issue_id,$worker_id,$admin_id);
$stmt->execute();


/* UPDATE ISSUE STATUS AND ASSIGNED WORKER */

$stmt = $conn->prepare("
UPDATE issues
SET status='In Progress', assigned_worker_id=?
WHERE id=?
");

$stmt->bind_param("ii",$worker_id,$issue_id);
$stmt->execute();


/* UPDATE WORKER STATUS */

$stmt = $conn->prepare("
UPDATE workers
SET status='Busy'
WHERE worker_id=?
");

$stmt->bind_param("i",$worker_id);
$stmt->execute();

$success = true;

}

}
?>

<!DOCTYPE html>
<html>
<head>

<title>Assign Worker — CivicPulse</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>

body{
background:#f1f5f9;
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

.priority-high{
background:#dc2626;
color:white;
padding:4px 10px;
border-radius:6px;
}

.priority-medium{
background:#f59e0b;
color:white;
padding:4px 10px;
border-radius:6px;
}

.priority-low{
background:#16a34a;
color:white;
padding:4px 10px;
border-radius:6px;
}

</style>

</head>

<body>

<div class="container container-box">

<div class="card shadow p-4">

<h4 class="mb-4">
<i class="bi bi-person-plus"></i> Assign Worker
</h4>

<?php if(isset($success)){ ?>

<div class="alert alert-success">

Work successfully assigned!

<a href="dashboard.php" class="btn btn-sm btn-success ms-3">
Back to Dashboard
</a>

</div>

<?php } ?>

<?php if(isset($error)){ ?>

<div class="alert alert-danger">
<?php echo $error; ?>
</div>

<?php } ?>


<div class="row">

<div class="col-md-6">

<h5>Issue Details</h5>

<hr>

<p>
<strong>Issue:</strong>
<?php echo htmlspecialchars($issue['issue_title']); ?>
</p>

<p>
<strong>Department:</strong>
<?php echo htmlspecialchars($issue['dept_name']); ?>
</p>

<p>
<strong>Reports:</strong>
<?php echo $issue['complaint_count']; ?>
</p>

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

</div>


<div class="col-md-6">

<form method="POST">

<h5>Select Worker</h5>

<hr>

<select name="worker" class="form-select mb-3" required>

<option value="">Choose Worker</option>

<?php while($w=$workers->fetch_assoc()){ ?>

<option value="<?php echo $w['worker_id']; ?>">

<?php echo $w['name']; ?> (<?php echo $w['phone']; ?>)

</option>

<?php } ?>

</select>

<button class="btn btn-primary w-100" name="assign">

<i class="bi bi-send"></i> Assign Work

</button>

</form>

</div>

</div>

</div>

</div>

</body>
</html>