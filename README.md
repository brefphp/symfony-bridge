This package configures Symfony to run on AWS Lambda using [Bref](https://bref.sh/).

[![Build Status](https://img.shields.io/travis/com/bref/symfony-bridge/master.svg?style=flat-square)](https://travis-ci.com/bref/symfony-bridge)
[![Latest Version](https://img.shields.io/github/release/bref/symfony-bridge.svg?style=flat-square)](https://packagist.org/packages/PHP-DI/php-di)
[![Total Downloads](https://img.shields.io/packagist/dt/bref/symfony-bridge.svg?style=flat-square)](https://packagist.org/packages/PHP-DI/php-di)

## Installation

```cli
composer req bref/symfony-bridge
```

## Usage

Update your Kernel.php

```diff
- class Kernel extends BaseKernel
+ class Kernel extends BrefKernel
```
