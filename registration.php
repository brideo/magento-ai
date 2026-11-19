<?php
/**
 * UpturnStudio_Ai
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'UpturnStudio_Ai', __DIR__);

// app/code modules are not autoloaded through their own composer.json, so the helper functions load here.
// phpcs:ignore Magento2.Security.IncludeFile.FoundIncludeFile
require_once __DIR__ . '/functions.php';
