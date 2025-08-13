<?php

namespace MauticPlugin\MauticCustomImportBundle\Import;

use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Model\ImportModel;
use Symfony\Component\Process\Process;

class ParallelImport
{
    /**
     * @var ImportModel
     */
    private $importModel;

    /**
     * @var PathsHelper
     */
    private $pathsHelper;

    /**
     * ParallelImport constructor.
     *
     * @param ImportModel $importModel
     * @param PathsHelper $pathsHelper
     */
    public function __construct(ImportModel $importModel, PathsHelper $pathsHelper)
    {
        $this->importModel = $importModel;
        $this->pathsHelper = $pathsHelper;
    }


    public function parallelImport(array $options)
    {
        $parallelLimit = $this->importModel->getParallelImportLimit();
        $processSet    = [];
        for ($i = 0; $i < $parallelLimit; $i++) {
            if (!$this->importModel->getImportToProcess() || !$this->importModel->checkParallelImportLimit()) {
                continue;
            }
            

// Determine PHP CLI executable in both default and php-fpm environments
$php = null;
try {
    // Prefer Symfony's PhpExecutableFinder (handles php, phpdbg, versioned binaries)
    $phpFinder = new PhpExecutableFinder();
    $php = $phpFinder->find(false);
} catch (\Throwable $e) {
    $php = null;
}

// If PhpExecutableFinder didn't find anything, try configured PathsHelper key
if (!$php) {
    try {
        $php = $this->pathsHelper->getSystemPath('php');
    } catch (\Throwable $e) {
        $php = null;
    }
}

// If PHP_BINARY exists and is a CLI (not php-fpm), use it
if (!$php && defined('PHP_BINARY') && @is_executable(PHP_BINARY) && stripos(basename(PHP_BINARY), 'php-fpm') === false) {
    $php = PHP_BINARY;
}

// Allow env overrides
if (!$php) {
    $envPhp = getenv('MAUTIC_PHP_BIN') ?: getenv('PHP_CLI_BINARY');
    if ($envPhp && @is_executable($envPhp)) {
        $php = $envPhp;
    }
}

// Common fallbacks
if (!$php) {
    foreach (['/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php8.3', '/usr/bin/php8.2', '/usr/bin/php8.1'] as $bin) {
        if (@is_executable($bin)) { $php = $bin; break; }
    }
}

// Last resort: rely on PATH
if (!$php) { $php = 'php'; }

// Resolve console path using Mautic root; fall back to app/console for older installs
$rootReal = null;
try {
    $rootReal = rtrim($this->pathsHelper->getSystemPath('root', true), '/');
} catch (\Throwable $e) {
    $rootReal = getcwd();
}

$console = $rootReal.'/bin/console';
if (!file_exists($console)) {
    $alt = $rootReal.'/app/console';
    if (file_exists($alt)) { $console = $alt; }
}

// Build command
$cmd = [$php, $console, 'mautic:import', '--limit='.$options['limit']];
if (defined('MAUTIC_ENV')) {
    $cmd[] = '--env='.MAUTIC_ENV;
}

// Working dir: use root if available
$workdir = $rootReal ?: getcwd();

$process = new Process($cmd, $workdir);
$process->setTimeout(9999);
$process->start();
$processSet[] = $process;
sleep(5);
        }

        return $processSet;

    }
}
