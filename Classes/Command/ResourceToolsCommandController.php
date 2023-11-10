<?php

namespace Flownative\ResourceTools\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\ResourceManagement\Exception;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Strategy\AssetUsageStrategyInterface;
use Neos\Utility\Exception\FilesException;
use Neos\Utility\Files;

final class ResourceToolsCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * Export resources to path
     *
     * This command exports all resource data found in a given resource collection into a specified path.
     *
     * @param string $targetPath The path where the resource files should be exported to.
     * @param bool $emptyTargetPath If set, all files in the target path will be deleted before the export runs
     * @param string $collection Name of the resource collection to export. Default: "persistent"
     * @throws FilesException
     */
    public function exportCommand(string $targetPath, bool $emptyTargetPath = false, string $collection = 'persistent'): void
    {
        if (!is_dir($targetPath)) {
            $this->outputLine('The target path does not exist.');
            $this->quit(1);
        }

        $collectionInstance = $this->resourceManager->getCollection($collection);
        if ($collectionInstance === null) {
            $this->outputLine('<error>Collection %s not found</error>', [$collection]);
            $this->quit(1);
        }

        $targetPath = realpath($targetPath) . '/';

        if ($emptyTargetPath) {
            $files = Files::readDirectoryRecursively($targetPath);

            if (count($files) && $this->output->askConfirmation(sprintf('Are you sure you want to delete %s files in %s ?', count($files), $targetPath), false)) {
                $this->outputLine('<b>Removing all files in %s ...</b>', [$targetPath]);
                Files::emptyDirectoryRecursively($targetPath);
            }
        }

        $this->outputLine('<b>Exporting resources to %s ...</b>', [$targetPath]);

        foreach ($collectionInstance->getObjects() as $object) {
            $stream = $object->getStream();
            if ($stream === false) {
                $this->outputLine('<error>missing</error>  %s %s', [$object->getSha1(), $object->getFilename()]);
            } else {
                file_put_contents($targetPath . $object->getSha1(), $stream);
                $this->outputLine('<success>exported</success> %s %s', [$object->getSha1(), $object->getFilename()]);
            }
        }
    }

    /**
     * Match files with resources
     *
     * This command iterates over the known resource objects (ie. the resource object metadata stored in the database)
     * and if a corresponding file exists in the given source path, the file is imported into the configured resource
     * storage.
     *
     * The source path must contain files which use the content SHA1 as their filename (without a file extension).
     *
     * @param string $sourcePath The path where the resource files are located
     * @param string $collection Name of the resource collection to match with. Default: "persistent"
     */
    public function matchCommand(string $sourcePath, string $collection = 'persistent'): void
    {
        if (!is_dir($sourcePath)) {
            $this->outputLine('<error>The source path does not exist.</error>');
            $this->quit(1);
        }

        $collectionInstance = $this->resourceManager->getCollection($collection);
        if ($collectionInstance === null) {
            $this->outputLine('<error>Collection %s not found</error>', [$collection]);
            $this->quit(1);
        }

        $sourcePath = rtrim($sourcePath, '/') . '/';

        $this->outputLine('<success>Matching resources with files in %s ...</success>' . chr(10), [$sourcePath]);

        foreach ($collectionInstance->getObjects() as $object) {
            $stream = $object->getStream();
            if ($stream === false) {
                if (file_exists($sourcePath . $object->getSha1())) {
                    sha1_file($sourcePath . $object->getSha1());
                    try {
                        $collectionInstance->importResource(fopen($sourcePath . $object->getSha1(), 'r'));
                        $this->outputLine('<success>imported</success>  %s %s', [$object->getSha1(), $object->getFilename()]);
                    } catch (Exception $e) {
                        $this->outputLine('<error>failed</error>  %s %s %s', [$object->getSha1(), $object->getFilename(), $e->getMessage()]);
                    }
                } else {
                    $this->outputLine('<error>missing </error>  %s %s', [$object->getSha1(), $object->getFilename()]);
                }
            } else {
                $this->outputLine('exists    %s %s', [$object->getSha1(), $object->getFilename()]);
            }
        }
    }

    /**
     * Import the given file as resource in Neos.
     *
     * @param string $source Can be anything PHP can open (filepath, URL, data string, etc.)
     * @param string $filename The filename (if empty string it will try to fetch from stream or leave empty)
     * @param string $collection Name of the resource collection to import to. Default: "persistent"
     */
    public function importFileCommand(string $source, string $filename, string $collection = 'persistent'): void
    {
        $fileHandler = fopen($source, 'rb');
        try {
            $resource = $this->resourceManager->importResource($fileHandler, $collection);
            if ($filename !== '') {
                $resource->setFilename($filename);
            }

            $this->outputLine('Imported file as resource "%s"', [$resource->getSha1()]);
        } catch (Exception $e) {
            $this->outputLine('<error>Failed importing file</error>  %s', [$e->getMessage()]);
            $this->quit(1);
        }
    }

    /**
     * Delete orphaned blobs (files) in Data/Persistent/Resources.
     *
     * Orphans are blobs not attached to a persistent resources record in the database.
     *
     * @param bool $dryRun Disable to really delete blobs
     * @param int $minimumAge Ignore files younger than x seconds. Default: 3600
     * @return void
     * @throws FilesException
     * @todo Enhance to work on arbitrary storages
     */
    public function removeOrphanedBlobsCommand(bool $dryRun = true, int $minimumAge = 3600): void
    {
        $basePath = FLOW_PATH_DATA . 'Persistent/Resources/';
        foreach (Files::getRecursiveDirectoryGenerator($basePath) as $file) {
            if (
                is_file($file) &&
                $this->isFileOldEnough($file, $minimumAge) &&
                // assume the filename is the SHA1
                $this->isFileNotAttachedToAResourceInDatabase(basename($file))
            ) {

                if ($dryRun === false) {
                    if (Files::unlink($file)) {
                        $this->outputLine('<success>deleted</success> %s', [str_replace(FLOW_PATH_ROOT, '', $file)]);
                    } else {
                        $this->outputLine('<error>failed</error>  %s', [str_replace(FLOW_PATH_ROOT, '', $file)]);
                    }
                    Files::removeEmptyDirectoriesOnPath(dirname($file), $basePath);
                }

            }
        }
    }

    /**
     * Delete unused assets, their resources records, and their blobs.
     *
     * Assets are considered unused if they are not having any use recorded
     * by any of the installed AssetUsageStrategyInterface implementations.
     *
     * @param bool $dryRun Disable to really delete resources and blobs
     */
    public function removeUnusedAssetsCommand(bool $dryRun = true): void
    {
        $numberOfDeletedAssets = 0;
        $numberOfKeptAssets = 0;
        $iterator = $this->assetRepository->findAllIterator();
        /** @var AssetInterface $asset */
        foreach ($this->assetRepository->iterate($iterator) as $asset) {
            $assetIdentifier = $this->persistenceManager->getIdentifierByObject($asset);
            if ($this->isAssetInUse($asset) === false) {
                $numberOfDeletedAssets++;
                if ($dryRun === false) {
                    $this->assetRepository->remove($asset);
                }
                $this->outputLine(
                    '<error>deleted</error> %s %s',
                    [
                        $assetIdentifier,
                        $asset->getTitle() ?: $asset->getResource()->getFilename()
                    ]
                );
            } else {
                $numberOfKeptAssets++;
                $this->outputLine(
                    '<success>kept</success>    %s %s',
                    [
                        $assetIdentifier,
                        $asset->getTitle() ?: $asset->getResource()->getFilename()
                    ]
                );
            }
        }

        $this->outputLine();
        $this->outputLine('<b>Summary</b>: %u assets <error>deleted</error> and %u assets <success>kept</success>', [$numberOfDeletedAssets, $numberOfKeptAssets]);
    }

    private function isAssetInUse(AssetInterface $asset): bool
    {
        $strategies = [];
        foreach ($this->reflectionService->getAllImplementationClassNamesForInterface(AssetUsageStrategyInterface::class) as $usageStrategyImplementationClassName) {
            $strategies[] = $this->objectManager->get($usageStrategyImplementationClassName);
        }

        $isInUse = true;
        /** @var AssetUsageStrategyInterface $strategy */
        foreach ($strategies as $strategy) {
            $isInUse = $isInUse && $strategy->isInUse($asset);
        }

        return $isInUse;
    }

    private function isFileOldEnough(string $file, int $minimumAge): bool
    {
        return filemtime($file) < time() - $minimumAge;
    }

    private function isFileNotAttachedToAResourceInDatabase(string $sha1): bool
    {
        return $this->resourceManager->getResourceBySha1($sha1) === null;
    }
}
