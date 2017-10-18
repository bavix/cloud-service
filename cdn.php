<?php

$root = __DIR__ . '/';

include_once $root . 'vendor/autoload.php';

$storage = $root . 'web/';

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

shell_exec('find web/ -type f -name "*.zip" -print0 | while IFS= read -r -d $\'\0\' line; do rm "$line"; done');
