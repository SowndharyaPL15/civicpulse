<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id'])){
header("Location: login.php");
exit;
}

$user_id=$_SESSION['user_id'];

$stmt=$conn->prepare("
SELECT 
c.cid AS complaint_id,
c.description,
c.image,
c.created_at,
d.dept_name,
it.issue_name,
i.id AS issue_id,
i.status,
i.priority,
i.complaint_count,
i.resolution_notes
FROM complaints c
JOIN departments d ON c.department_id=d.dept_id
JOIN issue_types it ON c.issue_type_id=it.type_id
LEFT JOIN issues i ON c.issue_id=i.id
WHERE c.user_id=?
ORDER BY c.created_at DESC
");

$stmt->bind_param("i",$user_id);
$stmt->execute();
$result=$stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<title>Track Complaints — CivicPulse</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>

body{
background:#f5f7fb;
font-family:'Inter',sans-serif;
}

/* CARD */
.card{
border:none;
border-radius:16px;
box-shadow:0 10px 25px rgba(0,0,0,0.06);
margin-bottom:25px;
padding:20px;
}

/* STATUS */
.status{
padding:6px 12px;
border-radius:20px;
font-size:13px;
font-weight:500;
}

.open{background:#fee2e2;color:#dc2626;}
.progress{background:#fef3c7;color:#d97706;}
.resolved{background:#dcfce7;color:#16a34a;}

/* PROGRESS TRACKER */
.progress-tracker{
display:flex;
align-items:center;
justify-content:space-between;
margin-top:15px;
}

.step{
text-align:center;
}

.circle{
width:32px;
height:32px;
border-radius:50%;
background:#e5e7eb;
display:flex;
align-items:center;
justify-content:center;
font-weight:600;
font-size:14px;
margin:0 auto;
transition:0.3s;
}

.step.active .circle{
background:#2563eb;
color:#fff;
}

.label{
font-size:12px;
margin-top:5px;
color:#6b7280;
}

.line{
flex:1;
height:3px;
background:#e5e7eb;
margin:0 5px;
}

.line.active{
background:#2563eb;
}

/* TIMELINE */
.timeline{
margin-top:15px;
border-left:2px solid #2563eb;
padding-left:15px;
}

.timeline-item{
margin-bottom:10px;
font-size:14px;
}

.issue-img{
margin-top:10px;
border-radius:10px;
max-width:160px;
border:1px solid #e5e7eb;
}

.btn-back{
background:#f1f5f9;
color:#111827;
border-radius:8px;
padding:6px 12px;
font-size:14px;
text-decoration:none;
font-weight:500;
transition:0.2s;
display:inline-flex;
align-items:center;
gap:6px;
}

.btn-back:hover{
background:#e5e7eb;
color:#000;
transform:translateX(-2px);
}

</style>

</head>

<body>

<div class="container mt-4 mb-5" style="max-width:800px;">

<div class="d-flex justify-content-between align-items-center mb-4">

<a href="home.php" class="btn-back">
    <i class="bi bi-arrow-left"></i> Back
</a>

<h3 class="m-0"><i class="bi bi-geo-alt"></i> Track Your Complaints</h3>

</div>

<?php if($result->num_rows == 0): ?>
<!-- EMPTY STATE -->
<div class="card text-center py-5">
<i class="bi bi-inbox" style="font-size:48px;color:#9ca3af;"></i>
<h5 class="mt-3 text-muted">No complaints to track yet</h5>
<p class="text-muted">Submit a complaint and track its progress here.</p>
<a href="complaint.php" class="btn btn-primary mt-2" style="border-radius:10px;">
<i class="bi bi-plus-lg"></i> Submit Complaint
</a>
</div>
<?php endif; ?>


<?php while($row=$result->fetch_assoc()): ?>

<?php
$has_issue = !empty($row['issue_id']);
$status = $has_issue ? $row['status'] : "Pending";

$step = 1;
if($status == "Open") $step = 1;
elseif($status == "In Progress") $step = 3;
elseif($status == "Resolved") $step = 4;
?>

<div class="card">

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

<div>
<h5 class="mb-1">
<i class="bi bi-exclamation-circle text-primary"></i>
<?php echo htmlspecialchars($row['issue_name']); ?>
</h5>
<small class="text-muted">
    <?= $has_issue ? "Issue #" . $row['issue_id'] : "Complaint ID: " . $row['complaint_id'] ?>
</small>
</div>

<?php
if($status == "Resolved")
    echo "<span class='status resolved'>✅ Resolved</span>";
elseif($status == "In Progress")
    echo "<span class='status progress'>🔧 In Progress</span>";
elseif($status == "Open")
    echo "<span class='status open'>🔴 Open</span>";
else
    echo "<span class='status text-muted' style='background:#f1f5f9;color:#6b7280;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:500;'>⚪ Pending Review</span>";
?>

</div>

<p class="text-muted mb-1 mt-2">
<i class="bi bi-building"></i> Department: <?php echo htmlspecialchars($row['dept_name']); ?>
</p>

<p><?php echo htmlspecialchars($row['description']); ?></p>

<?php if($row['image']): ?>
<img src="../<?php echo htmlspecialchars($row['image']); ?>" class="issue-img" alt="Evidence">
<?php endif; ?>

<!-- INFO -->
<div class="d-flex gap-4 mt-3 flex-wrap">

<div>
<strong>Priority</strong><br>
<?php
$p = $has_issue ? $row['priority'] : 'LOW';
if($p == "HIGH")
    echo "<span class='badge bg-danger'>HIGH</span>";
elseif($p == "MEDIUM")
    echo "<span class='badge bg-warning text-dark'>MEDIUM</span>";
else
    echo "<span class='badge bg-success'>LOW</span>";
?>
</div>

<div>
<strong>Reports</strong><br>
<?= $has_issue ? $row['complaint_count'] : 1 ?> total
</div>

<div>
<strong>Submitted</strong><br>
<?php echo date('M d, Y', strtotime($row['created_at'])); ?>
</div>

</div>

<!-- PROGRESS TRACKER -->
<div class="progress-tracker">

<div class="step <?php if($step>=1) echo 'active'; ?>">
<div class="circle">1</div>
<div class="label">Submitted</div>
</div>

<div class="line <?php if($step>=2) echo 'active'; ?>"></div>

<div class="step <?php if($step>=2) echo 'active'; ?>">
<div class="circle">2</div>
<div class="label">Assigned</div>
</div>

<div class="line <?php if($step>=3) echo 'active'; ?>"></div>

<div class="step <?php if($step>=3) echo 'active'; ?>">
<div class="circle">3</div>
<div class="label">In Progress</div>
</div>

<div class="line <?php if($step>=4) echo 'active'; ?>"></div>

<div class="step <?php if($step>=4) echo 'active'; ?>">
<div class="circle">4</div>
<div class="label">Resolved</div>
</div>

</div>

<!-- TIMELINE -->
<div class="timeline">

<div class="timeline-item">
📝 Complaint submitted<br>
<small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></small>
</div>

<?php
if($has_issue) {
    /* Fetch updates with error handling */
    $upd_stmt = $conn->prepare("SELECT * FROM issue_updates WHERE issue_id=? ORDER BY created_at ASC");
    if($upd_stmt){
        $upd_stmt->bind_param("i", $row['issue_id']);
        $upd_stmt->execute();
        $updates = $upd_stmt->get_result();

        while($u = $updates->fetch_assoc()){
            echo "<div class='timeline-item'>
            🔄 " . htmlspecialchars($u['update_message']) . "<br>
            <small class='text-muted'>" . date('M d, Y h:i A', strtotime($u['created_at'])) . "</small>
            </div>";
        }
    }
} else {
    echo "<div class='timeline-item text-muted'>
    ⏳ Under review (Awaiting AI processing & group assignment)
    </div>";
}
?>

<?php if($status=="Resolved"): ?>
<div class="timeline-item text-success fw-semibold">
✅ Issue resolved successfully
<?php if($row['resolution_notes']): ?>
<br><small class="text-muted"><?= htmlspecialchars($row['resolution_notes']) ?></small>
<?php endif; ?>
</div>
<?php endif; ?>

</div>

</div>

<?php endwhile; ?>

</div>

</body>
</html>