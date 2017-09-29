<?php

namespace Flownative\ResourceTools\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\Storage\StorageObject;
use Neos\Utility\Files;

final class ResourceToolsCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Export resources to path
     *
     * This command exports all resource data found in a given resource collection into a specified path.
     *
     * @param string $targetPath The path where the resource files should be exported to.
     * @param bool $emptyTargetPath If set, all files in the target path will be deleted before the export runs
     * @param string $collection Name of the resource collection to export. Default: "persistent"
     */
    public function exportCommand(string $targetPath, bool $emptyTargetPath = false, $collection = 'persistent')
    {
        if (!is_dir($targetPath)) {
            $this->outputLine('The target path does not exist.');
            $this->quit(1);
        }
        $targetPath = realpath($targetPath) . '/';

        if ($emptyTargetPath) {
            $files = Files::readDirectoryRecursively($targetPath);

            if (count($files) && $this->output->askConfirmation(sprintf('Are you sure you want to delete %s files in %s ?', count($files), $targetPath), false)) {
                $this->outputLine('<b>Removing all files in %s ...</b>' . chr(10), [$targetPath]);
                Files::emptyDirectoryRecursively($targetPath);
            }
        }

        $this->outputLine('<b>Exporting resources to %s ...</b>' . chr(10), [$targetPath]);

        $persistentCollection = $this->resourceManager->getCollection($collection);
        foreach ($persistentCollection->getObjects() as $object) {
            /** @var StorageObject $object */
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
    public function matchCommand(string $sourcePath, string $collection = 'persistent')
    {
        if (!is_dir($sourcePath)) {
            $this->outputLine('The source path does not exist.');
            $this->quit(1);
        }

        $sourcePath = rtrim($sourcePath,'/') . '/';

        $this->outputLine('<success>Matching resources with files in %s ...</success>' . chr(10), [$sourcePath]);

        $persistentCollection = $this->resourceManager->getCollection($collection);
        foreach ($persistentCollection->getObjects() as $object) {
            /** @var StorageObject $object */

            $stream = $object->getStream();
            if ($stream === false) {
                if (file_exists($sourcePath . $object->getSha1())) {
                    sha1_file($sourcePath . $object->getSha1());
                    $persistentCollection->importResource(fopen($sourcePath . $object->getSha1(), 'r'));
                    $this->outputLine('<success>imported</success>  %s %s', [$object->getSha1(), $object->getFilename()]);
                } else {
                    $this->outputLine('<error>missing </error>  %s %s', [$object->getSha1(), $object->getFilename()]);
                }
            } else {
                $this->outputLine('exists    %s %s', [$object->getSha1(), $object->getFilename()]);
            }
        }
    }
}
