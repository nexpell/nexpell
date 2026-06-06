<?php

namespace nexpell;

class LoginSecurity 
{


    // Key dynamisch aus Konstante holen
    private static function getAesKey(): string
    {
        if (!defined('AES_KEY') || strlen(AES_KEY) !== 32) {
            throw new RuntimeException('AES_KEY ist nicht definiert oder hat nicht die korrekte Länge von 32 Zeichen.');
        }
        return AES_KEY;
    }

    public static function encryptPepper(string $plain_pepper): ?string
    {
        $key = self::getAesKey();
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($plain_pepper, 'aes-256-cbc', $key, 0, $iv);
        if ($encrypted === false) {
            return null;
        }
        return base64_encode($iv . $encrypted);
    }

    public static function decryptPepper(string $encrypted_pepper): ?string
    {
        $key = self::getAesKey();
        $data = base64_decode($encrypted_pepper);
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $iv_length);
        $ciphertext = substr($data, $iv_length);
        return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
    }

    public static function createPasswordHash(string $password_hash, string $email, string $pepper): string {
        return password_hash($password_hash . $email . $pepper, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password_hash, string $email, string $pepper, string $hash): bool {
        return password_verify($password_hash . $email . $pepper, $hash);
    }

    // Methode zum Generieren eines lesbaren Passworts
    public static function generateReadablePassword(int $length = 10): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; // gut lesbare Zeichen
        $password_hash = '';

        for ($i = 0; $i < $length; $i++) {
            $password_hash .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password_hash;
    }
    

    public static function verifyLogin($email, $password_hash, $ip, $is_active , $banned): array {
        // Zuerst prüfen, ob IP gesperrt ist
        $isIpBanned = self::isIpBanned($ip); // IP-Überprüfung
        if ($isIpBanned) {
            return ['success' => false, 'ip_banned' => true, 'error' => 'Deine IP-Adresse wurde gesperrt.'];
        }

        // Benutzer aus der Datenbank abrufen
        $query = "SELECT * FROM `users` WHERE `email` = '" . self::escape($email) . "'";
        $result = safe_query($query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_array($result);

            // Überprüfen, ob das Konto aktiv ist
            if ($user['is_active'] == 0) {
                return ['success' => false, 'ip_banned' => false, 'error' => 'Dein Konto wurde noch nicht aktiviert. Bitte überprüfe deine E-Mail.'];
            }

            // Überprüfen, ob der User gebannt ist
            if (isset($user['banned']) && $user['banned'] == 1) {
                return ['success' => false, 'ip_banned' => false, 'error' => 'Dein Konto wurde gesperrt. Bitte überprüfe deine E-Mail.'];
            }

            // Entschlüsseln des Peppers
            $pepper_plain = self::decryptPepper($user['password_pepper']);
            if (!$pepper_plain) {
                return ['success' => false, 'ip_banned' => false, 'error' => 'Fehler beim Entschlüsseln des Peppers.'];
            }

            // Passwort mit E-Mail und Pepper kombinieren und überprüfen
            if (password_verify($password_hash . $email . $pepper_plain, $user['password_hash'])) {
                // Erfolgreiches Login
                return ['success' => true, 'ip_banned' => false];
            } else {
                // Falsches Passwort
                return ['success' => false, 'ip_banned' => false, 'error' => 'Ungültige E-Mail-Adresse oder Passwort.'];
            }

        } else {
            // Keine Benutzer gefunden
            return ['success' => false, 'ip_banned' => false, 'error' => 'Ungültige E-Mail-Adresse oder Passwort.'];
        }
    }



    public static function handleLoginError(array $loginResult, int $failCount, string $ip, ?int $userID, string $email): array
    {
        $isIpBanned = false;
        $message_zusatz = '';

        // Konto nicht aktiviert
        if (str_contains($loginResult['error'], 'noch nicht aktiviert')) {
            $isIpBanned = true;
            $message_zusatz .= '<div class="alert alert-warning" role="alert">Dein Konto wurde noch nicht aktiviert. Bitte überprüfe deine E-Mail.</div>';
        }

        // Benutzer gebannt
        elseif (str_contains($loginResult['error'], 'gebannt')) {
            $isIpBanned = true;
            $message_zusatz .= '<div class="alert alert-danger" role="alert">Dein Konto wurde gesperrt. Bitte kontaktiere den Support.</div>';
        }

        // Fehlversuche prüfen
        else {
            if ($failCount >= 5) {
                self::banIp($ip, $userID, "Zu viele Fehlversuche", $email);
                $message_zusatz .= '<div class="alert alert-danger" role="alert">Zu viele Fehlversuche – Deine IP wurde gesperrt.</div>';
                $isIpBanned = true;
            } else {
                $message_zusatz .= '<div class="alert alert-danger" role="alert">Versuche: ' . $failCount . ' / 5</div>';
            }
        }

        return [
            'isIpBanned' => $isIpBanned,
            'message_zusatz' => $message_zusatz
        ];
    }

    public static function logFailedLogin(int $userID, string $ip, string $reason, ?string $email = null): void
    {
        global $_database;

        $stmt = $_database->prepare("
            INSERT INTO failed_login_attempts (userID, ip, attempt_time, status, reason, email)
            VALUES (?, ?, NOW(), 'failed', ?, ?)
        ");
        $stmt->bind_param("isss", $userID, $ip, $reason, $email);
        $stmt->execute();
        $stmt->close();
    }

    public static function isEmailBanned(string $email, string $ip): bool
    {
        global $_database;

        // Zuerst fehlgeschlagene Login-Versuche für diese IP löschen
        $query = "DELETE FROM failed_login_attempts WHERE ip = '" . self::escape($ip) . "'";
        safe_query($query);

        $stmt = $_database->prepare("SELECT 1 FROM banned_ips WHERE email = ? AND (deltime IS NULL OR deltime > NOW())");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }

    public static function isEmailOrIpBanned(string $email, string $ip): bool
    {
        global $_database;

        $stmt = $_database->prepare("SELECT 1 FROM banned_ips WHERE (email = ? OR ip = ?) AND (deltime IS NULL OR deltime > NOW())");
        $stmt->bind_param("ss", $email, $ip);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }


    // Fehlversuche löschen
    public static function clearFailedAttempts(string $ip): void {
        safe_query("DELETE FROM failed_login_attempts WHERE ip = '" . escape($ip) . "'");
    }

    // Generierung eines zufälligen Peppers
    public static function generatePepper(int $length = 16): string {
        if (!is_int($length) || $length <= 0) {
            throw new \InvalidArgumentException('Länge des Peppers muss eine positive Ganzzahl sein.');
        }

        return bin2hex(random_bytes($length)); // Hexadezimale Darstellung eines zufälligen Bytes
    }

    // Passwort zurücksetzen und neuen Pepper und Hash speichern
    public static function resetPassword(int $userID, string $newPassword): void {
        // Neuer Pepper wird generiert
        $pepper = self::generatePepper();
        // Neues Passwort wird gehasht mit password_hash()
        $newPasswordHash = password_hash($newPassword . $pepper, PASSWORD_BCRYPT);

        // Passwort-Hash und Pepper in der Datenbank aktualisieren
        safe_query("
            UPDATE users
            SET password_hash = '" . escape($newPasswordHash) . "', password_pepper = '" . escape($pepper) . "'
            WHERE userID = " . intval($userID)
        );
    }

    public static function getFailCount(string $ip, string $email): int
    {
        global $_database;

        // Zähle die fehlgeschlagenen Login-Versuche für eine bestimmte IP und E-Mail
        $stmt = $_database->prepare("SELECT COUNT(*) FROM failed_login_attempts WHERE ip = ? AND email = ? AND status = 'failed' AND attempt_time > NOW() - INTERVAL 24 HOUR");
        $stmt->bind_param("ss", $ip, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int) $row['COUNT(*)'];
    }

    // Funktion zum Verfolgen eines fehlgeschlagenen Logins
    public static function trackFailedLogin(?int $userID, string $email, string $ip): void
    {
        global $_database;

        // Benutzer-ID ermitteln, falls nicht vorhanden
        if (is_null($userID)) {
            $stmt = $_database->prepare("SELECT userID FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $userID = (int)$row['userID'];
            } else {
                $userID = 0; // Wenn Benutzer nicht gefunden wurde
            }
            $stmt->close();
        }

        $reason = "Login fehlgeschlagen";
        $status = "failed";

        $stmt = $_database->prepare("INSERT INTO failed_login_attempts (userID, email, ip, status, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userID, $email, $ip, $status, $reason);
        $stmt->execute();
        $stmt->close();
    }


    // Funktion zum Sperren der IP
    public static function banIp($ip, $userID, $reason = "", $email = "") {
        global $_database;

        // Zuerst fehlgeschlagene Login-Versuche für diese IP löschen
        $query = "DELETE FROM `failed_login_attempts` WHERE `ip` = '" . self::escape($ip) . "'";
        safe_query($query);

        // Bannzeit auf 3 Stunden setzen
        $banTime = date('Y-m-d H:i:s', strtotime('+3 hours'));  // Sperre für 3 Stunden

        // SQL-Abfrage zum Sperren der IP
        $query = "INSERT INTO `banned_ips` (ip, userID, reason, email, deltime) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $_database->prepare($query)) {
            // Benutzung des richtigen Typs für die Parameter
            $stmt->bind_param("sisss", $ip, $userID, $reason, $email, $banTime);

            if ($stmt->execute()) {
                // Erfolgreich gespeichert
                return true;
            } else {
                // Fehler bei der Ausführung der SQL-Abfrage
                echo "Fehler beim Ausführen der Abfrage: " . $stmt->error;
                return false;
            }
        } else {
            // Fehler beim Vorbereiten der Abfrage
            echo "Fehler beim Vorbereiten der Abfrage: " . $_database->error;
            return false;
        }
    }


    public static function isIpAlreadyBanned($ip): bool {
        $query = "SELECT 1 FROM `banned_ips` WHERE `ip` = '" . self::escape($ip) . "' LIMIT 1";
        $result = safe_query($query);
        return mysqli_num_rows($result) > 0;
    }

    
    private static function anonymize_ip(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = '0000';
            return implode(':', $parts);
        }

        return $ip;
    }

    // Funktion zum Speichern der Session nach erfolgreichem Login
    public static function saveSession(int $userID): void {
        global $_database;

        $sessionID   = session_id();
        $userIP      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userIP      = self::anonymize_ip($userIP); // IP anonymisieren
        $browser     = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $sessionData = serialize($_SESSION); // optional
        $lastActivity = time();

        // Prüfen, ob bereits eine Session mit dieser ID existiert
        $checkStmt = $_database->prepare("SELECT id FROM user_sessions WHERE session_id = ?");
        $checkStmt->bind_param('s', $sessionID);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Update bestehender Session
            $updateStmt = $_database->prepare("
                UPDATE user_sessions 
                SET userID = ?, user_ip = ?, session_data = ?, browser = ?, last_activity = ?
                WHERE session_id = ?
            ");
            $updateStmt->bind_param('isssis', $userID, $userIP, $sessionData, $browser, $lastActivity, $sessionID);
            $updateStmt->execute();
        } else {
            // Neue Session speichern
            $insertStmt = $_database->prepare("
                INSERT INTO user_sessions (session_id, userID, user_ip, session_data, browser, last_activity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->bind_param('sisssi', $sessionID, $userID, $userIP, $sessionData, $browser, $lastActivity);
            $insertStmt->execute();
        }

        // ==========================
        // Alte Sessions löschen (> 30 Tage)
        // ==========================
        $deleteOldStmt = $_database->prepare("
            DELETE FROM user_sessions
            WHERE last_activity < ?
        ");
        $threshold = time() - (30 * 24 * 60 * 60); // 30 Tage
        $deleteOldStmt->bind_param('i', $threshold);
        $deleteOldStmt->execute();
    }

    // Funktion zur Überprüfung, ob die IP-Adresse des Nutzers gesperrt ist
    public static function isIpBanned(string $ip, ?string $email = null): bool {
        global $_database;

        $stmt = $_database->prepare("SELECT COUNT(*) FROM banned_ips WHERE ip = ? OR email = ?");
        $stmt->bind_param('ss', $ip, $email);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();

        return ($count > 0);
    }

    public static function cleanupExpiredBans() {
        global $_database;

        // Bereinigen von Banns, die abgelaufen sind
        $now = date('Y-m-d H:i:s');
        $_database->query("DELETE FROM banned_ips WHERE deltime <= '$now'");
    }

    public static function tooManyFailedAttempts(int $userID, string $ip, int $max = 5): bool
    {
        global $_database;

        $stmt = $_database->prepare("
            SELECT COUNT(*) FROM failed_login_attempts
            WHERE userID = ? AND ip = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)
        ");
        $stmt->bind_param('is', $userID, $ip);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();

        return ($count >= $max);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateRandomPepper($length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Erzeugt ein zufälliges Token
        }
        return $_SESSION['csrf_token'];
    }

}
