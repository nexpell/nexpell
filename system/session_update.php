<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
global $_database;

$IDLE_LIMIT = 300;

if ($_database instanceof mysqli) {
    $cleanupSql = "
        UPDATE users
        SET
            total_online_seconds = total_online_seconds + LEAST(
                GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(last_activity, login_time, NOW()), NOW()), 0),
                ?
            ),
            is_online = 0,
            last_activity = NULL,
            login_time = NULL
        WHERE is_online = 1
          AND last_activity IS NOT NULL
          AND last_activity < (NOW() - INTERVAL ? SECOND)
    ";
    $cleanupStmt = $_database->prepare($cleanupSql);
    if ($cleanupStmt) {
        $cleanupStmt->bind_param("ii", $IDLE_LIMIT, $IDLE_LIMIT);
        $cleanupStmt->execute();
        $cleanupStmt->close();
    }
}

if (!empty($_SESSION['userID'])) {
    $userID = (int)$_SESSION['userID'];

    $sql = "
        UPDATE users
        SET 
            total_online_seconds = total_online_seconds + LEAST(
                GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(last_activity, login_time, NOW()), NOW()), 0),
                ?
            ),
            last_activity = NOW(),
            is_online = 1
        WHERE userID = ?
    ";
    $stmt = $_database->prepare($sql);
    $stmt->bind_param("ii", $IDLE_LIMIT, $userID);
    $stmt->execute();
    $stmt->close();
}
