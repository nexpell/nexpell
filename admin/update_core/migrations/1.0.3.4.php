<?php
declare(strict_types=1);

return static function ($database = null): array {
    $db = $database instanceof mysqli ? $database : ($GLOBALS['_database'] ?? null);

    if (!$db instanceof mysqli) {
        throw new RuntimeException('Database connection not available for migration 1.0.3.4');
    }

    return [
        'success' => true,
        'notes' => [
            'Core bugfix update 1.0.3.4 applied.',
            'No database schema changes required.',
        ],
    ];
};
