<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

$faculty_id = $_SESSION['faculty_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $section_targeted = $_POST['section_targeted'];
    $class_type = $_POST['class_type'];
    
    // Generate base session ID
    $base_session_id = uniqid('ses_', true);
    $current_time = date('Y-m-d H:i:s');
    
    if ($class_type === 'lab') {
        // ============= CREATE 3 LAB SESSIONS =============
        $lab_types = [
            'pre-lab' => 0,    // Starts immediately
            'during-lab' => 1, // Starts after 1 hour  
            'post-lab' => 2    // Starts after 2 hours
        ];
        
        $created_sessions = 0;
        
        foreach ($lab_types as $lab_type => $hour_delay) {
            $session_id = $base
