<?php
$conn=mysqli_connect("localhost","root","","otp_verification",3307);
if(!$conn){
    echo "Connection Failed ".mysqli_connect_error() or die();
}