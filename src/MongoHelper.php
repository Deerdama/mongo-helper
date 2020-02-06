<?php

namespace Deerdama\MongoHelper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MongoHelper extends Command
{
    use ImportExportTrait;
    use CollectionTrait;
    use \Deerdama\ConsoleZoo\ConsoleZoo;

    /** @var string */
    protected $collectionName;

    /** @var */
    protected $collection;

    /** @var string */
    protected $connection;

    /** @var array */
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
                            {--where=* : query builder where parameters}
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
        $this->zooSetDefaults(['bold', 'color' => 'green']);
        $this->line("");

        if ($this->setupConnection() === false) {
            return;
        }

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
     * try to connect to the DB and check if it's mongo
     *
     * @param bool $fail
     * @return bool
     */
    private function setupConnection($fail = false)
    {
        if ($fail === true) {
            $this->line("");
            $this->zoo("Check <zoo underline>https://github.com/Deerdama/mongo-helper#Config</zoo> for extra info", [
                'bold' => false,
                'color' => 'blue'
            ]);

            return false;
        }

        $this->connection = $this->option('connection') ?? config('mongo_helper.connection');
        DB::setDefaultConnection($this->connection);

        try {
            $driver = DB::connection()->getDriverName();

            if ($driver !== 'mongodb') {
                $this->zoo("The configured connection '<zoo underline>{$this->connection}</zoo>' is not a mongodb. Its driver is <zoo underline>{$driver}</zoo>", $this->errorParam);
                return $this->setupConnection(true);
            }
        } catch (\Exception $e) {
            $this->zoo("Can't connect to the database.. make sure that the connection <zoo underline>{$this->connection}</zoo> exists", $this->errorParam);
            return $this->setupConnection(true);
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

            if ($this->confirm("")) {
                return $this->listCollections();
            } else {
                return null;
            }
        }

        return $name;
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
            return $this->collectionCount();
        }

        if ($this->option('dump')) {
            return $this->collection->get()->dump();
        }

        $this->zoo("And what exactly am I supposed to do with the <zoo swap>{$this->collectionName}</zoo> collection? Try again with some option please", [
            'color' => 'orange',
            'icons' => 'astonished_face'
        ]);
    }
}
