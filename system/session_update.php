<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
global $_database;

if (!empty($_SESSION['userID'])) {
    $userID = (int)$_SESSION['userID'];

    // Maximal anrechenbare Zeit zwischen 2 Requests (Sekunden)
    $IDLE_LIMIT = 300;

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
