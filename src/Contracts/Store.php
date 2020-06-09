<?php namespace Soma\Contracts;

use ArrayAccess;

interface Store extends ArrayAccess
{
    public function get(string $key, $default = null);

    public function set(string $key, $value);

    public function exists(string $key);

    public function has(string $key);

    public function is(string $key);

    public function all();

    public function reset();

    public function put($key, $value = null);

    public function replace(array $attributes);

    public function remove($key);

    public function pull(string $key, $default = null);

    public function increment(string $key, $amount = 1);

    public function decrement(string $key, $amount = 1);

    public function prepend(string $key, $value);

    public function push(string $key, $value);
}
