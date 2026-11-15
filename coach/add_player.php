<?php
session_start();

// Force strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

// Verify session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    die("Error: You must be logged in as a coach to add players.");
}

// Ensure the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $sport_id = $_SESSION['sport_id'];

    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        die("Error: All fields are required. Please go back and try again.");
    }

    try {
        // Hash the password securely
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into the database
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, sport_id) VALUES (?, ?, ?, 'player', ?)");
        $stmt->execute([$name, $email, $password_hash, $sport_id]);
        
        // Success! Set a flash message and redirect back to dashboard
        $_SESSION['coach_dashboard_flash'] = "Player added successfully!";
        header("Location: dashboard.php");
        exit;

    } catch (PDOException $e) {
        // If the database rejects it (e.g. duplicate email), this prints the exact reason
        die("Database Error: " . $e->getMessage());
    }
} else {
    die("Error: Invalid request method. The form must use POST.");
}
?>