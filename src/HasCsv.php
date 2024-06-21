<?php

namespace Inmanturbo\Futomaki;

use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

trait HasCsv
{
    use Futomaki;

    protected $maxFiles = 30;

    public static function bootHasCsv()
    {
        static::saved(function (self $model) {
            $model->writeCsv();
        });

        static::deleted(function (self $model) {
            $model->writeCsv();
        });
    }

    public function getRows()
    {
        $this->initCsv();

        $rows = SimpleExcelReader::create($this->CSVPath())->getRows()->toArray();

        $rows = array_map(function ($row) {
            return array_map(function ($value) {
                return $value === '' ? null : $value;
            }, $row);
        }, $rows);

        return $rows;
    }

    public function cleanupCsvFiles($maxFiles = 3)
    {
        $files = glob($this->csvDirectory().'/*');
        $files = array_combine($files, array_map('filemtime', $files));
        arsort($files);
        $files = array_slice($files, $maxFiles);
        foreach ($files as $file => $time) {
            unlink($file);
        }
    }

    public function initCsv()
    {
        if (! file_exists($this->csvPath())) {
            $rows = $this->getCsvRows();
            File::ensureDirectoryExists($this->csvDirectory());
            touch($this->csvPath());
            $writer = SimpleExcelWriter::create($this->csvPath());
            $writer->addHeader(array_keys($rows[0]));
            $writer->addRows($rows);

            $writer->close();
        }
    }

    protected function getCsvRows()
    {
        return $this->rows;
    }

    protected function sushiCacheReferencePath()
    {
        return $this->csvPath();
    }

    public function csvPath()
    {
        return implode(DIRECTORY_SEPARATOR, [
            $this->csvDirectory(),
            $this->csvFileName(),
        ]);
    }

    protected function csvFileName()
    {
        return (string) str()->of($this->sushiCacheFileName())->beforeLast('.').'.csv';
    }

    protected function csvDirectory()
    {
        return $this->sushiCacheDirectory();
    }

    public function deleteCsv()
    {
        if (file_exists($this->csvPath())) {
            unlink($this->csvPath());
        }
    }

    public function forceReload()
    {
        $this->deleteCsv();
        $this->refresh();
    }

    public function backupCsv()
    {
        if (! file_exists($this->csvPath())) {
            $this->initCsv();
        }
        copy($this->csvPath(), $this->csvPath().'.'.now()->format('Y-m-d-H-i-s'));
    }

    public function writeCsv()
    {
        if(cache()->get('sushi:lock_csv:'.md5($this->csvPath(), false))) {
            return;
        }

        // minimum lock: only write csv once in a minute
        cache()->put('sushi:lock_csv:'. md5($this->csvPath()), true, 60);

        $rows = $this->get()->map(fn (self $item) => $item->getAttributes())->toArray();

        if (count($rows) === 0) {
            return;
        }

        $pendingWritePath = $this->csvPath().'pending_write.'.now()->getTimestampMs().'.csv';
        touch($pendingWritePath);

        $writer = SimpleExcelWriter::create($pendingWritePath);
        $writer->addHeader(array_keys($rows[0]));
        $writer->addRows($rows);
        $writer->close();

        $this->backupCsv();
        copy($pendingWritePath, $this->csvPath());
        $this->cleanupCsvFiles($this->maxFiles);
    }
}
