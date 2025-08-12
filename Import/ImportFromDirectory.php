<?php

namespace MauticPlugin\MauticCustomImportBundle\Import;

use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Model\ImportModel;
use MauticPlugin\MauticCustomImportBundle\Exception\InvalidImportException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\UserBundle\Entity\User;

class ImportFromDirectory
{
    /**
     * @var array
     */
    private $integrationOptions;

    /**
     * @var ImportModel
     */
    private $importModel;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    /** @var EntityManagerInterface */
    private $em;

    /**
     * CreateImportFromDirectory constructor.
     *
     * @param ImportModel $importModel
     */
    public function __construct(
        ImportModel $importModel,
        EntityManagerInterface $em
    ) {
        $this->importModel        = $importModel;
        $this->em = $em;
        $this->em = $em;
    }

    /**
     * @param array $options
     *
     * @return array|\Iterator|SplFileInfo[]
     * @throws InvalidImportException
     */
    public function importFromFiles(array $options)
    {
        $this->integrationOptions = $options;

        $files = $this->loadCsvFilesFromPath($this->integrationOptions['path_to_directory_csv']);
        $importTemplate = $this->importModel->getEntity($this->integrationOptions['template_from_import']);
        if (!$importTemplate) {
            throw new InvalidImportException('Import template entity doesn\'t exists');
        }

        $this->fileSystem         = new Filesystem();
        foreach ($files as $file) {
            $this->importFromFile($file, $importTemplate);
            sleep(1);
        }

        return $files;
    }

    /**
     * @param SplFileInfo $file
     * @param Import      $importTemplate
     */
    private function importFromFile(SplFileInfo $file, Import $importTemplate)
    {
        $importDir          = $this->importModel->getImportDir();
        $fileName    = $this->importModel->getUniqueFileName();
        $newFilePath = $importDir.'/'.$fileName;
        // remove If file already exists
        if (file_exists($newFilePath)) {
            @unlink($newFilePath);
        }
        // move csv file to import directory
        $this->fileSystem->rename($file->getRealPath(), $newFilePath);

        
// Create an import object
$import = new Import();
$properties = $importTemplate->getProperties();

// Parser settings from template
$parserConfig = $importTemplate->getParserConfig() ?? [];
$delimiter = $parserConfig['delimiter'] ?? ',';
$enclosure = $parserConfig['enclosure'] ?? '"';
$escape    = $parserConfig['escape'] ?? '\\';

// Auto-detect delimiter from the first non-empty line and persist override
$__detected = $this->detectCsvDelimiter($newFilePath, [',',';',"	",'|']);
if ($__detected && $__detected !== $delimiter) {
    $delimiter = $__detected;
    $parserConfig['delimiter'] = $__detected;
}


// Read actual CSV headers
$fp = new \SplFileObject($newFilePath);
$fp->setFlags(\SplFileObject::READ_CSV);
$fp->setCsvControl($delimiter, $enclosure, $escape);
$fp->rewind();
$headersFromFile = $fp->fgetcsv();
if (!is_array($headersFromFile)) {
    $headersFromFile = [];
}
if (isset($headersFromFile[0]) && is_string($headersFromFile[0])) {
    $headersFromFile[0] = preg_replace('/^\xEF\xBB\xBF/u', '', $headersFromFile[0]);
}


// Align mapping: template fields -> actual header row (robust normalization)
$templateFields = $properties['fields'] ?? [];
$normalize = static function($v) {
    $v = is_string($v) ? mb_strtolower(trim($v)) : (string) $v;
    $v = preg_replace('/\s+/', '', $v);
    $v = str_replace(['-', '_'], '', $v);
    return $v;
};
$mapNormalizedToOriginal = [];
foreach ((array) $headersFromFile as $h) {
    $mapNormalizedToOriginal[$normalize((string) $h)] = (string) $h;
}
$aligned = [];
foreach ($templateFields as $src => $dst) {
    $key = $normalize((string) $src);
    if (isset($mapNormalizedToOriginal[$key])) {
        $aligned[$mapNormalizedToOriginal[$key]] = $dst;
    }
}
// Persist aligned props
$properties['fields']  = $aligned;
$properties['headers'] = $headersFromFile;
$properties['parser']  = $parserConfig;
if (isset($properties['line'])) {
            unset($properties['line']);
        }
        $import
            ->setProperties($properties)
            ->setDefault('owner', isset($this->integrationOptions['owner_id']) && ctype_digit((string)$this->integrationOptions['owner_id']) ? (int) $this->integrationOptions['owner_id'] : 1)
            ->setHeaders($headersFromFile)
            ->setParserConfig($parserConfig)
            ->setDir($importDir)
            ->setLineCount($this->getLinesCountFromPath($newFilePath))
            ->setFile($fileName)
            ->setOriginalFile($file->getFilename())
            ->setStatus($import::QUEUED);

// Resolve a valid user for context and ownership
$ownerId = (int) ($this->integrationOptions['owner_id'] ?? ($this->integrationOptions['owner'] ?? 0));
if (!$ownerId) { $ownerId = (int) ($properties['defaults']['owner'] ?? 0); }

$userRepo = $this->em->getRepository(User::class);
$user = $ownerId ? $userRepo->find($ownerId) : null;
if (!$user && 0 === (int)($this->integrationOptions['owner_id'] ?? 0)) {
    // Fallback: pick the lowest-ID user
    $user = $userRepo->createQueryBuilder('u')
        ->orderBy('u.id', 'ASC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
if (!$user) {
    throw new InvalidImportException('No Mautic user found. Please create a user or set an Owner ID in the Custom Import integration settings.');
}

// Ensure defaults.owner matches the actual user ID
$properties['defaults']['owner'] = $user->getId();
$import->setProperties($properties);

// Keep defaults.owner aligned with the actually resolved user ID
$properties['defaults']['owner'] = (int) $user->getId();
$import->setProperties($properties);

// Set creator and owner for the Import entity
$import->setCreatedBy($user);
// Resolve selected owner and set as creator to fill created_by and created_by_user
$ownerId = 0;
if (isset($this->integrationOptions['owner_id']) && (string) $this->integrationOptions['owner_id'] !== '') {
    $ownerId = (int) $this->integrationOptions['owner_id'];
} elseif (isset($properties['defaults']['owner'])) {
    $ownerId = (int) $properties['defaults']['owner'];
}

$userRepo = $this->em->getRepository(User::class);
$user = $ownerId ? $userRepo->find($ownerId) : null;
if (!$user) {
    $user = $userRepo->createQueryBuilder('u')->orderBy('u.id', 'ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
}
if (!$user) {
    throw new \RuntimeException('No Mautic user found to assign as Import owner.');
}

// Keep defaults.owner accurate
if (!isset($properties['defaults'])) { $properties['defaults'] = []; }
$properties['defaults']['owner'] = (int) $user->getId();
if (method_exists($import, 'setProperties')) {
    $import->setProperties($properties);
}

if (method_exists($import, 'setCreatedBy')) {
    $import->setCreatedBy($user);
}
if (method_exists($import, 'setCreatedByUser')) {
    $display = trim(($user->getFirstName() ?: '') . ' ' . ($user->getLastName() ?: ''));
    if ($display === '') {
        $display = $user->getUsername() ?: $user->getEmail() ?: ('User #'.$user->getId());
    }
    $import->setCreatedByUser($display);
}
$this->importModel->saveEntity($import);
    
// Force created_by and created_by_user to the configured owner in case listeners override it
try {
    $finalOwnerId = 0;
    if (isset($this->integrationOptions['owner_id']) && (string) $this->integrationOptions['owner_id'] !== '') {
        $finalOwnerId = (int) $this->integrationOptions['owner_id'];
    } elseif (isset($properties['defaults']['owner'])) {
        $finalOwnerId = (int) $properties['defaults']['owner'];
    }
    if ($finalOwnerId > 0) {
        $conn = $this->em->getConnection();
        $creatorDisplay = isset($display) && $display ? $display : '';
        if ($creatorDisplay === '' && isset($user)) {
            $creatorDisplay = trim(($user->getFirstName() ?: '') . ' ' . ($user->getLastName() ?: ''));
            if ($creatorDisplay === '') {
                $creatorDisplay = $user->getUsername() ?: $user->getEmail() ?: ('User #'.$user->getId());
            }
        }
        $conn->update('imports', ['created_by' => $finalOwnerId, 'created_by_user' => $creatorDisplay], ['id' => $import->getId()]);
    }
} catch (\Throwable $e) {
    // Swallow to avoid breaking the import if DBAL update fails
}
}

    /**
     * @param $path
     *
     * @return array|\Iterator|\Symfony\Component\Finder\SplFileInfo[]
     * @throws InvalidImportException
     */
    private function loadCsvFilesFromPath($path)
    {
        $finder = (new Finder())
            ->in($path)
            ->name('*.csv')
            ->getIterator();
        $files  = iterator_to_array($finder);
        if (empty($files)) {
            throw new InvalidImportException(sprintf("Not find any files in  %s directory", $path));
        }

        return $files;
    }

    /**
     * @param $path
     *
     * @return int
     */
    private function getLinesCountFromPath($path)
    {
        $fileData = new \SplFileObject($path);
        $fileData->seek(PHP_INT_MAX);
        return $fileData->key();
    
    }

    /**
     * Detect the most likely CSV delimiter by counting candidate occurrences
     * on the first non-empty, non-BOM line.
     */
    private function detectCsvDelimiter(string $filePath, array $candidates = [',',';', "\t", '|']): ?string
    {
        $fh = new \SplFileObject($filePath, 'r');
        $line = '';
        // Find first non-empty trimmed line
        while (!$fh->eof() && $line === '') {
            $line = (string) $fh->fgets();
            $line = trim($line);
        }
        if ($line === '') {
            return null;
        }
        // Strip UTF-8 BOM if present
        $line = preg_replace('/^\xEF\xBB\xBF/u', '', $line);
        $best = null;
        $bestCount = 0;
        foreach ($candidates as $c) {
            $cnt = substr_count($line, $c);
            if ($cnt > $bestCount) {
                $best = $c;
                $bestCount = $cnt;
            }
        }
        return $bestCount > 0 ? $best : null;
}
}
