<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

$action = $_POST['action'] ?? 'create';
$dashboardUrl = ($_SESSION['role'] === 'ADMIN') ? 'admin_dashboard.php' : 'member_dashboard.php';

if ($action === 'delete') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("BEGIN cancel_meeting(:meeting_id, :requester_id); END;");
        $stmt->bindValue(':meeting_id', $meetingId);
        $stmt->bindValue(':requester_id', $_SESSION['emp_id']);
        $stmt->execute();
        header("Location: meeting.php?success=" . urlencode('Meeting cancelled'));
    } catch (PDOException $e) {
        header('Location: meeting.php?error=' . urlencode('Could not cancel meeting: ' . $e->getMessage()));
    }
    exit();
}

if ($action === 'update') {
    $meetingId   = (int)$_POST['meeting_id'];
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_time  = $_POST['start_time'];
    $end_time    = $_POST['end_time'];
    $attendees   = $_POST['attendees'] ?? [];
    $attendeeCsv = implode(',', array_map('intval', $attendees));

    if (!$title || !$start_time || !$end_time) {
        header("Location: edit_meeting.php?id=$meetingId&error=" . urlencode('Please fill in all required fields'));
        exit();
    }

    try {
        $stmt = $pdo->prepare("BEGIN update_meeting_with_attendees(
                    :meeting_id, :requester_id, :title, :description,
                    TO_DATE(:start_time, 'YYYY-MM-DD HH24:MI'),
                    TO_DATE(:end_time, 'YYYY-MM-DD HH24:MI'),
                    :attendee_csv
                ); END;");
        $stmt->bindValue(':meeting_id', $meetingId);
        $stmt->bindValue(':requester_id', $_SESSION['emp_id']);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':start_time', str_replace('T', ' ', $start_time));
        $stmt->bindValue(':end_time', str_replace('T', ' ', $end_time));
        $stmt->bindValue(':attendee_csv', $attendeeCsv);
        $stmt->execute();
        header("Location: meeting.php?success=" . urlencode('Meeting updated'));
    } catch (PDOException $e) {
        header("Location: edit_meeting.php?id=$meetingId&error=" . urlencode('Could not update meeting: ' . $e->getMessage()));
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: create_meeting.php');
    exit();
}

$title       = trim($_POST['title']);
$description = trim($_POST['description']);
$start_time  = $_POST['start_time'];
$end_time    = $_POST['end_time'];
$attendees   = $_POST['attendees'] ?? [];
$organizerId = $_SESSION['emp_id'];

if (!$title || !$start_time || !$end_time) {
    header('Location: create_meeting.php?error=Please fill in all required fields');
    exit();
}

$deptStmt = $pdo->prepare("SELECT department FROM employees WHERE emp_id = :id");
$deptStmt->execute(['id' => $organizerId]);
$department = $deptStmt->fetchColumn();

$attendeeCsv = implode(',', array_map('intval', $attendees));

try {
    $sql = "BEGIN
                create_meeting_with_attendees(
                    :title, :description, :department,
                    TO_DATE(:start_time, 'YYYY-MM-DD HH24:MI'),
                    TO_DATE(:end_time, 'YYYY-MM-DD HH24:MI'),
                    :organizer_id, :attendee_csv, :meeting_id_out
                );
            END;";

    $stmt = $pdo->prepare($sql);
    $meetingId = 0;

    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':department', $department);
    $stmt->bindValue(':start_time', str_replace('T', ' ', $start_time));
    $stmt->bindValue(':end_time', str_replace('T', ' ', $end_time));
    $stmt->bindValue(':organizer_id', $organizerId);
    $stmt->bindValue(':attendee_csv', $attendeeCsv);
    $stmt->bindParam(':meeting_id_out', $meetingId, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40);

    $stmt->execute();

    $dashboardUrl = ($_SESSION['role'] === 'ADMIN') ? 'admin_dashboard.php' : 'member_dashboard.php';
    header("Location: $dashboardUrl?success=Meeting scheduled");
    exit();

} catch (PDOException $e) {
    header('Location: create_meeting.php?error=' . urlencode('Could not schedule meeting: ' . $e->getMessage()));
    exit();
}
?>