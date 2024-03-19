<?php

namespace Inmanturbo\Futomaki;

use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Sushi\Sushi;

trait HasCSV {
    use Sushi;

    public static function bootHasCSV()
    {
        static::saved(function (self $model) {
            $model->writeCSV();
        });

        static::deleted(function (self $model) {
            $model->writeCSV();
        });
    }

    public function getRows()
    {
        $this->initCSV();
        return SimpleExcelReader::create($this->CSVPath())->getRows()->toArray();
    }

    public function initCSV()
    {
        if(! file_exists($this->CSVPath())) {
            $rows = $this->getCSVRows();
            touch($this->CSVPath());
            $writer = SimpleExcelWriter::create($this->CSVPath());
            $writer->addHeader(array_keys($rows[0]));
            $writer->addRows($rows);

            $writer->close();
        }
    }

    protected function getCSVRows()
    {
        return $this->rows;
    }

    protected function sushiCacheReferencePath()
    {
        return $this->CSVPath();
    }

    public function CSVPath()
    {
        return implode(DIRECTORY_SEPARATOR, [
            $this->CSVDirectory(),
            $this->CSVFileName(),
        ]);
    }

    protected function CSVFileName()
    {
        return (string) str()->of($this->sushiCacheFileName())->beforeLast('.').'.csv';
    }

    protected function CSVDirectory()
    {
        return $this->sushiCacheDirectory();
    }

    public function deleteCSV()
    {
        if(file_exists($this->CSVPath())) {
            unlink($this->CSVPath());
        }
    }

    public function forceReload()
    {
        $this->deleteCSV();
        $this->refresh();
    }

    public function backupCSV()
    {
        if(!file_exists($this->CSVPath())) {
            $this->initCSV();
        }
        copy($this->CSVPath(), $this->CSVPath().'.'.now()->format('Y-m-d-H-i-s'));
    }

    public function writeCSV()
    {
        $rows = $this->get()->map(fn (self $item) => $item->getAttributes())->toArray();

        if( count($rows) > 0) {
            $this->backupCSV();
            $this->deleteCSV();
            touch($this->CSVPath());
            $writer = SimpleExcelWriter::create($this->CSVPath());
            $writer->addHeader(array_keys($rows[0]));
            $writer->addRows($rows);
            $writer->close();
        }
    }
}