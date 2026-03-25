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
    $department_id = intval($_POST['department_id']);
    $issue_type_id = intval($_POST['issue_type_id']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : NULL;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : NULL;
    $image_path = NULL;

    // Handle image upload
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $allowed = ['jpg','jpeg','png','gif'];
        $file_name = $_FILES['image']['name'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if(in_array($ext, $allowed)){
            $new_name = uniqid('complaint_', true) . '.' . $ext;
            $upload_dir = 'uploads/';
            if(!is_dir($upload_dir)){
                mkdir($upload_dir, 0755, true);
            }
            $destination = $upload_dir . $new_name;

            if(move_uploaded_file($file_tmp, $destination)){
                $image_path = $destination;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image format. Only JPG, PNG, GIF allowed.";
        }
    }

    if(empty($error) && !empty($department_id) && !empty($issue_type_id) && !empty($description)){
        $stmt = $conn->prepare("INSERT INTO complaints (user_id, department_id, issue_type_id, description, image, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissdd", $user_id, $department_id, $issue_type_id, $description, $image_path, $latitude, $longitude);

        if($stmt->execute()){
            $success = "Complaint submitted successfully!";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Submit Complaint</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ===== Base ===== */
body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: #f4f6f9;
    color: #333;
}
.container {
    max-width: 600px;
    margin: 50px auto;
    padding: 0 15px;
}

/* ===== Form Card ===== */
.form-card {
    background: #fff;
    border-radius: 12px;
    padding: 35px 30px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.form-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
}
.form-card h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #1f4e79;
    font-weight: 700;
    font-size: 2rem;
}

/* ===== Inputs ===== */
label {
    font-weight: 500;
    margin-bottom: 6px;
    display: block;
    color: #555;
}
input[type="text"], input[type="file"], select, textarea {
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 18px;
    border-radius: 8px;
    border: 1px solid #d1d9e6;
    font-size: 15px;
    background: #f9fafb;
    transition: all 0.3s ease;
}
input[type="text"]:focus, select:focus, textarea:focus {
    border-color: #1f4e79;
    outline: none;
    box-shadow: 0 0 8px rgba(31,78,121,0.2);
}

/* ===== Button ===== */
button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #1f4e79, #163a5f);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
button:hover {
    background: linear-gradient(135deg, #163a5f, #1f4e79);
    box-shadow: 0 8px 20px rgba(31,78,121,0.3);
    transform: translateY(-2px);
}

/* ===== Image Preview ===== */
#image-preview {
    display: none;
    max-width: 100%;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

/* ===== Map ===== */
#map {
    height: 300px;
    border-radius: 10px;
    margin-bottom: 18px;
}

/* ===== Alerts ===== */
.success {
    background-color:#d4edda; color:#155724;
    padding:12px; border-radius:6px; margin-bottom:15px;
    border-left:5px solid #28a745; font-weight:500;
}
.error {
    background-color:#f8d7da; color:#721c24;
    padding:12px; border-radius:6px; margin-bottom:15px;
    border-left:5px solid #dc3545; font-weight:500;
}

/* ===== Back Link ===== */
.back-link {
    display: block;
    margin-top: 15px;
    text-align: center;
    font-weight: 500;
    color: #1f4e79;
    text-decoration: none;
}
.back-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
<div class="form-card">
<h2>Submit Complaint</h2>

<?php if(!empty($success)) echo '<p class="success">'.$success.'</p>'; ?>
<?php if(!empty($error)) echo '<p class="error">'.$error.'</p>'; ?>

<form method="POST" action="" enctype="multipart/form-data">
<label for="department">Select Department</label>
<select name="department_id" id="department" required>
<option value="">--Select Department--</option>
<?php
if($dept_result->num_rows > 0){
    while($row = $dept_result->fetch_assoc()){
        echo "<option value='".$row['dept_id']."'>".htmlspecialchars($row['dept_name'])."</option>";
    }
}
?>
</select>

<label for="issue_type">Select Issue Type</label>
<select name="issue_type_id" id="issue_type" required>
<option value="">--Select Issue Type--</option>
</select>

<label for="description">Description</label>
<textarea name="description" id="description" rows="4" placeholder="Describe your complaint..." required></textarea>

<label for="image">Upload Image (optional)</label>
<input type="file" name="image" id="image" accept="image/*" onchange="previewImage(event)">
<img id="image-preview" src="#" alt="Image Preview">

<label>Pick Your Location (optional)</label>
<div id="map"></div>
<input type="hidden" name="latitude" id="latitude">
<input type="hidden" name="longitude" id="longitude">

<button type="submit" name="submit_complaint">Submit Complaint</button>
</form>

<a class="back-link" href="home.php">← Back to Dashboard</a>
</div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Department → Issue Type filter
const issueTypes = <?php echo json_encode($issue_types); ?>;
const deptSelect = document.getElementById('department');
const typeSelect = document.getElementById('issue_type');

deptSelect.addEventListener('change', function(){
    const deptId = parseInt(this.value);
    typeSelect.innerHTML = '<option value="">--Select Issue Type--</option>';
    issueTypes.forEach(it => {
        if(it.dept_id == deptId){
            const opt = document.createElement('option');
            opt.value = it.type_id;
            opt.textContent = it.issue_name;
            typeSelect.appendChild(opt);
        }
    });
});

// Image preview
function previewImage(event) {
    const preview = document.getElementById('image-preview');
    preview.src = URL.createObjectURL(event.target.files[0]);
    preview.style.display = 'block';
}

// Leaflet map
const map = L.map('map').setView([20.5937, 78.9629], 5); // India
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let marker;
map.on('click', function(e){
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    if(marker) { map.removeLayer(marker); }
    marker = L.marker([lat, lng]).addTo(map);
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
});
</script>
</body>
</html>
