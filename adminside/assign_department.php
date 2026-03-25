<?php
include "config.php";

$issue_id = $_POST['issue_id'];
$department_id = $_POST['department_id'];

$sql="UPDATE issues
SET department_id='$department_id',
status='In Progress'
WHERE id='$issue_id'";

mysqli_query($conn,$sql);

header("Location: central_admin_dashboard.php");
?>