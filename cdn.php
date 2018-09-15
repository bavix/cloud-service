<?php

$root = __DIR__ . '/';

include_once $root . 'vendor/autoload.php';

$storage = $root . 'public/';

$npm = new \Bavix\Loader\NPM($storage);

if (($argv[1] ?? null) === '--upd')
{
    $packages = scandir($storage, null);

    $argv = array_merge([__FILE__], array_slice($packages, 2));
    $argc = count($argv);
}

for ($i = 1; $i < $argc; $i++) {

    try {
        $npm->download($argv[$i]);
    }
    catch (\Bavix\Exceptions\NotFound\Page $page) {
        var_dump($page->getMessage());
    }

}

// cleanup
$root = __DIR__ . '/web';

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root)
);

$files = new RegexIterator($iter, '~\.(zip|jar|php)$~');

foreach ($files as $file) {
    echo $file . PHP_EOL;
    @unlink($file);
}
// end cleeanup
