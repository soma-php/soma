<?php

if (! function_exists('app')) {
    /**
     * Resolve a dependency from the container
     *
     * @param string|null $abstract If null then the \Soma\Application instance itself
     * @return mixed
     */
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
    /**
     * Checks whether APP_OPTIMIZE is true
     *
     * @return boolean
     */
    function should_optimize() : bool
    {
        return app()->isPerformanceMode();
    }
}

if (! function_exists('is_debug')) {
    /**
     * Checks whether APP_DEBUG is true
     *
     * @return boolean
     */
    function is_debug() : bool
    {
        return app()->isDebug();
    }
}

if (! function_exists('is_ajax')) {
    /**
     * Checks if the application is responding to an AJAX request
     *
     * @return boolean
     */
    function is_ajax() : bool
    {
        return app()->isAjaxRequest();
    }
}

if (! function_exists('is_cli')) {
    /**
     * Checks if the application is run via the command line interface
     *
     * @return boolean
     */
    function is_cli() : bool
    {
        return app()->isCommandLine();
    }
}

if (! function_exists('is_web')) {
    /**
     * Checks if the application is responding to a regular web request
     *
     * @return boolean
     */
    function is_web() : bool
    {
        return app()->isWebRequest();
    }
}

if (! function_exists('app_stage')) {
    /**
     * Test string against APP_STAGE
     *
     * @param [$test] If omitted then the current stage will be returned
     * @return boolean|string
     */
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
     * Get the path to the application folder or a resource relative to its root
     *
     * @param string [$path] 
     * @return string
     */
    function app_path($path = '') : string
    {
        return app()->getRootPath($path);
    }
}

if (! function_exists('app_url')) {
    /**
     * Get the URL to the application folder or a resource relative to its root
     *
     * @param string [$url] 
     * @return string
     */
    function app_url($url = '') : string
    {
        return app()->getRootUrl($url);
    }
}

if (! function_exists('public_path')) {
    /**
     * Get the path to the public folder or a resource relative to its root
     *
     * @param string [$path] 
     * @return string
     */
    function public_path($path = '') : string
    {
        return app_path($path);
    }
}

if (! function_exists('public_url')) {
    /**
     * Get the url to the public folder or a resource relative to its root
     *
     * @param string [$url] 
     * @return string
     */
    function public_url($url = '') : string
    {
        return app_url($url);
    }
}

if (! function_exists('module_url')) {
    /**
     * Get the url for a module resource or its root
     * 
     * The module helper is simply building an URL according
     * what's recommended as best practice for modules to 
     * serve content.
     *
     * @param string [$path] 
     * @param string [$url] 
     * @return string
     */
    function module_url(string $module, $url = '') : string
    {
        return get_url('extensions.public').'/'.$module.($url ? '/'.$url : $url);
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the path to the storage folder or a resource relative to its root
     *
     * @param string [$path] 
     * @return string
     */
    function storage_path($path = '') : string
    {
        return get_path('storage').($path ? '/'.$path : $path);
    }
}

if (! function_exists('get_path')) {
    /**
     * Get a specific named path registered with the application
     *
     * @param string $name
     * @param string|null [$default]
     * @return string
     */
    function get_path($name, $default = null)
    {
        return app()->paths()->get($name, $default);
    }
}

if (! function_exists('get_url')) {
    /**
     * Get a specific named URL registered with the application
     *
     * @param string $name
     * @param string|null [$default]
     * @return string
     */
    function get_url($name, $default = null)
    {
        return app()->urls()->get($name, $default);
    }
}

if (! function_exists('config')) {
    /**
     * Get the specified configuration value.
     *
     * @param string $key A key namespaced using dot-notation
     * @param mixed [$default]
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        return app('config')->get($key, $default);
    }
}

if (! function_exists('event')) {
    /**
     * Trigger an event
     *
     * @param string|object $event
     * @param mixed [$payload]
     * @param boolean [$halt]
     * @return array|null
     */
    function event($event, $payload = [], $halt = false)
    {
        return app()->getEventDispatcher()->dispatch($event, $payload, $halt);
    }
}

if (! function_exists('listen')) {
    /**
     * Register an event listener
     *
     * @param string|array $events
     * @param mixed $listener
     * @return void
     */
    function listen($events, $listener) : void
    {
        app()->getEventDispatcher()->listen($events, $listener);
    }
}

if (! function_exists('run_command')) {
    /**
     * Call a console command
     *
     * @param string $command
     * @return int
     */
    function run_command(string $command) : int
    {
        return app()->runCommand($command);
    }
}

if (! function_exists('is_valid')) {
    /**
     * Attempt to determine if the object is created correctly
     * 
     * Classes can implement \Soma\Contracts\ValidityChecking to make use of
     * the this feature better.
     *
     * @param mixed $object
     * @return boolean
     */
    function is_valid($object) : bool
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
    /**
     * Convert a date into a datetime
     * 
     * If a format isn't set then the DATE_FORMAT constant
     * will be used, and if that isn't defined then DateTime::ISO8601
     * will be used as fallback.
     *
     * @param string|int $date_str
     * @param string|null [$format]
     * @return \DateTime
     */
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
    /**
     * Format a \DateTime
     * 
     * Will use DATE_FORMAT if defined and simply guess the format if not
     *
     * @param \DateTime $date
     * @param string|null [$format]
     * @return string
     */
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
    /**
     * Determine if a date is valid
     * 
     * Will use DATE_FORMAT if defined and simply guess the format if not
     *
     * @param string $date
     * @param string|null [$format]
     * @return bool
     */
    function validate_date($date, $format = null) : bool
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
    /**
     * Empty the children of a directory
     *
     * @param string $path
     * @param boolean [$recursive]
     * @param boolean [$preserveDirs]
     * @return boolean
     */
    function empty_dir($path, $recursive = true, $preserveDirs = false) : bool
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
    /**
     * Recursive unlink
     *
     * @param string $path
     * @return bool
     */
    function runlink($path) : bool
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
    /**
     * Will resolve "../" and "./"
     * 
     * An alternative to using `realpath` if you wish to avoid
     * resolving symlinks
     *
     * @param string $address
     * @return string
     */
    function canonicalize_path($address) : string
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
    /**
     * Convert string to a numeric
     *
     * @param string $val
     * @return int|float
     */
    function make_numeric($val)
    {
        if (is_numeric($val)) {
            return $val + 0;
        }

        return 0;
    }
}

if (! function_exists('is_booly')) {
    /**
     * Check whether a string has a "booly" value
     *
     * @param string $val
     * @return boolean
     */
    function is_booly($val) : bool
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
    /**
     * Convert object into its boolean value
     *
     * @param mixed $val
     * @return bool
     */
    function make_bool($val) : bool
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
    /**
     * Creates directory recursively if it doesn't exist and returns it
     *
     * @param string $path
     * @param integer $permissions
     * @return string
     */
    function ensure_dir_exists($path, $permissions = 0775)
    {
        if (! file_exists($path)) {
            mkdir($path, $permissions, true);
        }

        return $path;
    }
}

if (! function_exists('is_url')) {
    /**
     * Checks whether a string is an URL
     *
     * @param string $url
     * @return boolean
     */
    function is_url(string $url) : bool
    {
        return (filter_var($url, FILTER_VALIDATE_URL)) ? true : false;
    }
}

if (! function_exists('parse_attributes')) {
    /**
     * Combines an associative array into an HTML attribute string
     *
     * @param array $attr
     * @return string
     */
    function parse_attributes(array $attr = []) : string
    {
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
    /**
     * Returns the lowest common directory of array of paths
     *
     * @param array $paths
     * @return string
     */
    function common_path(array $paths) : string
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
    /**
     * Remove double forward slashes from string
     *
     * @param string $path
     * @return string
     */
    function remove_double_slashes(string $path) : string
    {
        $path = str_replace('//', '/', $path);
        $path = str_replace('//', '/', $path);
        return $path;
    }
}

if (! function_exists('build_url')) {
    /**
     * Construct an URL from an array
     * 
     * See PHP documentation for `parse_url`
     *
     * @param array $parts
     * @return string
     */
    function build_url(array $parts) : string
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
    /**
     * Convert an absolute path into a relative
     *
     * @param string $path
     * @param string $compareWith
     * @return string
     */
    function rel_path(string $path, string $compareWith) : string
    {
        return ltrim(substr($path, strlen($compareWith)), '/');
    }
}