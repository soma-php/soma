<?php namespace Soma;

use Illuminate\Support\Arr;

class Repository extends Store
{
    protected $data;

    public function __construct($data = [])
    {
        if ($data instanceof \Soma\Contracts\Store) {
            $data = $data->all();
        }

        $this->data = $data;
    }

    public function exists(string $key)
    {
        return Arr::has($this->data, $key);
    }

    public function get(string $key, $default = null)
    {
        return Arr::get($this->data, $key, $default);
    }

    public function set(string $key, $value = null)
    {
        Arr::set($this->data, $key, $value);

        return $this;
    }

    public function remove($key)
    {
        Arr::forget($this->data, $key);

        return null;
    }
}
