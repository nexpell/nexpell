<?php
declare(strict_types=1);

namespace nexpell;

/**
 * Lightweight fallback updater to keep update_core_real.php compatible
 * when no dedicated CMSUpdater implementation is bundled.
 */
class CMSUpdater
{
    private bool $dryRun;

    public function __construct(bool $dryRun = true)
    {
        $this->dryRun = $dryRun;
    }

    public function runUpdates(): string
    {
        $mode = $this->dryRun ? 'Dry-Run' : 'Live';
        return "<div class='text-muted small'>CMSUpdater-Fallback aktiv ({$mode}).</div>";
    }
}
