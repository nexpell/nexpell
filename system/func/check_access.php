<?php

use nexpell\AccessControl;

function checkUserRoleAssignment($userID) {
    global $_database;

    $stmt = $_database->prepare("SELECT COUNT(*) AS cnt FROM `user_role_assignments` WHERE `userID` = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();
    return $result['cnt'] > 0;
}