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

    /** @var string */
    protected $collectionName;

    /** @var  */
    protected $collection;

    /** @var string */
    protected $connection;


    protected $signature = 'db:mongo-helper
                            {collection? : collection name}
                            {--list : get a list of all existing collections in mongodb}
                            {--connection= : use a specific connection name instead of the default}
                            {--count : count of records in the specified collection}
                            {--count_all : output every single collection with the records count}
                            {--limit= : limit the amount of records to get}
                            {--dump : dump the results}
                            {--select=* : get only specific fields}
                            {--delete : delete all records in the collection}
                            {--drop : completely drop the collection}
                            {--download : download the collection into the storage}
                            {--csv : download as csv}
                            {--download_path= : download the collection into a specific path}
                            {--import_data= : path to the file to upload into the specified collection}';

    protected $description = 'Methods to debug and handle mongo collections';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $this->connection = $this->option('connection') ?? config('config.connection');
        DB::setDefaultConnection($this->connection);

        if ($this->option('list')) {
            return $this->listCollections();
        }

        if ($this->option('count_all')) {
            return $this->getAllCounts();
        }

        if ($this->option('import_data')) {
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
            $this->info(' * ' . $collection['collection']);
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
            return $this->info(PHP_EOL . " * Total records in {$this->collectionName}: {$this->collection->count()} *");
        }

        if ($this->option('dump')) {
            return $this->collection->get()->dump();
        }

        $this->warn(" * And what exactly am I supposed to do with the {$this->collectionName} collection? \nTry again with some option please");
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
        $this->warn("Drop {$this->collectionName} collection with {$this->collection->count()} records?");

        if ($this->confirm("") === true) {
            Schema::connection('mongodb')->drop($this->collectionName);
            $this->info("collection {$this->collectionName} dropped");
        }
    }

    /**
     * delete all records from a collection
     */
    private function delete()
    {
        $count = $this->collection->count();

        if (!$count) {
            return $this->info("Collection {$this->collectionName} already empty");
        }

        $this->warn(" * Delete all {$count} records from {$this->collectionName} collection? *");

        if ($this->confirm("") === true) {
            $this->collection->delete();
            $this->info("{$count} records deleted from {$this->collectionName}");
        }
    }

    /**
     * anticipate collection name, list them if none is passed
     */
    private function noCollection()
    {
        $collections = $this->getAllCollections();
        $this->warn(PHP_EOL . ' * My crystal ball just broke.. what collection are we talking about?');
        $name = $this->anticipate("Collection Name:", array_column($collections, 'collection'));

        if (!$name || !in_array($name, array_column($collections, 'collection'))) {
            $this->error("Nope.. collection {$name} doesn't exist, try again..");
            $this->warn(PHP_EOL . "Little help, here are all the collections I found:" . PHP_EOL);
            return $this->listCollections();
        }

        return $name;
    }
}
