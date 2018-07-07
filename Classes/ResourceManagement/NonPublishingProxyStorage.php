<?php
namespace Flownative\ResourceTools\ResourceManagement;

use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Storage\WritableStorageInterface;

/**
 * Resources imported to this storage will not automatically
 * be published. You need to take care of publishing in your
 * application code!
 * Note that it won't be easy to use this as a default
 * storage for Neos CMS, as there isn't an easy way to
 * trigger publication of resources.
 */
class NonPublishingProxyStorage implements WritableStorageInterface
{
    /**
     * @var WritableStorageInterface
     */
    protected $storage;

    /**
     * Build the proxy storage.
     *
     * @param string $name Name of this storage instance, according to the resource settings
     * @param array $options Options for this storage
     * @throws \Exception
     */
    public function __construct($name, array $options = [])
    {
        if (!isset($options['storageClass'])) {
            throw new \Exception('This storage needs the option "storageClass" to be set to a valid WritableStorageInterface implementation class.', 1530974316);
        }

        if (!isset($options['storageOptions'])) {
            throw new \Exception('This storage needs the option "storageOptions" to be set to an array of storage options.', 1530974316);
        }

        $this->storage = new $options['storageClass']($name, $options['storageOptions']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->storage->getName();
    }

    /**
     * @param PersistentResource $resource
     * @return bool|resource
     */
    public function getStreamByResource(PersistentResource $resource)
    {
        return $this->storage->getStreamByResource($resource);
    }

    /**
     * @param string $relativePath
     * @return bool|resource
     */
    public function getStreamByResourcePath($relativePath)
    {
        return $this->storage->getStreamByResourcePath($relativePath);
    }

    /**
     * @return \Generator|void
     */
    public function getObjects()
    {
        yield $this->storage->getObjects();
    }

    /**
     * @param CollectionInterface $collection
     * @return \Generator
     */
    public function getObjectsByCollection(CollectionInterface $collection)
    {
        yield $this->storage->getObjectsByCollection($collection);
    }

    /**
     * @param resource|string $source
     * @param string $collectionName
     * @return PersistentResource
     * @throws \Neos\Flow\ResourceManagement\Storage\Exception
     */
    public function importResource($source, $collectionName)
    {
        $resource = $this->storage->importResource($source, $collectionName);
        $resource->disableLifecycleEvents();
        return $resource;
    }

    /**
     * @param string $content
     * @param string $collectionName
     * @return PersistentResource
     * @throws \Neos\Flow\ResourceManagement\Storage\Exception
     */
    public function importResourceFromContent($content, $collectionName)
    {
        $resource = $this->storage->importResourceFromContent($collectionName, $collectionName);
        $resource->disableLifecycleEvents();
        return $resource;
    }

    /**
     * @param PersistentResource $resource
     * @return bool
     */
    public function deleteResource(PersistentResource $resource)
    {
        return $this->storage->deleteResource($resource);
    }

}
