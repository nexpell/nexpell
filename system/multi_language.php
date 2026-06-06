<?php

class multiLanguage {

    public $language;
    public $availableLanguages = array();

    public function __construct($lang) {
        $this->language = $lang;
    }

    /**
     * Ermittelt alle verfügbaren Sprachen im gegebenen Text.
     * 
     * @param string $text Der zu untersuchende Text mit [[lang:xx]]-Tags
     */
    public function detectLanguages($text) {
        // Suche nach Sprach-Tags wie [[lang:xx]]
        preg_match_all('/\[\[lang:([a-z]{2})\]\]/i', $text, $matches);
        
        // Wenn keine Sprach-Tags gefunden werden, wird ein leeres Array verwendet
        if (empty($matches[1])) {
            $this->availableLanguages = array();
        } else {
            $this->availableLanguages = array_unique($matches[1]);
        }
    }

    /**
     * Gibt den Text in der ausgewählten Sprache zurück.
     * 
     * @param string $text Der mehrsprachige Text
     * @return string Der passende Textausschnitt
     */
    /*public function getTextByLanguage($text) {
        // Wenn keine Sprachmarkierungen im Text vorhanden sind, gib den Originaltext zurück
        if (empty($this->availableLanguages)) {
            return $text; // Hier wird der Originaltext immer zurückgegeben, wenn keine Sprach-Tags vorhanden sind
        }

        // Prüfen, ob die gewählte Sprache verfügbar ist
        if (in_array($this->language, $this->availableLanguages)) {
            return $this->getTextByTag($this->language, $text);
        } 
        // Wenn keine Übersetzung für die aktuelle Sprache gefunden wird, gibt es den ersten verfügbaren Text zurück
        elseif (!empty($this->availableLanguages)) {
            return $this->getTextByTag($this->availableLanguages[0], $text);
        } 
        // Wenn keine Übersetzungen vorhanden sind, gib den Originaltext zurück
        else {
            return $text;
        }
    }*/

    public function getTextByLanguage($text) {
        // Verfügbare Sprachen aus dem Text erkennen
        $this->detectLanguages($text);

        // Wenn keine Sprachmarkierungen im Text vorhanden sind, gib den Originaltext zurück
        if (empty($this->availableLanguages)) {
            return $text;
        }

        // Prüfen, ob die gewählte Sprache verfügbar ist
        if (in_array($this->language, $this->availableLanguages)) {
            return $this->getTextByTag($this->language, $text);
        } 
        // Wenn keine Übersetzung für die aktuelle Sprache gefunden wird, gibt es den ersten verfügbaren Text zurück
        return $this->getTextByTag($this->availableLanguages[0], $text);
    }


    /**
     * Holt den konkreten Textabschnitt einer Sprache
     * 
     * @param string $language Sprachkürzel
     * @param string $text Ursprünglicher Text mit Sprachblöcken
     * @return string Nur der passende Text
     */
    private function getTextByTag($language, $text) {
        // Regex, um den Text für das angegebene Sprachkürzel zu extrahieren
        $pattern = '/\[\[lang:' . preg_quote($language, '/') . '\]\](.*?)(?=\[\[lang:|$)/is';
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }
        return ''; // Falls keine Übereinstimmung gefunden wird, gib einen leeren String zurück
    }
}



