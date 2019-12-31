<?php

namespace Deerdama\MongoHelper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MongoHelper extends Command
{
    use ImportExportTrait;
    use \Deerdama\ConsoleZoo\ConsoleZoo;

    /** @var string */
    protected $collectionName;

    /** @var */
    protected $collection;

    /** @var string */
    protected $connection;

    protected $errorParam = [
        'color' => 'white',
        'icons' => 'no_entry',
        'background' => 'red',
        'bold' => false
    ];


    protected $signature = 'db:mongo-helper
                            {collection? : collection name}
                            {--list : get a list of all existing collections in mongodb}
                            {--connection= : use a specific connection name instead of the default}
                            {--count : count of records in the specified collection}
                            {--count_all : output every single collection with the records count}
                            {--limit= : limit the amount of records to retrieve}
                            {--dump : dump the results}
                            {--select=* : get only specific fields}
                            {--delete : delete all records in the collection}
                            {--drop : completely drop the collection}
                            {--download : download the collection into the storage}
                            {--csv : download as csv}
                            {--download_path= : download the collection into a specific directory}
                            {--import= : path to the file to upload into the specified collection}';

    protected $description = 'Methods to debug and handle mongo collections';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $this->connection = $this->option('connection') ?? config('config.connection');
        DB::setDefaultConnection($this->connection);
        $this->zooSetDefaults(['bold', 'color' => 'green']);
        $this->line("");

        if ($this->option('list')) {
            return $this->listCollections();
        }

        if ($this->option('count_all')) {
            return $this->getAllCounts();
        }

        if ($this->option('import')) {
            $this->collectionName = $this->argument('collection');

            return $this->importRequest();
        }

        $this->collectionName = $this->argument('collection') ?: $this->noCollection();

        if ($this->collectionName && is_string($this->collectionName)) {
            return $this->specificCollection();
        }
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
     * handle all the options for a specific collection
     */
    private function specificCollection()
    {
        $this->collection = $this->getCollection();

        if ($this->option('download') || $this->option('download_path')) {
            return $this->downloadCollection();
        }

        if ($this->option('drop')) {
            return $this->dropCollection();
        }

        if ($this->option('delete')) {
            return $this->delete();
        }

        if ($this->option('count')) {
            return $this->zoo("<icon>pushpin</icon> There are <zoo swap> {$this->collection->count()} </zoo> records in the <zoo underline>{$this->collectionName}</zoo> collection");
        }

        if ($this->option('dump')) {
            return $this->collection->get()->dump();
        }

        $this->zoo("And what exactly am I supposed to do with the <zoo swap>{$this->collectionName}</zoo> collection? Try again with some option please", [
            'color' => 'orange',
            'icons' => 'astonished_face'
        ]);
    }

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
     * completely drop a collection
     */
    private function dropCollection()
    {
        $this->zooWarning("Do you really want to drop <zoo swap>{$this->collectionName}</zoo> collection with all the {$this->collection->count()} records?", [
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

    /**
     * anticipate collection name, list them if none is passed
     */
    private function noCollection()
    {
        $collections = $this->getAllCollections();

        $this->zooWarning('Sorry, my crystal ball just broke.. what collection are we talking about?', [
            'icons' => 'crystal_ball'
        ]);

        $name = $this->anticipate("Collection Name:", array_column($collections, 'collection'));

        if (!$name || !in_array($name, array_column($collections, 'collection'))) {
            $this->zooError(PHP_EOL . " <icon>no_entry</icon> Nope.. collection <zoo swap> {$name} </zoo> doesn't exist, try again.", [
                'icons' => false
            ]);

            $this->zooWarning(PHP_EOL . " <icon>electric_light_bulb</icon> Do you want me to show you all the collections I found?", [
                'icons' => false,
                'bold' => false
            ]);

            if ($this->confirm("", true)) {
                return $this->listCollections();
            } else {
                return null;
            }
        }

        return $name;
    }
}
