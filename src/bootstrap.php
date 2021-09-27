<?php declare(strict_types=1);

use Bref\Bref;
use Bref\SymfonyBridge\HandlerResolver;

/**
 * File executed when the application starts: it registers a Bref PSR-11 "handler resolver".
 *
 * This is what Bref will use to turn handler names (strings defined in serverless.yml/AWS Lambda)
 * into classes that can handle the Lambda events.
 */
Bref::setContainer(fn () => new HandlerResolver);
