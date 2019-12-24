<?php

namespace Deerdama\MongoHelper;

trait ImportExportTrait
{
    /**
     * download the collection
     */
    private function downloadCollection()
    {
        if (!$this->collection->count()) {
            return $this->warn(" * Collection {$this->collectionName} is empty *");
        }

        $timestamp = date('Y_m_d_H_i_s');
        $path = $this->option('download_path') ?: config('config.directory');
        $file = $path . $this->collectionName . '_' . $timestamp;

        if (!$this->option('csv')) {
            config('config.storage')->put($file . '.json', $this->collection->get());
            $this->info(PHP_EOL . " * Collection downloaded to {$file}.json *");
        } else {
            config('config.storage')->put($file . '.csv', '');
            $this->downloadCsv($file . '.csv');
        }
    }

    /**
     * export data into a csv file
     *
     * @param string $path
     */
    private function downloadCsv($path)
    {
        $this->writer = \League\Csv\Writer::createFromPath(config('config.storage')->path($path), 'a+');
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

        $this->writer->insertOne($headers);
        $this->writer->insertAll($results);

        $this->info(PHP_EOL . " * Collection downloaded to {$path} *");
    }

    /**
     * associate data to correct column, since they can differ across records
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
}