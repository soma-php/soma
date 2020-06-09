<?php

/*--------------------------------------------------*/

if (! function_exists('app')) {
    function app($abstract = null)
    {
        $app = \Soma\Application::getInstance();

        if (is_null($abstract)) {
            return $app;
        }

        return $app->get($abstract);
    }
}

if (! function_exists('should_optimize')) {
    function should_optimize()
    {
        return app()->isPerformanceMode();
    }
}

if (! function_exists('is_debug')) {
    function is_debug()
    {
        return app()->isDebug();
    }
}

if (! function_exists('is_ajax')) {
    function is_ajax()
    {
        return app()->isAjaxRequest();
    }
}

if (! function_exists('is_cli')) {
    function is_cli()
    {
        return app()->isCommandLine();
    }
}

if (! function_exists('is_web')) {
    function is_web()
    {
        return app()->isWebRequest();
    }
}

if (! function_exists('app_stage')) {
    function app_stage($test = '')
    {
        if (! empty($test)) {
            return app()->isStage($test);
        }
        else {
            return app()->getStage();
        }
    }
}

if (! function_exists('app_path')) {
    /**
     * Get the path to the application folder.
     *
     * @param  string  $path
     * @return string
     */
    function app_path($path = '')
    {
        return app()->getRootPath($path);
    }
}

if (! function_exists('app_url')) {
    /**
     * Get the uri to the application folder.
     *
     * @param  string  $uri
     * @return string
     */
    function app_url($url = '')
    {
        return app()->getRootUrl($url);
    }
}

if (! function_exists('public_path')) {
    function public_path($path = '')
    {
        return app_path($path);
    }
}

if (! function_exists('public_url')) {
    function public_url($url = '')
    {
        return app_url($url);
    }
}

if (! function_exists('module_url')) {
    /**
     * Get the uri to the application folder.
     *
     * @param  string  $uri
     * @return string
     */
    function module_url($module, $url = '')
    {
        return get_url('extensions.public').'/'.$module.($url ? '/'.$url : $url);
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the path to the application folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        return get_path('storage').($path ? '/'.$path : $path);
    }
}

if (! function_exists('get_path')) {
    /**path
     * Get the path to the application folder.
     *
     * @param  string  $path
     * @return string
     */
    function get_path($path, $default = null)
    {
        return app()->paths()->get($path, $default);
    }
}

if (! function_exists('get_url')) {
    /**
     * Get the uri to the application folder.
     *
     * @param  string  $uri
     * @return string
     */
    function get_url($url, $default = null)
    {
        return app()->urls()->get($url, $default);
    }
}

if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function config($key, $default = null)
    {
        return app('config')->get($key, $default);
    }
}

if (! function_exists('event')) {
    function event($event, $payload = [])
    {
        return app()->getEventDispatcher()->dispatch($event, $payload);
    }
}

if (! function_exists('listen')) {
    function listen($event, $handler)
    {
        return app()->getEventDispatcher()->listen($event, $handler);
    }
}

if (! function_exists('run_command')) {
    function run_command($command)
    {
        return app()->runCommand($command);
    }
}

if (! function_exists('is_valid')) {
    function is_valid($object)
    {
        if (($object instanceof \Soma\Contracts\ValidityChecking || method_exists($object, '__validate')) && $object->__validate()) {
            return true;
        }
        elseif (! is_null($object)) {
            return true;
        }

        return false;
    }
}

/*--------------------------------------------------*/

if (! function_exists('make_datetime')) {
    function make_datetime($date_str, $format = null)
    {
        if (empty($format)) {
            if (is_int($date_str))
                $format = 'U';
            elseif (defined('DATE_FORMAT'))
                $format = DATE_FORMAT;
            else
                $format = \DateTime::ISO8601;
        }

        return \DateTime::createFromFormat($format, $date_str);
    }
}

if (! function_exists('format_date')) {
    function format_date($date, $format = null)
    {
        if (is_null($format) && defined('DATE_FORMAT')) {
            $format = DATE_FORMAT;
        }

        if ($date instanceof \DateTime) {
            return $date->format($format);
        }

        return $date;
    }
}

if (! function_exists('validate_date')) {
    function validate_date($date, $format = null)
    {
        if (is_null($format) && defined('DATE_FORMAT')) {
            $format = DATE_FORMAT;
        }

        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

/*--------------------------------------------------*/

if (! function_exists('empty_dir')) {
    function empty_dir($path, $recursive = true, $preserveDirs = false)
    {
        if (! is_dir($path)) {
            return false;
        }

        foreach (glob($path.'/*') ?: [] as $file) {
            if (is_dir($file)) {
                if (! $recursive) {
                    continue;
                }

                if ($preserveDirs) {
                    empty_dir($file, true, true);
                } else {
                    runlink($file);
                }
            } else {
                unlink($file);
            }
        }

        return true;
    }
}

if (! function_exists('runlink')) {
    function runlink($path)
    {
        if (! is_dir($path)) {
            return false;
        }

        $di = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($ri as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }

        return rmdir($path);
    }
}

if (! function_exists('canonicalize_path')) {
    function canonicalize_path($address)
    {
        $address = explode('/', $address);
        $keys = array_keys($address, '..');

        foreach($keys as $keypos => $key) {
            array_splice($address, $key - ($keypos * 2 + 1), 2);
        }

        $address = implode('/', $address);
        $address = str_replace('./', '', $address);

        return $address;
    }
}

if (! function_exists('make_numeric')) {
    function make_numeric($val)
    {
        if (is_numeric($val)) {
            return $val + 0;
        }

        return 0;
    }
}

if (! function_exists('is_booly')) {
    function is_booly($val)
    {
        switch (strtolower($val)) {
            case "y":
            case "yes":
            case "(yes)":
            case "true":
            case "(true)":
            case "n":
            case "no":
            case "(no)":
            case "false":
            case "(false)":
                return true;
        }

        return false;
    }
}

if (! function_exists('make_bool')) {
    function make_bool($val)
    {
        if (is_bool($val)) {
            return $val;
        }
        
        switch (strtolower($val)) {
            case "y":
            case "yes":
            case "(yes)":
            case "true":
            case "(true)":
                return true;
            case "n":
            case "no":
            case "(no)":
            case "false":
            case "(false)":
                return false;
        }

        return boolval($val);
    }
}

if (! function_exists('ensure_dir_exists')) {
    function ensure_dir_exists($path, $permissions = 0775)
    {
        if (! file_exists($path)) {
            mkdir($path, $permissions, true);
        }

        return $path;
    }
}

if (! function_exists('is_url')) {
    function is_url($url)
    {
        return (filter_var($url, FILTER_VALIDATE_URL)) ? true : false;
    }
}

if (! function_exists('parse_attributes')) {
    function parse_attributes($attr = array())
    {
        if ( ! is_array($attr)) {
            return '';
        }

        return join(' ', array_map(function($key) use ($attr) {
           if (is_bool($attr[$key])) {
              return $attr[$key] ? $key : '';
           }

           if (is_array($attr[$key])) {
               return $key.'="'.implode(' ', $attr[$key]).'"';
           }
           else {
               return $key.'="'.$attr[$key].'"';
           }
        }, array_keys($attr)));
    }
}

if (! function_exists('common_path')) {
    function common_path($paths)
    {
        $lastOffset = 1;
        $common = '/';

        while (($index = strpos($paths[0], '/', $lastOffset)) !== false) {
            $dirLen = $index - $lastOffset + 1;	// include /
            $dir = substr($paths[0], $lastOffset, $dirLen);

            foreach ($paths as $path) {
                if (substr($path, $lastOffset, $dirLen) != $dir) {
                    return $common;
                }
            }

            $common .= $dir;
            $lastOffset = $index + 1;
        }

        return substr($common, 0, -1);
    }
}

if (! function_exists('remove_double_slashes')) {
    function remove_double_slashes($path)
    {
        $path = str_replace('//', '/', $path);
        $path = str_replace('//', '/', $path);
        return $path;
    }
}

if (! function_exists('build_url')) {
    function build_url(array $parts)
    {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
            ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
            (isset($parts['user']) ? "{$parts['user']}" : '') .
            (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
            (isset($parts['user']) ? '@' : '') .
            (isset($parts['host']) ? "{$parts['host']}" : '') .
            (isset($parts['port']) ? ":{$parts['port']}" : '') .
            (isset($parts['path']) ? "{$parts['path']}" : '') .
            (isset($parts['query']) ? "?{$parts['query']}" : '') .
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }
}

/*--------------------------------------------------*/

if (! function_exists('rel_path')) {
    // Convert an absolute path to a relative
    function rel_path($uri, $compareWith)
    {
        return ltrim(substr($uri, strlen($compareWith)), '/');
    }
}