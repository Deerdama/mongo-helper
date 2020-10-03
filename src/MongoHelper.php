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
    protected $allCollections;

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
                            {--sort= : order results based on a specific field}
                            {--order= : order results based on a specific field}
                            {--desc : make the --sort option descending}
                            {--delete : delete all records in the collection}
                            {--drop : completely drop the collection}
                            {--download : download the collection into the storage}
                            {--csv : download as csv}
                            {--download_path= : download the collection into a specific directory}
                            {--import= : path to the file to upload into the specified collection}
                            {--update=* : update specific records}"';

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

        return $this->anticipate("Collection Name:", array_column($collections, 'collection'));
    }

    /**
     * check if the requested collection exists
     */
    private function collectionExists()
    {
        $collections = $this->getAllCollections();

        if (!in_array($this->collectionName, array_column($collections, 'collection'))) {
            $this->zooWarning(" <icon>no_entry</icon> Nope.. sorry but the collection <zoo swap> {$this->collectionName} </zoo> doesn't exist, try again..", [
                'icons' => false,
            ]);

            if ($this->choice('Do you want me to show you all the collections I found?', ['no', 'yes'], 1) === 'yes') {
                $this->table(['Collections'], $collections);
                $this->br();
            }

            $this->br();
            $this->collectionName = $this->anticipate("Collection Name?", array_column($collections, 'collection'));
            $this->br();

            if (!$this->collectionName) {
                $this->zooError("Come back when you make up your mind!", ['icons' => 'angry_face']);
                return false;
            }

            return $this->collectionExists();
        }
    }

    /**
     * handle all the options for a specific collection
     */
    private function specificCollection()
    {
        if ($this->collectionExists() === false) {
            return;
        }

        if ($this->option('drop')) {
            return $this->dropCollection();
        }

        $this->collection = $this->getCollection();

        if ($this->option('count')) {
            $this->collectionCount();
        } else if ($this->option('download') || $this->option('download_path')) {
            $this->downloadCollection();
        } else if ($this->option('delete')) {
            $this->delete();
        } else if ($this->option('dump')) {
            $this->collection->get()->dump();
        } else if ($this->option('update')) {
            $this->update();
        } else {
            $this->zoo("And what exactly am I supposed to do with the <zoo swap>{$this->collectionName}</zoo> collection? Try again with some option..", [
                'color' => 'orange',
                'icons' => 'astonished_face'
            ]);
            $this->br();
            $this->zooWarning("   Run <zoo italic>php artisan db:mongo-helper --help</zoo> to see all available options", ['icons' => false, 'bold' => false]);
        }
    }
}
