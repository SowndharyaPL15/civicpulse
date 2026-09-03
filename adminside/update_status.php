<?php
session_start();
include "config.php";

if(!isset($_SESSION['admin_id'])){
header("Location: adminlogin.php");
exit;
}

$admin_id = $_SESSION['admin_id'];

/* VALIDATE ISSUE ID */
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
die("Invalid Issue ID");
}

$issue_id = intval($_GET['id']);


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

$old_status = $issue['status'];


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
$notes  = trim($_POST['notes'] ?? '');

/* Validate status value */
$valid_statuses = ['Open', 'In Progress', 'Resolved'];
if(!in_array($status, $valid_statuses)){
    $error = "Invalid status value.";
} else {

    $stmt = $conn->prepare("UPDATE issues SET status=?, resolution_notes=? WHERE id=?");
    $stmt->bind_param("ssi",$status,$notes,$issue_id);
    $stmt->execute();

    /* Record status history */
    $stmt = $conn->prepare("INSERT INTO issue_status_history (issue_id, old_status, new_status, changed_by, notes) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issis", $issue_id, $old_status, $status, $admin_id, $notes);
    $stmt->execute();

    /* Add to issue_updates timeline */
    $update_msg = "Status changed from '$old_status' to '$status'";
    if(!empty($notes)){
        $update_msg .= ". Notes: $notes";
    }
    $stmt = $conn->prepare("INSERT INTO issue_updates (issue_id, update_message, updated_by) VALUES (?,?,?)");
    $stmt->bind_param("isi", $issue_id, $update_msg, $admin_id);
    $stmt->execute();

    /* IF RESOLVED → FREE WORKER */
    if($status=="Resolved" && $assignment){

        $worker = $assignment['worker_id'];

        $stmt = $conn->prepare("UPDATE workers SET status='Available' WHERE worker_id=?");
        $stmt->bind_param("i", $worker);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE work_assignments SET status='Completed', completed_at=NOW() WHERE issue_id=?");
        $stmt->bind_param("i", $issue_id);
        $stmt->execute();
    }

    $success = true;
}

}

/* GET STATUS HISTORY */
$stmt = $conn->prepare("
SELECT * FROM issue_status_history
WHERE issue_id=?
ORDER BY created_at DESC
LIMIT 10
");
$stmt->bind_param("i",$issue_id);
$stmt->execute();
$history = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<title>Update Issue Status — CivicPulse</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
background:#eef2f7;
font-family:'Inter',sans-serif;
}

.container-box{
max-width:900px;
margin:auto;
margin-top:40px;
margin-bottom:40px;
}

.card{
border:none;
border-radius:14px;
}

.priority-high{background:#dc2626;color:white;padding:4px 10px;border-radius:6px;font-size:12px}
.priority-medium{background:#f59e0b;color:white;padding:4px 10px;border-radius:6px;font-size:12px}
.priority-low{background:#16a34a;color:white;padding:4px 10px;border-radius:6px;font-size:12px}

.status-open{color:#dc2626;font-weight:600}
.status-progress{color:#f59e0b;font-weight:600}
.status-resolved{color:#16a34a;font-weight:600}

</style>

</head>

<body>

<div class="container container-box">

<div class="card shadow p-4 mb-4">

<div class="d-flex justify-content-between align-items-center mb-3">
<h4 class="m-0">
<i class="bi bi-arrow-repeat"></i> Update Issue Status
</h4>
<a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if(isset($success)): ?>
<div class="alert alert-success d-flex align-items-center gap-2">
<i class="bi bi-check-circle"></i> Status updated successfully!
<a href="dashboard.php" class="btn btn-sm btn-success ms-auto">Back to Dashboard</a>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

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

<?php if($assignment): ?>
<p>
<strong>Assigned Worker:</strong>
<?php echo htmlspecialchars($assignment['worker_name']); ?>
</p>
<?php endif; ?>

</div>

<div class="col-md-6">

<form method="POST">

<h6>Change Status</h6>

<select name="status" class="form-select mb-3" required>

<option value="">Select Status</option>

<option value="Open" <?= $issue['status']=='Open'?'selected':'' ?>>Open</option>
<option value="In Progress" <?= $issue['status']=='In Progress'?'selected':'' ?>>In Progress</option>
<option value="Resolved" <?= $issue['status']=='Resolved'?'selected':'' ?>>Resolved</option>

</select>

<label class="form-label">Resolution Notes</label>

<textarea name="notes" class="form-control mb-3" rows="3"
placeholder="Describe repair work done..."><?= htmlspecialchars($issue['resolution_notes'] ?? '') ?></textarea>

<button class="btn btn-primary w-100" name="update">

<i class="bi bi-check-circle"></i>
Update Status

</button>

</form>

</div>

</div>

</div>

<!-- STATUS HISTORY -->
<?php if($history && $history->num_rows > 0): ?>
<div class="card shadow p-4">

<h5><i class="bi bi-clock-history"></i> Status History</h5>

<div class="table-responsive">
<table class="table table-sm">
<thead class="table-light">
<tr>
<th>From</th>
<th>To</th>
<th>Notes</th>
<th>Date</th>
</tr>
</thead>
<tbody>
<?php while($h = $history->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($h['old_status'] ?? 'N/A') ?></td>
<td><strong><?= htmlspecialchars($h['new_status']) ?></strong></td>
<td><?= htmlspecialchars($h['notes'] ?? '-') ?></td>
<td><?= $h['created_at'] ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
<?php endif; ?>

</div>

</body>
</html>