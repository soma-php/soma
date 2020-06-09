<?php namespace Soma;

class Store implements \Soma\Contracts\Store
{
    protected $data;

    public function __construct($data = [])
    {
        if ($data instanceof \Soma\Contracts\Store) {
            $data = $data->all();
        }

        $this->data = $data;
    }

    public function reset()
    {
        $this->data = [];
    }

    public function all()
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function exists(string $key)
    {
        return array_key_exists($key, $this->data);
    }

    public function has(string $key)
    {
        // Check if it's not empty
        if ($this->exists($key)) {
            if (! empty($this->get($key))) {
                return true;
            }
        }

        return false;
    }

    public function is(string $key)
    {
        // Check if it's truthy
        if ($this->exists($key)) {
            if ($this->get($key)) {
                return true;
            }
        }

        return false;
    }

    public function remove($key)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->remove($k);
            }
        }
        elseif ($this->exists($key)) {
            $value = $this->data[$key];
            unset($this->data[$key]);
            return $value;
        }

        return null;
    }

    public function put($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        }
        else {
            $this->set($key, $value);
        }

        return $this;
    }

    public function replace(array $attributes)
    {
        $this->put($attributes);

        return $this;
    }

    public function pull(string $key, $default = null)
    {
        if (! $this->exists($key)) {
            return $default;
        }

        return $this->remove($key);
    }

    public function increment(string $key, $amount = 1)
    {
        $this->put($key, $value = $this->get($key, 0) + $amount);

        return $value;
    }

    public function decrement(string $key, $amount = 1)
    {
        return $this->increment($key, $amount * -1);
    }

    public function prepend(string $key, $value)
    {
        $array = $this->get($key);
        array_unshift($array, $value);
        $this->set($key, $array);

        return $this;
    }

    public function push(string $key, $value)
    {
        $array = $this->get($key);
        $array[] = $value;
        $this->set($key, $array);

        return $this;
    }

    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->remove($key);
    }

    public function __get(string $key)
    {
        return $this->get($key);
    }

    public function __set(string $key, $value)
    {
        return $this->set($key, $value);
    }
}
