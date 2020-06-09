<?php

namespace Soma;

use stdClass;
use Traversable;
use InvalidArgumentException;

use Soma\Repository;
use Soma\Contracts\Store;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class Manifest extends Repository
{
    protected $path;
    protected $format;

    protected $cache;
    protected $cachePath;
    protected $mtime;

    public static $cacheDir;    

    public function __construct($path, bool $cache = false, bool $mtime = false, ?callable $onload = null, ?callable $onsave = null)
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Manifest file doesn't exist");
        }

        if ($path instanceof Store) {
            $data = $path->all();
        }
        elseif (is_array($path)) {
            $data = $path;
        }
        else {
            $data = [];
        }
        
        $this->path = $path;
        $this->format = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $this->cache = $cache;
        $this->cachePath = ($cache) ? self::$cacheDir.'/'.md5($path).'.php' : null;
        $this->mtime = $mtime;

        if (is_string($path) && file_exists($path)) {
            if ($cache && ! is_debug() && $this->validateCache($this->mtime)) {
                $data = self::parseFile($this->cachePath);
            }
            else {
                $data = self::parseFile($this->path);       

                if (is_callable($onsave)) {
                    $data = $onsave($data);
                }
                    
                if ($cache && ! is_debug()) {
                    $this->save($this->cachePath);
                }
            }

            $data = (is_callable($onload)) ? $onload($data) : $data;
        }

        parent::__construct($data);
    }

    public function validateCache($mtime = true)
    {
        if (! file_exists($this->path) || ! file_exists($this->cachePath)) {
            return false;
        }

        if (! $mtime || ($mtime && filemtime($this->cachePath) > filemtime($this->path))) {
            return true;
        }
        
        return false;
    }

    public static function parseFile($path)
    {
        if (! is_file($path)) {
            return null;
        }

        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::parse($path, $format);
    }

    public static function parse(string $string, string $format = null)
    {
        $format ??= 'PHP';
        $format = (strtoupper($format) == 'YML') ? 'YAML' : $format;
        $method = 'parse'.strtoupper($format);

        if (! method_exists(self::class, $method)) {
            throw new InvalidArgumentException('Manifest format not supported: '.$format);
        }

        return call_user_func([self::class, $method], $string);
    }

    public static function parseINI($string)
    {
        if (is_file($string)) {
            return parse_ini_file($path, true) ?: [];
        }

        return parse_ini_string($path, true) ?: [];
    }

    public static function parseYAML($string)
    {
        if (is_file($string)) {
            return Yaml::parseFile($string, Yaml::PARSE_DATETIME) ?: [];
        }

        return Yaml::parse($string, Yaml::PARSE_DATETIME) ?: [];
    }

    public static function parseJSON($string)
    {
        if (is_file($string)) {
            $string = file_get_contents($string);
        }

        return json_decode($string, true) ?: [];
    }

    public static function parsePHP($string)
    {
        if (is_file($string)) {
            return (require $string) ?: [];
        }
        
        return [];
    }

    public function save($path = null)
    {
        $path ??= $this->path;
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($path == $this->path && $format != $this->format) {
            throw new InvalidArgumentException("You cannot save the manifest at its original location and also change its format");
        }

        return self::dumpFile($path, $this->all());
    }

    public static function dumpFile($path, $data)
    {
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $content = self::dump($data, $format);

        if (! touch($path)) {
            throw new \ErrorException('Failed to create file: '.$path);
        }
        if (! file_put_contents($path, $content)) {
            throw new \ErrorException('Failed to write to file: '.$path);
        }

        return true;
    }
    
    public static function dump($object, string $format = 'php') : string
    {
        $format = (strtoupper($format) == 'YML') ? 'YAML' : $format;
        $method = 'dump'.strtoupper($format);

        if (! method_exists(self::class, $method)) {
            throw new InvalidArgumentException('Manifest format not supported: '.$format);
        }

        return call_user_func([self::class, $method], $object);
    }

    public static function dumpPHP($data) : string
    {
        return '<?php return '.var_export($data, true).';';
    }

    public static function dumpJSON($data, $options = JSON_PRETTY_PRINT) : string
    {
        return json_encode($data, $options);
    }

    public static function dumpYAML($data) : string
    {
        return Yaml::dump($data);
    }

    public static function dumpINI($data) : string
    {
        if (! (is_array($data) || $data instanceof Traversable || $data instanceof stdClass)) {
            throw new InvalidArgumentException('Data must be iterable');
        }

        // process array
        $data = [];

        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $data[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    if (is_array($sval)) {
                        foreach ($sval as $_skey => $_sval) {
                            if (is_numeric($_skey)) {
                                $data[] = $skey.'[] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            }
                            else {
                                $data[] = $skey.'['.$_skey.'] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            }
                        }
                    }
                    else {
                        $data[] = $skey.' = '.(is_numeric($sval) ? $sval : (ctype_upper($sval) ? $sval : '"'.$sval.'"'));
                    }
                }
            }
            else {
                $data[] = $key.' = '.(is_numeric($val) ? $val : (ctype_upper($val) ? $val : '"'.$val.'"'));
            }
            // empty line
            $data[] = null;
        }

        return implode(PHP_EOL, $data).PHP_EOL;
    }
}