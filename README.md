Charcoal DatabaseMigrator
===============

[![License][badge-license]][charcoal-contrib-database-migrator]
[![Latest Stable Version][badge-version]][charcoal-contrib-database-migrator]
[![Code Quality][badge-scrutinizer]][dev-scrutinizer]
[![Coverage Status][badge-coveralls]][dev-coveralls]
[![Build Status][badge-travis]][dev-travis]

A [Charcoal][charcoal-app] service provider my cool feature.



## Table of Contents

-   [Installation](#installation)
    -   [Dependencies](#dependencies)
-   [Configuration](#configuration)
-   [Usage](#usage)
    - [Running the migration](#running-the-migration)
    - [Creating new Patches](#creating-new-patches)
    - [Injecting Patches to the migrator](#injecting-patches-to-the-migrator)
-   [Development](#development)
    -  [API Documentation](#api-documentation)
    -  [Development Dependencies](#development-dependencies)
    -  [Coding Style](#coding-style)
-   [Credits](#credits)
-   [License](#license)



## Installation

The preferred (and only supported) method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-database-migrator
```



### Dependencies

#### Required

-   [**PHP 7.2+**](https://php.net): _PHP 7.3+_ is recommended.



#### PSR

-   [**PSR-7**][psr-7]: Common interface for HTTP messages. Fulfilled by Slim.
-   [**PSR-11**][psr-11]: Common interface for dependency containers. Fulfilled by Pimple.



## Configuration

In your project's config file, require the migrator module like so : 
```json
{
    "modules": {
        "charcoal/migrator/migrator": {}
    }
}
```


## Usage

### Running the migration

Simply run this command in the console to lunch the migration process
```shell
$ vendor/bin/charcoal admin/patch/database
```

The CLI UI will guide you step by step.


### Creating new Patches

A patch should always extend [**AbstractPatch**](src/Charcoal/DatabaseMigrator/AbstractPatch.php)

[**GenericPatch**](src/Charcoal/Patch/DatabaseMigrator/GenericPatch.php) can be used as a starting point when creating new patches.
Just copy and paste it the package in need of a new patch.

A patch should always be named ``PatchYYYYMMDDHHMMSS.php`` to facilitate readability.

A patch file consist of a PHP class which follows these guidelines : 

- Be namespaced ``Charcoal\\Patch\\..``
- Have a ``DB_VERSION`` const which equals the timestamp of the commit this patch is fixing
- Have an ``up`` and ``down`` method for the migration tool to process the migration when going up in version or down.
- Have a ``descripion`` and ``author`` method to briefly document the patch

you can implement the setDependencies method on a patch.

```PHP
     /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A Pimple DI service container.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        // This method is a stub.
        // Reimplement in children method to inject dependencies in your class from a Pimple container.
    }
```

### Injecting Patches to the migrator

As long as the patch follows the guidelines described above, it'll be automatically parsed by the migrator.
No need to do anything more than that.


## Development

To install the development environment:

```shell
$ composer install
```

To run the scripts (phplint, phpcs, and phpunit):

```shell
$ composer test
```



### API Documentation

-   The auto-generated `phpDocumentor` API documentation is available at:  
    [https://locomotivemtl.github.io/charcoal-contrib-database-migrator/docs/master/](https://locomotivemtl.github.io/charcoal-contrib-database-migrator/docs/master/)
-   The auto-generated `apigen` API documentation is available at:  
    [https://codedoc.pub/locomotivemtl/charcoal-contrib-database-migrator/master/](https://codedoc.pub/locomotivemtl/charcoal-contrib-database-migrator/master/index.html)



### Development Dependencies

-   [php-coveralls/php-coveralls][phpcov]
-   [phpunit/phpunit][phpunit]
-   [squizlabs/php_codesniffer][phpcs]



### Coding Style

The charcoal-contrib-database-migrator module follows the Charcoal coding-style:

-   [_PSR-1_][psr-1]
-   [_PSR-2_][psr-2]
-   [_PSR-4_][psr-4], autoloading is therefore provided by _Composer_.
-   [_phpDocumentor_](http://phpdoc.org/) comments.
-   [phpcs.xml.dist](phpcs.xml.dist) and [.editorconfig](.editorconfig) for coding standards.

> Coding style validation / enforcement can be performed with `composer phpcs`. An auto-fixer is also available with `composer phpcbf`.



## Credits

-   [Locomotive](https://locomotive.ca/)



## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.



[charcoal-contrib-database-migrator]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-database-migrator
[charcoal-app]:             https://packagist.org/packages/locomotivemtl/charcoal-app

[dev-scrutinizer]:    https://scrutinizer-ci.com/g/locomotivemtl/charcoal-contrib-database-migrator/
[dev-coveralls]:      https://coveralls.io/r/locomotivemtl/charcoal-contrib-database-migrator
[dev-travis]:         https://travis-ci.org/locomotivemtl/charcoal-contrib-database-migrator

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-database-migrator.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-database-migrator.svg?style=flat-square
[badge-scrutinizer]:  https://img.shields.io/scrutinizer/g/locomotivemtl/charcoal-contrib-database-migrator.svg?style=flat-square
[badge-coveralls]:    https://img.shields.io/coveralls/locomotivemtl/charcoal-contrib-database-migrator.svg?style=flat-square
[badge-travis]:       https://img.shields.io/travis/locomotivemtl/charcoal-contrib-database-migrator.svg?style=flat-square

[psr-1]:  https://www.php-fig.org/psr/psr-1/
[psr-2]:  https://www.php-fig.org/psr/psr-2/
[psr-3]:  https://www.php-fig.org/psr/psr-3/
[psr-4]:  https://www.php-fig.org/psr/psr-4/
[psr-6]:  https://www.php-fig.org/psr/psr-6/
[psr-7]:  https://www.php-fig.org/psr/psr-7/
[psr-11]: https://www.php-fig.org/psr/psr-11/
[psr-12]: https://www.php-fig.org/psr/psr-12/
