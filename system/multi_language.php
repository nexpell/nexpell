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
    public function detectLanguages(string $text): array
    {
        preg_match_all('/\[\[lang:([a-z]{2})\]\]/i', $text, $matches);
        return !empty($matches[1]) ? array_unique($matches[1]) : [];
    }

    /**
     * Gibt den Text in der ausgewählten Sprache zurück.
     * 
     * @param string $text Der mehrsprachige Text
     * @return string Der passende Textausschnitt
     */
    public function getTextByLanguage(string $text): string
    {
        $availableLanguages = $this->detectLanguages($text);

        if (empty($availableLanguages)) {
            return $text;
        }

        if (in_array($this->language, $availableLanguages, true)) {
            return $this->getTextByTag($this->language, $text);
        }

        return $this->getTextByTag($availableLanguages[0], $text);
    }
    
    /**
     * Holt den konkreten Textabschnitt einer Sprache
     * 
     * @param string $language Sprachkürzel
     * @param string $text Ursprünglicher Text mit Sprachblöcken
     * @return string Nur der passende Text
     */
    private function getTextByTag(string $language, string $text): string
    {
        $pattern = '/\[\[lang:' . preg_quote($language, '/') . '\]\](.*?)(?=\[\[lang:[a-z]{2}\]\]|$)/is';
        return preg_match($pattern, $text, $m) ? trim($m[1]) : '';
    }

}



