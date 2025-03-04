<?php
namespace Flownative\ResourceTools\ResourceManagement;

use Neos\Flow\ResourceManagement\CollectionInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\Target\TargetInterface;

/**
 * A resource target that actually does nothing. Any resource
 * in a collection with this target will not be publicly
 * available.
 * Getting the public uri will return an empty string.
 */
class DummyTarget implements TargetInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * Constructor for the DummyTarget
     *
     * @param $name
     * @param array $options
     */
    public function __construct($name, $options)
    {
        $this->name = $name;
    }

    /**
     * Get name of this target
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param CollectionInterface $collection
     */
    public function publishCollection(CollectionInterface $collection)
    {
        return;
    }

    /**
     * @param PersistentResource $resource
     * @param CollectionInterface $collection
     * @return void
     */
    public function publishResource(PersistentResource $resource, CollectionInterface $collection)
    {
        return;
    }

    /**
     * @param PersistentResource $resource
     * @return void
     */
    public function unpublishResource(PersistentResource $resource)
    {
        return;
    }

    /**
     * @param string $relativePathAndFilename
     * @return string
     */
    public function getPublicStaticResourceUri($relativePathAndFilename)
    {
        return '';
    }

    /**
     * @param PersistentResource $resource
     * @return string
     */
    public function getPublicPersistentResourceUri(PersistentResource $resource)
    {
        return '';
    }

    public function onPublish(\Closure $callback): void
    {
    }
}
