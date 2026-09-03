<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch departments
$dept_result = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC");

// Fetch issue types
$type_result = $conn->query("SELECT type_id, issue_name, dept_id FROM issue_types ORDER BY issue_name ASC");
$issue_types = [];
while($row = $type_result->fetch_assoc()){
    $issue_types[] = $row;
}
 
if(isset($_POST['submit_complaint'])){
    $department_id = $_POST['department_id'] ?? '';
    $issue_type_id = $_POST['issue_type_id'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $other_department = trim($_POST['other_department'] ?? '');
    $other_issue = trim($_POST['other_issue'] ?? '');

    /* HANDLE OTHERS */
    if($department_id === "other"){
        $department_id = 0;
        if(!empty($other_department)){
            $description .= " | Custom Dept: " . $other_department;
        }
    }

    if($issue_type_id === "other"){
        $issue_type_id = 0;
        if(!empty($other_issue)){
            $description .= " | Custom Issue: " . $other_issue;
        }
    }

    $description = mysqli_real_escape_string($conn, $description);
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : NULL;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : NULL;
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : NULL;
    $image_path = NULL;

    // Image upload
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){

        // File size validation (5MB max)
        $max_size = 5 * 1024 * 1024;
        if($_FILES['image']['size'] > $max_size){
            $error = "Image file too large. Maximum size is 5MB.";
        } else {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if(in_array($ext, $allowed)){
                $new_name = uniqid('complaint_', true) . '.' . $ext;
                $upload_dir = '../uploads/';
                if(!is_dir($upload_dir)){
                    mkdir($upload_dir, 0755, true);
                }
                $destination = $upload_dir . $new_name;

                if(move_uploaded_file($_FILES['image']['tmp_name'], $destination)){
                    $image_path = 'uploads/' . $new_name;
                } else {
                    $error = "Failed to upload image. Please try again.";
                }
            } else {
                $error = "Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP.";
            }
        }
    }

    if(empty($error) && !empty($department_id) && !empty($issue_type_id) && !empty($description)){
        $stmt = $conn->prepare("INSERT INTO complaints 
        (user_id, department_id, issue_type_id, description, image, latitude, longitude, address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("iiissdds", $user_id, $department_id, $issue_type_id, $description, $image_path, $latitude, $longitude, $address);

        if($stmt->execute()){
            $success = "Complaint submitted successfully! It will be reviewed and grouped shortly.";
            
            // Run AI grouping script using relative or configured path
            $script_path = getenv('PYTHON_SCRIPT_PATH');
            if(empty($script_path)){
                $script_path = realpath(__DIR__ . '/../process_complaints.py');
            }
            if($script_path && file_exists($script_path)){
                $python = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'python' : 'python3';
                exec("$python \"$script_path\" > /dev/null 2>&1 &");
            }
        } else {
            $error = "Something went wrong. Please try again.";
        }
    } elseif(empty($error)) {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Submit Complaint — CivicPulse</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* BASE */
body {
    font-family: 'Inter', sans-serif;
    background: #f8fafc;
}

/* CONTAINER */
.container {
    max-width: 720px;
    margin: 40px auto;
    padding: 0 15px;
}

/* CARD */
.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    overflow: hidden;
    background: #fff;
}

/* HEADER */
.card-header {
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    font-size: 1.2rem;
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* BACK BUTTON */
.back-btn {
    background: #f1f5f9;
    color: #111827;
    font-size: 14px;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    transition: 0.2s;
}

.back-btn:hover {
    background: #e5e7eb;
}

/* FORM */
.card-body {
    padding: 25px;
}

.form-label {
    font-weight: 500;
    color: #374151;
}

/* INPUTS */
.form-control,
.form-select {
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    padding: 10px;
    transition: 0.2s;
}

.form-control:focus,
.form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
}

/* MAP BOX */
#map {
    height: 280px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    margin-bottom: 15px;
}

/* IMAGE PREVIEW */
#preview {
    max-height: 180px;
    border-radius: 10px;
    margin-top: 10px;
    border: 1px solid #e5e7eb;
}

/* BUTTONS */
.btn-primary {
    background: #2563eb;
    border: none;
    border-radius: 10px;
    font-weight: 500;
    padding: 10px;
    transition: 0.2s;
}

.btn-primary:hover {
    background: #1e40af;
}

/* SECONDARY BUTTON */
.btn-outline-primary {
    border-radius: 10px;
    font-weight: 500;
}

/* ALERT */
.alert {
    border-radius: 10px;
    font-weight: 500;
}
</style>
</head>
<body>

<div class="container">
<div class="card">
<div class="card-header">
    <span><i class="bi bi-pencil-square text-primary"></i> Submit Complaint</span>
    <a href="home.php" class="back-btn">← Back to Dashboard</a>
</div>
<div class="card-body">

<?php if($success) echo "<div class='alert alert-success'><i class='bi bi-check-circle'></i> " . htmlspecialchars($success) . "</div>"; ?>
<?php if($error) echo "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> " . htmlspecialchars($error) . "</div>"; ?>

<form method="POST" enctype="multipart/form-data">

<div class="mb-3">
<label class="form-label">Department</label>
<select class="form-select" name="department_id" id="department" required>
<option value="">Select Department</option>
<?php
while($row = $dept_result->fetch_assoc()){
    echo "<option value='{$row['dept_id']}'>" . htmlspecialchars($row['dept_name']) . "</option>";
}
?>
<option value="other">Other</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Issue Type</label>
<select class="form-select" name="issue_type_id" id="issue_type" required>
<option value="">Select Issue Type</option>
</select>
</div>

<!-- CUSTOM DEPARTMENT -->
<div class="mb-3" id="otherDeptBox" style="display:none;">
<input type="text" class="form-control" name="other_department" placeholder="Enter Department Name">
</div>

<!-- CUSTOM ISSUE TYPE -->
<div class="mb-3" id="otherIssueBox" style="display:none;">
<input type="text" class="form-control" name="other_issue" placeholder="Enter Issue Type">
</div>

<div class="mb-3">
<label class="form-label">Description</label>
<textarea class="form-control" name="description" placeholder="Describe the issue in detail..." rows="4" required></textarea>
</div>

<div class="mb-3">
<label class="form-label">Upload Image (optional, max 5MB)</label>
<input class="form-control" type="file" name="image" accept="image/*" onchange="previewImage(event)">
<img id="preview" style="display:none; width:100%;">
</div>

<div class="mb-3">
<label class="form-label">Search Location</label>
<div class="input-group mb-3">
<input type="text" class="form-control" id="searchAddress" placeholder="Type address (e.g. Anna Nagar, Chennai)">
<button type="button" class="btn btn-primary" onclick="searchLocation()"><i class="bi bi-search"></i> Search</button>
</div>
</div>

<div class="mb-3">
<button type="button" class="btn btn-outline-primary w-100 mb-3" onclick="getCurrentLocation()">
📍 Use My Current Location
</button>
<div id="map"></div>
</div>

<input type="hidden" name="latitude" id="latitude">
<input type="hidden" name="longitude" id="longitude">

<div class="mb-3">
<input type="text" class="form-control" name="address" id="address" readonly placeholder="Detected address will appear here">
</div>

<button name="submit_complaint" class="btn btn-primary w-100">
<i class="bi bi-send"></i> Submit Complaint
</button>
</form>

</div>
</div>
</div>

<!-- JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// ===== Issue Type Filter =====
const issueTypes = <?php echo json_encode($issue_types); ?>;
document.getElementById('department').addEventListener('change', function(){
    const deptId = this.value;
    const typeSelect = document.getElementById('issue_type');

    // reset issue dropdown
    typeSelect.innerHTML = '<option value="">Select Issue Type</option>';

    // hide both custom fields first
    document.getElementById('otherDeptBox').style.display = "none";
    document.getElementById('otherIssueBox').style.display = "none";

    // if "Other" selected
    if(deptId === "other"){
        document.getElementById('otherDeptBox').style.display = "block";
        document.getElementById('otherIssueBox').style.display = "block";
        // Add a placeholder option so form validation passes
        let opt = document.createElement('option');
        opt.value = "other";
        opt.textContent = "Other (Custom)";
        opt.selected = true;
        typeSelect.appendChild(opt);
        return;
    }

    // normal filtering
    issueTypes.forEach(it => {
        if(it.dept_id == deptId){
            let opt = document.createElement('option');
            opt.value = it.type_id;
            opt.textContent = it.issue_name;
            typeSelect.appendChild(opt);
        }
    });

    // add "Other" inside issue type
    let opt = document.createElement('option');
    opt.value = "other";
    opt.textContent = "Other";
    typeSelect.appendChild(opt);
});

document.getElementById('issue_type').addEventListener('change', function(){
    if(this.value === "other"){
        document.getElementById('otherIssueBox').style.display = "block";
    } else {
        document.getElementById('otherIssueBox').style.display = "none";
    }
});

// ===== Image Preview =====
function previewImage(e){
    const file = e.target.files[0];
    if(!file) return;

    // Client-side size check
    if(file.size > 5 * 1024 * 1024){
        alert('Image too large. Maximum 5MB allowed.');
        e.target.value = '';
        return;
    }

    const img = document.getElementById('preview');
    img.src = URL.createObjectURL(file);
    img.style.display = 'block';
}

// ===== Map =====
const map = L.map('map').setView([20.5937,78.9629],5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let marker;

// Reverse Geocode
function getAddress(lat, lng){
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
    .then(res => res.json())
    .then(data => {
        document.getElementById('address').value = data.display_name || "Address not found";
    })
    .catch(() => {
        document.getElementById('address').value = "Could not detect address";
    });
}

// Current location
function getCurrentLocation(){
    if(navigator.geolocation){
        navigator.geolocation.getCurrentPosition(pos=>{
            let lat = pos.coords.latitude;
            let lng = pos.coords.longitude;

            map.setView([lat,lng],15);

            if(marker) map.removeLayer(marker);
            marker = L.marker([lat,lng]).addTo(map);

            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            getAddress(lat,lng);
        }, err=>{
            alert("Could not get location: " + err.message);
        });
    } else alert("Geolocation not supported");
}

// Map click
map.on('click', e=>{
    let lat = e.latlng.lat;
    let lng = e.latlng.lng;

    if(marker) map.removeLayer(marker);
    marker = L.marker([lat,lng]).addTo(map);

    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;

    getAddress(lat,lng);
});

// Search Location
function searchLocation(){
    const query = document.getElementById('searchAddress').value;
    if(!query) return;

    fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json`)
    .then(res => res.json())
    .then(data => {
        if(data.length > 0){
            const lat = parseFloat(data[0].lat);
            const lng = parseFloat(data[0].lon);

            map.setView([lat, lng], 15);

            if(marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);

            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            document.getElementById('address').value = data[0].display_name;
        } else alert("Location not found");
    }).catch(()=>alert("Error finding location"));
}
</script>

</body>
</html>