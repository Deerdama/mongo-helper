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

        return $request;
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
        $this->zooWarning("Do you really want to drop <zoo swap>{$this->collectionName}</zoo> collection? (contains {$this->collection->count()} records)", [
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
            return $this->zooInfo("<icon>thumbs_up_sign</icon> Collection <zoo swap>{$this->collectionName}</zoo> is already empty", [
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