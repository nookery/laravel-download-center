<?php

namespace DownloadCenter\Jobs;

use App\Jobs\DeleteFile;
use App\Jobs\Job;
use DownloadCenter\Models\DownloadTask;
use Illuminate\Database\Eloquent\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Illuminate\Support\Facades\Storage;

class DownloadJob extends Job
{
	/**
	 * 任务最大尝试次数。
	 *
	 * @var int
	 */
	public $tries = 1;

    /**
     * 文件存储在哪个disk
     *
     * @var string
     */
	public $disk = 'public';

	/**
	 * 任务运行的超时时间。
	 *
	 * @var int
	 */
	public $timeout = 1800;

    /**
     * 要处理的任务，即数据中的一条记录
     *
     * @var Collection
     */
	public $downloadTask;

	public function __construct(DownloadTask $downloadTask)
    {
        $this->downloadTask = $downloadTask;
    }

    /**
     * 执行任务
     *
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\InvalidArgumentException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Writer\Exception\WriterNotOpenedException
     */
	public function handle()
	{
	    $lockResult = DownloadTask::where('id', $this->downloadTask->id)
            ->where('status', 'ready')
            ->update(['status' => 'processing']);
	    if (!$lockResult) {
	        return false;
        } else {
	        $this->downloadTask = DownloadTask::find($this->downloadTask->id);
        }

	    // 一个Excel文件中有多个sheet，一个sheet中有多个item
        $sheets = new SheetCollection();
        $items = new Collection();

        // 组建Builder
        $builder = (unserialize($this->downloadTask->model))::ofDownload($this->downloadTask->query);

        // 更新总量
        if (!$this->downloadTask->total) {
            $this->downloadTask->total = $builder->count();
            $this->downloadTask->save();
        }

        // 确定offset和limit
        $builder->offset($this->downloadTask->offset)
            ->limit($this->downloadTask->sheet_size * $this->downloadTask->document_size);

        // 读取item
        foreach ($builder->cursor() as $item) {
            $items->push($item);
            if ($items->count() === $this->downloadTask->sheet_size) {
                $sheets->push($items);
                $items = new Collection();
            }
        }

        if ($items->count() > 0) {
            $sheets->push($items);
        }

        // 量大时，单次不能完成，记录偏移量
        $this->downloadTask->offset = $this->downloadTask->offset + $this->downloadTask->sheet_size * $this->downloadTask->document_size;
        $this->downloadTask->save();

        // 创建临时文件夹
        if (!Storage::disk($this->disk)->exists($this->getDestinationDirectory())) {
            Storage::disk($this->disk)->makeDirectory($this->getDestinationDirectory());
        }

        // 生成Excel文件
        (new FastExcel($sheets))->export($this->getDestinationDirectory(true).'/'.uniqid().'.xlsx');

        if ($this->downloadTask->offset < $this->downloadTask->total) {
            // 如果任务没有处理完，等待下次处理
            $this->downloadTask->status = 'ready';
            $this->downloadTask->save();
            dispatch(new self(DownloadTask::find($this->downloadTask->id)));
        } else {
            // 任务处理完，则创建zip文件，清理临时目录
            $this->createZipFile();
            $this->downloadTask->file_path = $this->getDestinationDirectory(true).'.zip';
            $this->downloadTask->status = 'finished';
            $this->downloadTask->save();

            $this->clean();
        }
	}

    /**
     * 生成压缩文件
     *
     */
    private function createZipFile()
    {
        $zipper = new \Chumper\Zipper\Zipper;
        $zipper->make($this->getDestinationDirectory(true).'.zip')->add($this->getDestinationDirectory(true))->close();
    }

    /**
     * 返回生成的Excel文件要存放的临时目录
     *
     * @param string $isRoot
     * @return mixed|string
     */
    private function getDestinationDirectory($isRoot = '')
    {
        $directoryName = "download_task_temp_dir[id={$this->downloadTask->id}]";

        if ($isRoot) {
            return Storage::disk($this->disk)->path($directoryName);
        } else {
            return $directoryName;
        }
    }

    /**
     * 清理
     *
     * @return void
     */
    private function clean()
    {
        Storage::disk($this->disk)->deleteDirectory($this->getDestinationDirectory());
        dispatch(new DeleteFile($this->getDestinationDirectory(true).'.zip'))->delay(now()->addHours(3));
    }
}
