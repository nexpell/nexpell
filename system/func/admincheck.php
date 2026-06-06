<?php
#session_start(); // Sicherstellen, dass die Session korrekt gestartet wird

if (isset($_POST['ws_user']) && isset($_POST['password'])) {
    $username = $_POST['ws_user'];
    $password = $_POST['password'];

    // Escapen des Benutzernamens (alternativ Prepared Statements)
    $escaped_username = escape($username); 

    $query = "
        SELECT 
            u.userID, 
            u.password, 
            r.role_name AS role
        FROM users u
        LEFT JOIN user_role_assignments ura ON ura.adminID = u.userID
        LEFT JOIN user_roles r ON r.roleID = ura.roleID
        WHERE u.username = '" . $escaped_username . "'
        LIMIT 1
    ";

    $result = safe_query($query);
    $row = mysqli_fetch_assoc($result);

    if ($row && password_verify($password, $row['password'])) {
        // Login erfolgreich: Session und Rolle setzen
        $_SESSION['userID'] = $row['userID'];
        $_SESSION['role'] = $row['role'];

        // Optional: Cookie für längere Login-Dauer setzen
        setcookie('nexpell_session', 'accepted', time() + 3600 * 24 * 30, '/');

        // Weiterleitung zum Admincenter
        header('Location: admin/admincenter.php');
        exit;
    } else {
        // Login fehlgeschlagen
        echo "<div class='alert alert-danger' role='alert'>Login fehlgeschlagen! Benutzername oder Passwort falsch.</div>";
    }
}



?>
