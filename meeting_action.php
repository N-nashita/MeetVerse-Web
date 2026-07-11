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

function formatOracleDateTime(string $value): string
{
    $timestamp = strtotime(str_replace('T', ' ', $value));
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
}

function getOracleConnectionFromPdo($pdo)
{
    if (method_exists($pdo, 'getConnection')) {
        return $pdo->getConnection();
    }

    throw new PDOException('Oracle connection is not available.');
}

function buildAttendeeCollection($connection, array $attendees)
{
    $collection = oci_new_collection($connection, 'ATTENDEE_ID_TABLE');
    if (!$collection) {
        throw new PDOException('Could not create Oracle attendee collection.');
    }

    foreach ($attendees as $attendeeId) {
        $attendeeId = (int)$attendeeId;
        if ($attendeeId > 0) {
            $collection->append($attendeeId);
        }
    }

    return $collection;
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

    if (!$title || !$start_time || !$end_time) {
        header("Location: edit_meeting.php?id=$meetingId&error=" . urlencode('Please fill in all required fields'));
        exit();
    }

    try {
        $connection = getOracleConnectionFromPdo($pdo);
        $attendeeCollection = buildAttendeeCollection($connection, $attendees);
        $meetingIdValue = $meetingId;
        $requesterIdValue = (int)$_SESSION['emp_id'];
        $titleValue = $title;
        $descriptionValue = $description;
        $startValue = formatOracleDateTime($start_time);
        $endValue = formatOracleDateTime($end_time);

        $sql = "BEGIN
                    update_meeting_with_attendees(
                        :meeting_id, :requester_id, :title, :description,
                        TO_DATE(:start_time, 'YYYY-MM-DD HH24:MI:SS'),
                        TO_DATE(:end_time, 'YYYY-MM-DD HH24:MI:SS'),
                        :attendee_ids
                    );
                END;";
        $stmt = oci_parse($connection, $sql);
        oci_bind_by_name($stmt, ':meeting_id', $meetingIdValue, 40, SQLT_INT);
        oci_bind_by_name($stmt, ':requester_id', $requesterIdValue, 40, SQLT_INT);
        oci_bind_by_name($stmt, ':title', $titleValue, -1, SQLT_CHR);
        oci_bind_by_name($stmt, ':description', $descriptionValue, -1, SQLT_CHR);
        oci_bind_by_name($stmt, ':start_time', $startValue, -1, SQLT_CHR);
        oci_bind_by_name($stmt, ':end_time', $endValue, -1, SQLT_CHR);
        oci_bind_by_name($stmt, ':attendee_ids', $attendeeCollection, -1, SQLT_NTY);

        if (!oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $error = oci_error($stmt);
            throw new PDOException('Could not execute Oracle statement. ' . ($error['message'] ?? ''));
        }

        oci_free_statement($stmt);
        if ($attendeeCollection) {
            $attendeeCollection->free();
        }
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

try {
    $connection = getOracleConnectionFromPdo($pdo);
    $attendeeCollection = buildAttendeeCollection($connection, $attendees);
    $meetingId = 0;
    $titleValue = $title;
    $descriptionValue = $description;
    $departmentValue = $department;
    $startValue = formatOracleDateTime($start_time);
    $endValue = formatOracleDateTime($end_time);
    $organizerIdValue = (int)$organizerId;

    $sql = "BEGIN
                create_meeting_with_attendees(
                    :title, :description, :department,
                    TO_DATE(:start_time, 'YYYY-MM-DD HH24:MI:SS'),
                    TO_DATE(:end_time, 'YYYY-MM-DD HH24:MI:SS'),
                    :organizer_id, :attendee_ids, :meeting_id_out
                );
            END;";

    $stmt = oci_parse($connection, $sql);
    oci_bind_by_name($stmt, ':title', $titleValue, -1, SQLT_CHR);
    oci_bind_by_name($stmt, ':description', $descriptionValue, -1, SQLT_CHR);
    oci_bind_by_name($stmt, ':department', $departmentValue, -1, SQLT_CHR);
    oci_bind_by_name($stmt, ':start_time', $startValue, -1, SQLT_CHR);
    oci_bind_by_name($stmt, ':end_time', $endValue, -1, SQLT_CHR);
    oci_bind_by_name($stmt, ':organizer_id', $organizerIdValue, 40, SQLT_INT);
    oci_bind_by_name($stmt, ':attendee_ids', $attendeeCollection, -1, SQLT_NTY);
    oci_bind_by_name($stmt, ':meeting_id_out', $meetingId, 40, SQLT_INT);

    if (!oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $error = oci_error($stmt);
        throw new PDOException('Could not execute Oracle statement. ' . ($error['message'] ?? ''));
    }

    oci_free_statement($stmt);
    if ($attendeeCollection) {
        $attendeeCollection->free();
    }

    header("Location: $dashboardUrl?success=" . urlencode('Meeting scheduled successfully'));
    exit();

} catch (PDOException $e) {
    $message = isMeetingOverlapError($e)
        ? 'Meeting time overlaps an existing meeting.'
        : 'Could not schedule meeting: ' . $e->getMessage();
    header('Location: create_meeting.php?error=' . urlencode($message));
    exit();
}
?>