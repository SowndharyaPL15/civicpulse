<?php
session_start();
include "config.php";

/* AUTH CHECK - Prevent unauthorized access */
if(!isset($_SESSION['admin_id'])){
    header("Location: adminlogin.php");
    exit;
}

if(!isset($_POST['issue_id']) || !isset($_POST['department_id'])){
    header("Location: central_admin_dashboard.php");
    exit;
}

$issue_id = intval($_POST['issue_id']);
$department_id = intval($_POST['department_id']);

/* Use prepared statement to prevent SQL injection */
$stmt = $conn->prepare("UPDATE issues SET department_id=?, status='In Progress' WHERE id=?");
$stmt->bind_param("ii", $department_id, $issue_id);
$stmt->execute();
$stmt->close();

header("Location: central_admin_dashboard.php");
exit;
?>