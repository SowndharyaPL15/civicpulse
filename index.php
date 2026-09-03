<?php
/**
 * CivicPulse — Root Entry Point
 * Redirects visitors to the Citizen portal landing page.
 */
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: userside/home.php");
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    header("Location: adminside/dashboard.php");
    exit;
} else {
    header("Location: userside/about.php");
    exit;
}
