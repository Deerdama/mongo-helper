<?php

namespace Deerdama\MongoHelper;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CollectionTrait
{
    /**
     * build the query
     */
    private function getCollection()
    {
        $request = DB::collection($this->collectionName)
            ->when($this->option('select'), function ($q) {
                $q->select($this->option('select'));
            })
            ->when($this->option('limit'), function ($q) {
                $q->limit((int)$this->option('limit'));
            });

        if (count($this->option('where'))) {
            $this->whereParam($request);
        }

        return $request;
    }

    /**
     * form query parameters
     *
     * @param $q
     */
    private function whereParam($q)
    {
        foreach ($this->option('where') as $where) {
            preg_match('/^.*(?=,)/U', $where, $field);
            preg_match('/(?<=,).*(?=,|$)/U', $where, $operator);
            preg_match('/\,cast=.*$|\, cast=.*$/U', $where, $cast);

            if ($cast) {
                $where = str_replace($cast[0], '', $where);
                preg_match('/(?<=cast=).*(?=\s|$)/U', $cast[0], $type);
                $cast = $type[0];
            }

            preg_match('/^.*\,.*\,(.*)$/U', $where, $value);

            if (!$field || !$operator) {
                continue;
            }

            $field = trim($field[0], ' ');
            $operator = strtoupper(trim($operator[0], ' '));
            $value = trim($value[1] ?? '', ' ');

            if (strpos($value, '[') === 0) {
                $value = str_replace([', ', ' ,'], ',', $value);
                $value = explode(',', trim($value, '[]'));
            }

            if ($cast) {
                $value = $this->castValue($value, $cast);
            }

            if ($operator == 'NULL') {
                $q->whereNull($field);
            } else if ($operator == 'NOT NULL') {
                $q->whereNotNull($field);
            } else if (!$value) {
                continue;
            } else if ($operator == 'IN') {
                $q->whereIn($field, (array)$value);
            } else if ($operator == 'NOT IN') {
                $q->whereNotIn($field, (array)$value);
            } else if ($operator == 'BETWEEN') {
                $q->whereBetween($field, (array)$value);
            } else {
                $q->where($field, $operator, $value);
            }
        }
    }

    /**
     * cast the value for the where condition as a specific type
     *
     * @param string|array $value
     * @param string $type
     * @param bool $arr
     * @return array|bool|int|object|string
     */
    private function castValue($value, $type, $arr = false)
    {
        if (is_array($value) && $arr === false) {
            foreach ($value as $item) {
                $new[] = $this->castValue($item, $type, true);
            }
        } else if ($type === 'int' || $type === 'integer') {
            $new = (int)$value;
        } else if ($type === 'bool' || $type === 'boolean') {
            $new = $value === 'false' ? false : (bool)$value;
        } else if ($type === 'object' || $type === 'obj') {
            $new = (object)$value;
        } else if ($type === 'array' || $type === 'arr') {
            $new = (array)$value;
        }

        return $new ?? $value;
    }

    /**
     * @return array
     */
    private function getAllCollections()
    {
        $collections = [];

        foreach (DB::listCollections() as $collection) {
            $collections[]['collection'] = $collection->getName();
        }

        return $collections;
    }

    /**
     * dump the names of all existing collections
     */
    private function listCollections($collections = null)
    {
        $collections = $collections ?: $this->getAllCollections();

        foreach ($collections as $collection) {
            $this->zoo('<icon>eight_spoked_asterisk</icon> ' . $collection['collection'], [
                'color' => 'light_blue_dark_1'
            ]);
            $this->line(" --------------------------------------");
        }
    }

    /**
     * output a table with all collections and their size
     */
    private function getAllCounts()
    {
        $collections = $this->getAllCollections();
        $result = [];

        foreach ($collections as $collection) {
            $collection['total'] = DB::collection($collection['collection'])->count();
            $result[] = $collection;
        }

        $this->table(['Collection', 'Total'], $result);
    }

    /**
     * Output total collection records
     */
    private function collectionCount()
    {
        $this->zoo("<icon>pushpin</icon> There are <zoo swap> {$this->collection->count()} </zoo> records in the <zoo underline>{$this->collectionName}</zoo> collection");
    }

    /**
     * completely drop a collection
     */
    private function dropCollection()
    {
        $count = DB::collection($this->collectionName)->count();

        $this->zooWarning("Do you really want to drop <zoo swap>{$this->collectionName}</zoo> collection? (contains {$count} records)", [
            'icons' => 'heavy_exclamation_mark_symbol'
        ]);

        if ($this->confirm("") === true) {
            Schema::connection('mongodb')->drop($this->collectionName);
            $this->line("");
            $this->zoo("Collection <zoo swap>{$this->collectionName}</zoo> dropped... hope that's what you wanted", [
                'icons' => 'thumbs_up_sign'
            ]);
        }
    }

    /**
     * delete all records from a collection
     */
    private function delete()
    {
        $count = $this->collection->count();

        if (!$count) {
            return $this->zooInfo("<icon>thumbs_up_sign</icon> Collection <zoo swap>{$this->collectionName}</zoo> is already empty or there aren't any matching results", [
                'icons' => false
            ]);
        }

        $this->zooWarning("Do you really want to delete all <zoo swap> {$count} </zoo> records from <zoo underline>{$this->collectionName}</zoo>?", [
            'icons' => 'black_question_mark_ornament'
        ]);

        if ($this->confirm("") === true) {
            $this->collection->delete();
            $this->zoo("All set! {$count} records deleted from <zoo underline>{$this->collectionName}</zoo>", [
                'icons' => 'thumbs_up_sign'
            ]);
        }
    }
}