<?php
namespace nexpell;

class LanguageManager
{
    protected $_database;

    public function __construct($database)
    {
        $this->_database = $database;
    }

    protected function safe_query(string $sql): bool
    {
        $result = $this->_database->query($sql);
        if (!$result) {
            error_log("SQL Fehler: " . $this->_database->error);
            return false;
        }
        return true;
    }

    public function insertLanguage(array $data): bool
    {
        $iso_639_1   = $this->_database->real_escape_string($data['iso_639_1']);
        $iso_639_2   = isset($data['iso_639_2']) ? $this->_database->real_escape_string($data['iso_639_2']) : '';
        $name_en     = $this->_database->real_escape_string($data['name_en']);
        $name_native = isset($data['name_native']) ? $this->_database->real_escape_string($data['name_native']) : '';
        $name_de     = isset($data['name_de']) ? $this->_database->real_escape_string($data['name_de']) : '';
        $active      = isset($data['active']) ? (int)$data['active'] : 1;

        $sql = "
            INSERT INTO settings_languages 
            (iso_639_1, iso_639_2, name_en, name_native, name_de, active) VALUES
            ('$iso_639_1', '$iso_639_2', '$name_en', '$name_native', '$name_de', $active)
        ";

        return $this->safe_query($sql);
    }

    public function updateLanguage(int $id, array $data): bool
    {
        $id = (int)$id;
        $iso_639_1   = $this->_database->real_escape_string($data['iso_639_1']);
        $iso_639_2   = isset($data['iso_639_2']) ? $this->_database->real_escape_string($data['iso_639_2']) : '';
        $name_en     = $this->_database->real_escape_string($data['name_en']);
        $name_native = isset($data['name_native']) ? $this->_database->real_escape_string($data['name_native']) : '';
        $name_de     = isset($data['name_de']) ? $this->_database->real_escape_string($data['name_de']) : '';
        $active      = isset($data['active']) ? (int)$data['active'] : 1;

        $sql = "
            UPDATE settings_languages SET
            iso_639_1 = '$iso_639_1',
            iso_639_2 = '$iso_639_2',
            name_en = '$name_en',
            name_native = '$name_native',
            name_de = '$name_de',
            active = $active
            WHERE id = $id
        ";

        return $this->safe_query($sql);
    }

    public function deleteLanguage(int $id): bool
    {
        return $this->safe_query("DELETE FROM settings_languages WHERE id = $id");
    }

    public function toggleLanguageStatus(int $id, bool $active): bool
    {
        $active = $active ? 1 : 0;
        return $this->safe_query("UPDATE settings_languages SET active = $active WHERE id = $id");
    }

    public function getLanguage(int $id): ?array
    {
        $result = $this->_database->query("SELECT * FROM settings_languages WHERE id = $id LIMIT 1");
        return $result ? $result->fetch_assoc() : null;
    }

    public function getLanguageByIso(string $iso): ?array
    {
        $iso = $this->_database->real_escape_string($iso);
        $result = $this->_database->query("SELECT * FROM settings_languages WHERE iso_639_1 = '$iso' LIMIT 1");
        return $result ? $result->fetch_assoc() : null;
    }

    public function getAllLanguages(): array
    {
        $result = $this->_database->query("SELECT * FROM settings_languages ORDER BY active DESC, name_en ASC");
        $languages = [];
        while ($row = $result->fetch_assoc()) {
            $languages[] = $row;
        }
        return $languages;
    }
}

 