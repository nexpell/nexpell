<?php

# Datei muss noch überprüft werden!!!


#userlist verwendiese funktion
function getusername($userID) {
    $erg = safe_query("SELECT username FROM users WHERE `userID` = " . (int)$userID);
    if(mysqli_num_rows($erg) == '1') {
        $ds = mysqli_fetch_array(safe_query("SELECT username FROM users WHERE `userID` = " . (int)$userID));
        return $ds['username'];
    } else {
        $ds = mysqli_fetch_array(safe_query("SELECT username FROM user_username WHERE `userID` = " . (int)$userID));
        return '<s>'.@$ds['username'].'</s>';
    }
}

function getuserpic($userID)
{
    $ds = mysqli_fetch_array(safe_query("SELECT userpic,username FROM users WHERE `userID` = " . (int)$userID . ""));
    if (empty($ds['userpic'])) {
        return "svg-avatar.php?name=".@$ds['username']."G";
    }

    return $ds['userpic'];
}

function getavatar($userID)
{
    $userID = (int)$userID;

    $ds = mysqli_fetch_array(safe_query("
        SELECT u.username, p.avatar
        FROM users u
        LEFT JOIN user_profiles p ON u.userID = p.userID
        WHERE u.userID = $userID
    "));

    // Username für Fallback-Avatar bestimmen
    $username = !empty($ds['username']) ? $ds['username'] : 'Gelöschtes Mitglied';

    // Wenn Avatar vorhanden → zurückgeben
    if (!empty($ds['avatar'])) {
        return $ds['avatar'];
    }

    // Andernfalls dynamischen SVG-Avatar zurückgeben
    return '/images/avatars/svg-avatar.php?name=' . urlencode($username);
}

function getemail($userID)
{
    $ds = mysqli_fetch_array(safe_query("SELECT email FROM users WHERE `userID` = " . (int)$userID));
    if(isset($ds))
    return getinput($ds['email']);
}