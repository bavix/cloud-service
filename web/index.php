<?php

$root = dirname(__DIR__) . '/';

include_once $root . 'vendor/autoload.php';

$storage = $root . 'storage/';

$npm = new \Bavix\Loader\NPM($storage);

for ($i = 1; $i < $argc; $i++) {

    try {
        $npm->download($argv[$i]);
    }
    catch (\Bavix\Exceptions\NotFound\Page $page) {
        var_dump($page->getMessage());
    }

}
