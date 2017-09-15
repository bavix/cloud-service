<?php

namespace Bavix\Loader;

use Alchemy\Zippy\Zippy;
use Bavix\Exceptions\Blank;
use Bavix\Exceptions\NotFound\Page;
use Bavix\Exceptions\Runtime;
use Bavix\Helpers\Arr;
use Bavix\Helpers\Dir;
use Bavix\Helpers\File;
use Bavix\Helpers\JSON;
use Bavix\Helpers\Stream;

class NPM
{

    /**
     * @var string
     */
    protected $api = 'https://registry.npmjs.org/';

    /**
     * @var string
     */
    protected $storage;

    /**
     * @var string
     */
    protected $tgzStorage;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * NPM constructor.
     *
     * @param $storage
     */
    public function __construct($storage)
    {
        $this->storage    = \rtrim($storage, '\\/') . '/';
        $this->tgzStorage = dirname($this->storage) . '/tgz/';
    }

    /**
     * @param string $name
     *
     * @return string
     *
     * @throws Runtime
     */
    protected function api($name)
    {
        if (preg_match('~[^\w-_.@]~', $name))
        {
            throw new Runtime('Undefined library name `' . $name . '`');
        }

        return $this->api . $name;
    }

    /**
     * @param $url
     *
     * @return string
     *
     * @throws Page
     */
    protected function fetch($url)
    {
        $handle = curl_init();

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_POST, false);
        curl_setopt($handle, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 60);

        $response = curl_exec($handle);
        $length   = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $body     = substr($response, $length);

        if ($httpCode > 199 && $httpCode < 300)
        {
            return $body;
        }

        throw new Page($url);
    }

    /**
     * @param string $name
     *
     * @return array
     *
     * @throws Runtime
     * @throws Page
     */
    protected function load($name)
    {
        if (!isset($this->cache[$name]))
        {
            $data = $this->fetch(
            // load json data
                $this->api($name)
            );

            $this->cache[$name] = JSON::decode($data);
        }

        return $this->cache[$name];
    }

    /**
     * @param string $name
     * @param string $path
     * @param mixed  $default
     *
     * @return mixed
     *
     * @throws Runtime
     */
    protected function store($name, $path, $default = null)
    {
        return Arr::get($this->load($name), $path, $default);
    }

    /**
     * @return array
     *
     * @throws Runtime
     */
    protected function versions($name)
    {
        /**
         * @var $mixed array
         */
        return $this->store($name, 'versions', []);
    }

    /**
     * @param array $data
     *
     * @return string
     *
     * @throws Runtime
     */
    protected function tgz(array $data)
    {
        $tgz = Arr::get($data, 'dist.tarball');

        if ($tgz === null)
        {
            throw new Runtime('file tgz not found');
        }

        return $tgz;
    }

    protected function log()
    {
        fwrite(STDOUT, implode(' ', func_get_args()) . PHP_EOL);
    }

    public function downloadVersion($name, array $tagVersion, $data)
    {
        list($tag, $version) = $tagVersion;
        $pathTo = $this->storage . $name . '/';

        for ($i = 0; $i < 5; $i++)
        {
            $success = true;

            try
            {
                if ($i)
                {
                    $this->log('try ... ');
                }

                if ($tag !== $version)
                {
                    $this->downloadVersion($name, [$version, $version], $data);

                    chdir($pathTo);

                    var_dump(File::isLink($tag));die;
                    if (File::isLink($tag))
                    {
                        File::remove($tag);
                    }

                    File::symlink($version, $tag);
                    $this->log('symlink package', $name, '[' . $tag . '] from', $version);
                    break;
                }

                $tgz = $this->tgz($data);

                // fixme: remove me!?
                if (Dir::isDir($pathTo . $version))
                {
                    $this->log('skip package', $name, '[' . $version . '] from', $tgz);
                    break;
                }

                $this->log('download package', $name, '[' . $version . '] from', $tgz);

                Stream::download($tgz, (string)($tmp = new Tmp($this->tgzStorage, '.tgz')));

                Dir::make($tmp . '-data');
                Dir::make($pathTo . $version);

                $zippy = Zippy::load()->open((string)$tmp);
                $zippy->extract($tmp . '-data');

                if (Dir::isDir($pathTo . $version))
                {
                    Dir::remove($pathTo . $version, true);
                }

                $dirs = glob($tmp . '-data/*');

                if (empty($dirs))
                {
                    continue;
                }

                // moved
                rename(current($dirs), $pathTo . $version);

                if (Dir::isDir($tmp . '-data'))
                {
                    Dir::remove($tmp . '-data');
                }

                break;
            }
            catch (Page $page)
            {
                $success = false;
                var_dump($page->getMessage());
                break;
            }
            catch (\Throwable $throwable)
            {
                $success = false;
                var_dump($throwable->getMessage());
            }
            finally
            {
                if (!$success && Dir::isDir($pathTo . $version))
                {
                    Dir::remove($pathTo . $version, true);
                }
            }
        }
    }

    public function download($name)
    {
        $allow   = null;
        $verList = [];

        // eq === 0 & eq === false
        if (\strpos($name, '@'))
        {
            list($name, $allow) = \explode('@', $name);
        }

        $versions = $this->versions($name);

        foreach ($versions as $version => $mixed)
        {
            $verList[$version] = $version;
        }

        $verList = \array_merge(
            $verList,
            $this->store($name, 'dist-tags', [])
        );

        foreach ($verList as $tag => $version)
        {
            if ($allow && $tag !== $allow)
            {
                continue;
            }

            if (!isset($versions[$version]))
            {
                throw new Blank('Undefined version `' . $tag . '`');
            }

            $this->downloadVersion($name, [$tag, $version], $versions[$version]);
        }
    }

}
