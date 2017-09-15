<?php

namespace Bavix\Loader;

use Bavix\Helpers\File;
use Bavix\Helpers\Str;

class Tmp
{

    protected $file;

    public function __construct($root, $postfix = '.bx')
    {
        $this->file = $root . Str::random() . $postfix;
        register_shutdown_function([$this, '__destruct']);
    }

    public function __destruct()
    {
        if (File::isFile($this->file))
        {
            File::remove($this->file);
        }
    }

    public function __toString()
    {
        return $this->file;
    }

}
