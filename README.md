# AMP Magento Library

[![Build Status](https://travis-ci.org/webgriffe/amp-magento.svg?branch=master)](https://travis-ci.org/webgriffe/amp-magento)

Magento REST Api wrapper to use with [Amp](https://amphp.org/) PHP framework.

## Installation

Require this package using [Composer](https://getcomposer.org/):

    composer require webgriffe/amp-magento
     
## Usage

ApiClient is the main class of this library: it contains methods that wrap Magento REST API.

```php
<?php

use Amp\Http\Client\HttpClientBuilder;
use Webgriffe\AmpMagento\ApiClient;

require_once __DIR__.'/vendor/autoload.php';

$client = new ApiClient(
    HttpClientBuilder::buildDefault(),
    [
        'baseUrl' => 'http://magento.base.url',
        'username' => 'magento-username',
        'password' => 'magento-password'
    ]
);

$order = \Amp\Promise\wait($client->getOrder(1));
var_dump($order);

```

## In Memory Magento

The folder InMemoryMagento contains a fake Magento server and client to be used for automated testing.
Unit tests in tests/ApiClientTest.php show how to use InMemoryMagento.

Contributing
------------

To contribute simply fork this repository, do your changes and then propose a pull requests.
You should run coding standards check and tests as well:

```bash
vendor/bin/phpcs --standard=PSR2 src
vendor/bin/phpunit
```

License
-------
This library is under the MIT license. See the complete license in the LICENSE file.

Credits
-------
Developed by [Webgriffe®](http://www.webgriffe.com/)
