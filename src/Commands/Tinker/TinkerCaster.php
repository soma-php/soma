<?php namespace Soma\Commands\Tinker;

use Symfony\Component\VarDumper\Caster\Caster;

class TinkerCaster
{
    public static function application($app)
    {
        return $app->export();
    }

    public static function store($store)
    {
        return [
            Caster::PREFIX_VIRTUAL.'all' => $store->all(),
        ];
    }

    public static function collection($collection)
    {
        return [
            Caster::PREFIX_VIRTUAL.'data' => $collection->all(),
        ];
    }

    public static function model($model)
    {
        $attributes = array_merge(
            $model->getAttributes(), $model->getRelations()
        );

        $visible = array_flip(
            $model->getVisible() ?: array_diff(array_keys($attributes), $model->getHidden())
        );

        $results = [];

        foreach (array_intersect_key($attributes, $visible) as $key => $value) {
            $results[(isset($visible[$key]) ? Caster::PREFIX_VIRTUAL : Caster::PREFIX_PROTECTED).$key] = $value;
        }

        return $results;
    }
}