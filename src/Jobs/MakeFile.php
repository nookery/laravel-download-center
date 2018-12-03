<?php

namespace LaravelDownloader\Jobs;

use Illuminate\Support\Collection;
use LaravelDownloader\Models\Task;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Illuminate\Support\Facades\Storage;

class MakeFile extends Job
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
     * 最大可取多少个item（取决于内存限制）
     *
     * @var int
     */
	public $limit = 0;

    /**
     * 要处理的任务，即数据中的一条记录
     *
     * @var Collection
     */
	public $downloadTask;

	public function __construct(Task $downloadTask)
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
        $this->printMemoryUsage('开始处理时');

	    $lockResult = Task::where('id', $this->downloadTask->id)
            ->where('status', 'ready')
            ->update(['status' => 'processing']);
	    if (!$lockResult) {
	        return false;
        } else {
	        $this->downloadTask = Task::find($this->downloadTask->id);
        }

        // 创建临时文件夹
        if (!Storage::disk($this->disk)->exists($this->getDestinationDirectory())) {
            Storage::disk($this->disk)->makeDirectory($this->getDestinationDirectory());
        }

        // 一个Excel文件中有多个sheet，一个sheet中有多个item
        // 将所有获取的数据放到$sheets中，最后生成Excel文件
        $sheets = new SheetCollection();
        $items = new Collection();

        do {
            $newItems = $this->getBatchItems();
            $this->printLog('newItems的大小：'.$newItems->count());
            $items = $items->merge($newItems->all());
            if ($items->count() >= $this->downloadTask->sheet_size) {
                $sheets->push($items);
                $items = new Collection();
            }

            $this->printMemoryUsage('获取新批次');
        } while ($this->isMemoryEnough() && $newItems->count() > 0);

        if ($items->count() > 0) {
            $sheets->push($items);
        }

        // 生成Excel文件，生成完毕后会释放大量内存
        (new FastExcel($sheets))->export($this->getDestinationDirectory(true).'/'.uniqid().'.xlsx');

        if ($this->downloadTask->offset < $this->downloadTask->total) {
            // 如果任务没有处理完，等待下次处理
            $this->printMemoryUsage('本次处理结束，开始下一个Job');
            $this->downloadTask->status = 'ready';
            $this->downloadTask->save();
            dispatch(new self(Task::find($this->downloadTask->id)));
        } else {
            // 任务处理完，则创建zip文件，清理临时目录
            dispatch(new CreateZip($this->getDestinationDirectory(true)));
            $this->downloadTask->file_path = $this->getDestinationDirectory(true).'.zip';
            $this->downloadTask->status = 'finished';
            $this->downloadTask->save();

            $this->clean();
        }
	}

    /**
     * 获取少量的item（多次执行，以防止内存不足）
     *
     * @return mixed
     */
	private function getBatchItems()
    {
        // 组建Builder
        $builder = (unserialize($this->downloadTask->model))::ofDownload($this->downloadTask->query);

        // 更新总量
        if (!$this->downloadTask->total) {
            $this->downloadTask->total = $builder->count();
            $this->downloadTask->save();
        }

        // 确定offset和limit
        if (!$this->limit) {
            $this->setLimit();
        }

        $builder->offset($this->downloadTask->offset)
            ->limit($this->limit);

        // 量大时，单次不能完成，记录偏移量
        $this->downloadTask->offset = $this->downloadTask->offset + $this->limit;
        $this->downloadTask->save();

        return $builder->get();
    }

    /**
     * 确定limit的值
     *
     */
    private function setLimit()
    {
        $memory = memory_get_usage();
        $item = (unserialize($this->downloadTask->model))::ofDownload($this->downloadTask->query)->first();
        $itemMemory = memory_get_usage() - $memory;

        // $this->printLog('单个item占用的内存是：'.$itemMemory);
        $max = str_replace('M', '000000', ini_get('memory_limit'));

        $this->limit = floor($max/$itemMemory/10);

        // $this->printLog('limit的值是：'.$this->limit);
        unset($item);
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
        // Storage::disk($this->disk)->deleteDirectory($this->getDestinationDirectory());
        dispatch(new DeleteFile($this->getDestinationDirectory(true).'.zip'))->delay(now()->addHours(3));
    }

    public function printLog($message = '')
    {
        echo '['.date('Y-m-d H:i:s').']->'.$message."\r\n";
    }

    public function printMemoryUsage($prefix = '')
    {
        $this->printLog($prefix.'内存占用：'.(memory_get_usage()/1000000).'M（配置的最大值：'.ini_get('memory_limit').')');
    }

    /**
     * 内存是否充足
     *
     * @return bool
     */
    public function isMemoryEnough()
    {
        $max = ini_get('memory_limit');
        $max = str_replace('M', '000000', $max);
        $max = str_replace('m', '000000', $max);

        $usage = memory_get_usage();

        // $this->printLog($max);

        // $this->printLog($usage);

        return ($max - $usage) / $max > 0.3;
    }
}
