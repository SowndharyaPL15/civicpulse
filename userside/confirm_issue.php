<?php
session_start();
include "config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["status"=>"error","message"=>"Not logged in"]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;

if($issue_id <= 0){
    echo json_encode(["status"=>"error","message"=>"Invalid issue"]);
    exit;
}

// check if already confirmed
$check = $conn->prepare("
    SELECT id FROM issue_confirmations
    WHERE issue_id=? AND user_id=?
");
$check->bind_param("ii",$issue_id,$user_id);
$check->execute();
$res=$check->get_result();

if($res->num_rows == 0){

    $stmt = $conn->prepare("
        INSERT INTO issue_confirmations(issue_id,user_id)
        VALUES(?,?)
    ");
    $stmt->bind_param("ii",$issue_id,$user_id);
    $stmt->execute();
}

// get updated count
$count = $conn->query("
    SELECT COUNT(*) c FROM issue_confirmations WHERE issue_id=$issue_id
")->fetch_assoc()['c'];

echo json_encode([
    "status"=>"success",
    "count"=>$count
]);
?>