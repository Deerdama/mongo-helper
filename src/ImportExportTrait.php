<?php

namespace Deerdama\MongoHelper;

use Illuminate\Support\Facades\DB;

trait ImportExportTrait
{
    /** @var string */
    protected $path;

    /**
     * download the collection
     */
    private function downloadCollection()
    {
        if (!$this->collection->count()) {
            return $this->warn(" * Collection {$this->collectionName} is empty *");
        }

        $timestamp = date('Y_m_d_H_i_s');
        $dir = $this->option('download_path') ?: config('config.directory');
        $this->path = $dir . $this->collectionName . '_' . $timestamp;

        if (!$this->option('csv')) {
            config('config.storage')->put($this->path . '.json', $this->collection->get());
            $this->info(PHP_EOL . " * Collection downloaded to {$this->path}.json *");
        } else {
            config('config.storage')->put($this->path . '.csv', '');
            $this->downloadCsv();
        }
    }

    /**
     * export data into a csv file
     */
    private function downloadCsv()
    {
        try {
            $writer = \League\Csv\Writer::createFromPath(config('config.storage')->path($this->path . '.csv'), 'a+');
        } catch (\Throwable $e) {
            $this->error(" To use csv format, make sure you have league/csv installed");
            $this->warn(" * composer require league/csv * ");
            exit;
        }

        $data = $this->collection->get();
        $results = [];
        $headers = [];

        foreach ($data as $row) {
            foreach ($row as $column => $content) {
                if (!in_array($column, $headers)) {
                    $headers[] = $column;
                }
                $row[$column] = $this->validateContent($content);
            }
            $results[] = $this->prepareForInsert($row, $headers);
        }

        $writer->insertOne($headers);
        $writer->insertAll($results);
        $this->info(PHP_EOL . " * Collection downloaded to {$this->path}.csv *");
    }

    /**
     * associate data to correct column for csv, since they can differ across records
     *
     * @param array $row
     * @param array $headers
     * @return array
     */
    private function prepareForInsert($row, $headers)
    {
        foreach ($headers as $header) {
            $result[$header] = $row[$header] ?? null;
        }

        return $result;
    }

    /**
     * try to encode the content
     *
     * @param $content
     * @param bool $fail
     * @return string|int|null
     */
    private function validateContent($content, $fail = false)
    {
        if (is_string($content) || is_integer($content)) {
            return str_replace('\\', '', $content);
        }

        if ($fail === true) {
            return null;
        }

        return $this->validateContent(json_encode($content), true);
    }

    /**
     * confirm data import details
     */
    private function importRequest()
    {
        if (!$this->collectionName) {
            $this->collectionName = $this->ask("*** Write the name of the target collection ***");
            return $this->importRequest();
        } else {
            $confirm = $this->confirm(" * Do you really want to import everything from {$this->option('import_data')} into {$this->collectionName}? *");
            if (!$confirm) {
                return;
            }
        }

        $this->path = $this->option('import_data');
        $this->line("");
        $this->importData();
    }

    /**
     * check the file and process the import
     *
     * @param bool $retry
     */
    private function importData($retry = false)
    {
        $file = config('config.storage')->exists($this->path);

        if (!$file && !$retry) {
            $this->path = config('config.directory') . $this->path;
            return $this->importData(true);
        }

        if (!$file) {
            return $this->error("Couldn't find file {$this->path}");
        }

        if (preg_match('/\.json$/', $this->path)) {
            $total = $this->importFromJson();
        } else if (preg_match('/\.csv$/', $this->path)) {
            $total = $this->importFromCsv();
        } else {
            return $this->error("The data to import needs to be in a csv or json file");
        }

        $this->info("\n\n * {$total} records imported into {$this->collectionName}");
    }

    /**
     * import data from json file
     *
     * @return int
     */
    private function importFromJson()
    {
        $file = config('config.storage')->get($this->path);
        $data = json_decode($file);
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $item) {
            unset($item->_id);
            DB::collection($this->collectionName)->insert((array)$item);
            $bar->advance();
        }
        $bar->finish();

        return count($data);
    }

    /**
     * import data from a csv sheet
     *
     * @return int
     */
    private function importFromCsv()
    {
        try {
            $data = \League\Csv\Reader::createFromPath(config('config.storage')->path($this->path), 'r');
        } catch (\Throwable $e) {
            $this->error(" To use csv format, make sure you have league/csv installed");
            $this->warn(" * composer require league/csv * ");
            exit;
        }

        $data->setHeaderOffset(0);
        $records = $data->getRecords();
        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($records as $record) {
            DB::collection($this->collectionName)->insert($record);
            $bar->advance();
        }

        return count($data);
    }
}