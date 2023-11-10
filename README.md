[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Packagist](https://img.shields.io/packagist/v/flownative/resource-tools.svg)](https://packagist.org/packages/flownative/resource-tools)
[![Maintenance level: Love](https://img.shields.io/badge/maintenance-%E2%99%A1%E2%99%A1%E2%99%A1-ff69b4.svg)](https://www.flownative.com/en/products/open-source.html)

# Resource Import and Export for Flow Framework and Neos CMS

Flownative Resource Tools is a package which provides

- a simple command line tool which allows for exporting and importing of Flow resources,
  independently from the resource storage being used
- multiple resource targets and storages for special purposes

## Installation

The Flownative Resource Tools package is installed as a regular Flow package
via Composer. For your existing project, simply include `flownative/resource-tools
into the dependencies of your Flow or Neos distribution:

```bash
    $ composer require flownative/resource-tools
```

## Usage

See `./flow help` 

# Resource Targets and Storages

#### NonPublishingProxyStorage

A special wrapper for another storage that prevents automatic publishing
of any resources imported to this storage. Note that you need to take 
care of publishing resources in your application, they will not be
automatically published if you ask for the public URI. So never use
this as default storage, for example for NeosCMS as it will not be able
to work with unpublished resources.
Note that running `./flow resource:publish` will publish resources added
to a collection with this storage so you should avoid that.

Configuration options:

* `storageClass` - Sets the class name for the actual storage that will store resources.

* `storageOptions` - Configures the options for the storage class that actually stores resources.

Example configuration:
```yaml
Neos:
  Flow:
    resource:
      storages:
        specialNonPublishedStorage:
          storage: 'Flownative\ResourceTools\ResourceManagement\NonPublishingProxyStorage'
          storageOptions:
            storageClass: 'Neos\Flow\ResourceManagement\Storage\WritableFileSystemStorage'
            storageOptions:
              path: '%FLOW_PATH_DATA%Persistent/Resources/'

``` 

#### SaltedFileSystemSymlinkTarget

This target works just like the Flow `\Neos\Flow\ResourceManagement\Target\FileSystemSymlinkTarget`
but it generates a salted hash that cannot be guessed by knowing the file.
This is useful if you let anonymous users upload resources to your system but don't want them
to be able to guess the public URI for security reasons.

Configuration options:

All the options of the Flow FileSystemSymlinkTarget

* `salt` - optional (will fallback to Flow system encryption key) - the salt to hash URIs with.
If you run multiple servers make sure to set this to the same string on every server instead of
relying on the encryption key. Ideally set this to a long randomly generated string.

Example configuration:
```yaml
Neos:
  Flow:
    resource:
      targets:
        localWebDirectoryPersistentResourcesTarget:
          target: 'Flownative\ResourceTools\ResourceManagement\SaltedFileSystemSymlinkTarget'
          targetOptions:
            subdivideHashPathSegment: true
            # Optional salt
            salt: 'foobar'
``` 

#### DummyTarget

This target prevents any publication by simply doing nothing. For all intends and purposes
it looks to Flow like a regular target, but it will not actually make resources public
and requesting a URI for one will always return an empty string. There are no configuration
options:

Example configuration:
```yaml
Neos:
  Flow:
    resource:
      targets:
        dummyTarget:
          target: '\Flownative\ResourceTools\ResourceManagement\DummyTarget'
          targetOptions: []
``` 

## Credits

- This has been partly developed based on things our customers needed, so they paid some of it.
- Inspiration also taken from https://github.com/networkteam/Networkteam.OrphanedResources
