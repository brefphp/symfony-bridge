<?php

/**
 * Fake front controller (replacing index.php) that will simulate a request to the Kernel
 * using Symfony's HttpKernelBrowser. This allow to go through the same process
 * as a classic HTTP request without the burden of building such a request ourselves.
 */

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

require dirname(__DIR__).'/config/bootstrap.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

$browser = new HttpKernelBrowser($kernel);

$crawler = $browser->request('GET', '/');
