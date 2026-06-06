<?php

namespace nexpell;

class Captcha
{

    
    // Definiere die benötigten Eigenschaften
    public $hash;
    public $valide_time;

    public function __construct() {
        $this->hash = '';  // Initialisiere die Eigenschaft
        $this->valide_time = 0;  // Initialisiere die Eigenschaft
    }

    /* create captcha image/string and hash */
    public function createCaptcha()
    {
        // Erstelle einen sicheren Hash mit random_bytes
        $this->hash = bin2hex(random_bytes(16));  // Sicherer Hash

        $captcha = $this->generateCaptchaText();
        $captcha_result = $captcha['result'];
        $captcha_text = $captcha['text'];

        if ($this->type == 'g') {
            $captcha_text = $this->createCatpchaImage($captcha_text);
        }

        // Bereite die SQL-Abfrage vor, um den sicheren Hash und andere Daten zu speichern
        $sql = "INSERT INTO `captcha` (`hash`, `captcha`, `deltime`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($GLOBALS['db'], $sql);
        
        if ($stmt === false) {
            // Fehlerbehandlung bei der Vorbereitung der Abfrage
            die('Fehler bei der SQL-Abfrage-Vorbereitung: ' . mysqli_error($GLOBALS['db']));
        }

        // Setze die Parameter und führe die Abfrage aus
        $deltime = time() + ($this->valide_time * 60);
        mysqli_stmt_bind_param($stmt, 'ssi', $this->hash, $captcha_result, $deltime);

        if (!mysqli_stmt_execute($stmt)) {
            // Fehlerbehandlung bei der Ausführung der Abfrage
            die('Fehler bei der Ausführung der SQL-Abfrage: ' . mysqli_error($GLOBALS['db']));
        }

        mysqli_stmt_close($stmt);
        return $captcha_text;
    }

    /* create transaction hash for formulars */
    public function createTransaction()
    {

        $this->hash = md5(time() . rand(0, 10000));
        safe_query(
            "INSERT INTO `captcha`(
            `hash`,`captcha`,`deltime`
            )VALUES (
            '" . $this->hash . "',
            '0',
            '" . (time() + ($this->valide_time * 60)) . "'
            )"
        );
        return true;
    }

    /* print created hash */
    public function getHash()
    {

        return $this->hash;
    }

    /* check if input fits captcha */
    public function checkCaptcha($input, $hash)
    {

        if (mysqli_num_rows(
            safe_query(
                "SELECT `hash`
                    FROM `captcha`
                    WHERE
                        `captcha` = '" . $input . "' AND
                        `hash` = '" . $hash . "'"
            )
        )
        ) {
            safe_query(
                "DELETE FROM `captcha`
                WHERE
                    `captcha` = '" . $input . "' AND
                    `hash` = '" . $hash . "'"
            );
            $file = 'tmp/' . $hash . '.jpg';
            if (file_exists($file)) {
                unlink($file);
            }
            return true;
        } else {
            return false;
        }
    }

    /* remove old captcha files */
    public function clearOldCaptcha()
    {
        $time = time();
        $ergebnis = safe_query("SELECT `hash` FROM `captcha` WHERE `deltime` < " . $time);

        while ($ds = mysqli_fetch_array($ergebnis)) {
            $file = 'tmp/' . $ds['hash'] . '.jpg';
            if (file_exists($file)) {
                if (!unlink($file)) {
                    // Fehlerbehandlung beim Löschen der Datei
                    echo "Fehler beim Löschen der Datei: $file";
                }
            } else {
                $file = '../' . $file;
                if (file_exists($file)) {
                    if (!unlink($file)) {
                        // Fehlerbehandlung beim Löschen der Datei
                        echo "Fehler beim Löschen der Datei: $file";
                    }
                }
            }
        }

        // Lösche alte Captcha-Daten aus der Datenbank
        safe_query("DELETE FROM `captcha` WHERE `deltime` < " . $time);
    }
}