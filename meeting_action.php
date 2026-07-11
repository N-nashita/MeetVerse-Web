<?php
session_start();
require 'db.php';

if (!isset($_SESSION['emp_id'])) {
    header('Location: login.html');
    exit();
}

function isMeetingOverlapError(PDOException $e): bool
{
    return strpos($e->getMessage(), 'ORA-20010') !== false;
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
        $message = isMeetingOverlapError($e)
            ? 'Meeting time overlaps an existing meeting.'
            : 'Could not update meeting: ' . $e->getMessage();
        header("Location: edit_meeting.php?id=$meetingId&error=" . urlencode($message));
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
                    :organizer_id, :meeting_id_out
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
    $stmt->bindParam(':meeting_id_out', $meetingId, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40);
    $stmt->execute();

    $skipped = [];
    if (!empty($attendees)) {
        $attStmt = $pdo->prepare("BEGIN :result := check_attendee_conflict(:emp_id, :meeting_id); END;");
        foreach ($attendees as $empId) {
            $empId = (int)$empId;
            if ($empId === $organizerId) continue;
            $result = '';
            $attStmt->bindParam(':result',     $result,   PDO::PARAM_STR, 20);
            $attStmt->bindParam(':emp_id',     $empId,    PDO::PARAM_INT);
            $attStmt->bindParam(':meeting_id', $meetingId, PDO::PARAM_INT);
            $attStmt->execute();

    error_log("emp_id: $empId | meeting_id: $meetingId | result: $result");


            if ($result === 'CONFLICT') {
                $nameRow = $pdo->prepare("SELECT first_name || ' ' || last_name FROM employees WHERE emp_id = :id");
                $nameRow->execute([':id' => $empId]);
                $skipped[] = $nameRow->fetchColumn();
            }
        }
    }

    $msg = 'Meeting scheduled successfully';
    if (!empty($skipped)) {
        $msg .= '. Skipped due to conflict: ' . implode(', ', $skipped);
    }

    header("Location: $dashboardUrl?success=" . urlencode($msg));
    exit();

} catch (PDOException $e) {
    $message = isMeetingOverlapError($e)
        ? 'Meeting time overlaps an existing meeting.'
        : 'Could not schedule meeting: ' . $e->getMessage();
    header('Location: create_meeting.php?error=' . urlencode($message));
    exit();
}
?>