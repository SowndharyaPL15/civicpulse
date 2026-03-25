<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id'])){
header("Location: login.php");
exit;
}

$user_id=$_SESSION['user_id'];

$query="
SELECT 
i.id issue_id,
i.status,
i.priority,
i.complaint_count,
d.dept_name,
it.issue_name,
MIN(c.created_at) created_at,
MIN(c.image) image,
MIN(c.description) description
FROM issues i
JOIN complaints c ON c.issue_id=i.id
JOIN departments d ON c.department_id=d.dept_id
JOIN issue_types it ON c.issue_type_id=it.type_id
WHERE c.user_id=$user_id
GROUP BY i.id
ORDER BY created_at DESC
";

$result=$conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>

<title>Track Complaints</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

<style>

body{
background:#f1f5f9;
font-family:Poppins;
}

.card{
border:none;
border-radius:12px;
box-shadow:0 6px 18px rgba(0,0,0,0.08);
margin-bottom:20px;
}

.timeline{
border-left:3px solid #2563eb;
padding-left:15px;
margin-top:15px;
}

.timeline div{
margin-bottom:10px;
}

.confirm-box{
background:#eef2ff;
padding:10px;
border-radius:8px;
}

</style>

</head>

<body>

<div class="container mt-5">

<h3 class="mb-4">Complaint Tracking</h3>

<?php while($row=$result->fetch_assoc()): ?>

<div class="card">

<div class="card-body">

<div class="row">

<div class="col-md-8">

<h5><?php echo $row['issue_name']; ?></h5>

<p class="text-muted">
Department: <?php echo $row['dept_name']; ?>
</p>

<p><?php echo $row['description']; ?></p>

</div>

<div class="col-md-4 text-end">

<?php

$status=$row['status'];

if($status=="Resolved")
echo "<span class='badge bg-success'>Resolved</span>";

elseif($status=="In Progress")
echo "<span class='badge bg-warning text-dark'>In Progress</span>";

else
echo "<span class='badge bg-secondary'>Open</span>";

?>

</div>

</div>

<hr>

<div class="row">

<div class="col-md-4">

<strong>Priority</strong><br>

<?php

if($row['priority']=="HIGH")
echo "<span class='badge bg-danger'>HIGH</span>";

elseif($row['priority']=="MEDIUM")
echo "<span class='badge bg-warning text-dark'>MEDIUM</span>";

else
echo "<span class='badge bg-success'>LOW</span>";

?>

</div>

<div class="col-md-4">

<strong>Reports</strong><br>

<?php echo $row['complaint_count']; ?> citizens reported

</div>

<div class="col-md-4">

<strong>Date</strong><br>

<?php echo $row['created_at']; ?>

</div>

</div>

<?php if($row['image']): ?>

<br>

<img src="<?php echo $row['image']; ?>" width="140" class="rounded">

<?php endif; ?>

<!-- Timeline -->

<div class="timeline">

<div>Complaint Submitted</div>

<?php

$updates=$conn->query("
SELECT * FROM issue_updates
WHERE issue_id=".$row['issue_id']."
ORDER BY created_at ASC
");

while($u=$updates->fetch_assoc()){

echo "<div>".$u['update_message']." - ".$u['created_at']."</div>";

}

?>

</div>

<!-- Confirmation -->

<?php

$conf=$conn->query("
SELECT COUNT(*) c
FROM issue_confirmations
WHERE issue_id=".$row['issue_id']
)->fetch_assoc()['c'];

?>

<div class="confirm-box mt-3">

<?php echo $conf; ?> citizens confirmed this issue

<form method="post" action="confirm_issue.php">

<input type="hidden" name="issue_id" value="<?php echo $row['issue_id']; ?>">

<button class="btn btn-sm btn-primary mt-2">
Confirm This Issue
</button>

</form>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

</body>
</html>