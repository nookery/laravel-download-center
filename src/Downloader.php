<?php

namespace LaravelDownloader;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LaravelDownloader\Jobs\MakeFile;
use LaravelDownloader\Models\Task;
use Rap2hpoutre\FastExcel\FastExcel;

class Downloader
{
    /**
     * 每个Excel存放多少个sheet
     *
     * @var int
     */
    protected $document_size = 1;

    /**
     * 每个Sheet存放多少个item
     *
     * @var int
     */
    protected $sheet_size = 1000;

    /**
     * 设置属性
     *
     * @param string $attribute
     * @param string $value
     * @return $this
     */
    public function set($attribute = '', $value = '')
    {
        if (isset($this->$attribute)) {
            $this->$attribute = $value;
        }

        return $this;
    }

    /**
     * 获取下载文件
     *
     * @param Model $model
     * @param Request $request
     * @return bool|string
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\InvalidArgumentException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Writer\Exception\WriterNotOpenedException
     */
    public function export(Model $model, $filter = [])
    {
        $builder = $model->ofDownload($filter);
        $count = $builder->count();
        $path = storage_path('app/public/'.$model->getTable().'.xlsx');

        if ($count == 0) {
            (new FastExcel([]))->export($path);

            return $path;
        } else if ($count <= 20000) {
            // 数量较少时，直接输出并下载
            (new FastExcel($builder->get()))->export($path);

            return $path;
        } else {
            // 数量大时，查询是否有缓存，有则返回缓存文件路径，否则创建任务
            $task = $this->getTask($model, $filter);

            if (is_file($task->file_path)) {
                return $task->file_path;
            } else {
                return false;
            }
        }
    }

    /**
     * 根据筛选条件，返回数据库中对应的记录
     *
     * @param Model $model
     * @param array $filter
     * @return mixed
     */
    public function getTask(Model $model, $filter = [])
    {
        // 找出一个文件有效的记录
        $task = Task::where('model', serialize($model))
            ->where('query', http_build_query($filter))
            ->ofValid()
            ->orderBy('id', 'desc')
            ->first();

        if (!$task || ($task && !is_file($task->file_path))) {
            // 找出一个有效的正在进行的记录
            $task = Task::where('model', serialize($model))
                ->where('query', http_build_query($filter))
                ->ofProcessing()
                ->first();

            if (!$task) {
                // 找不到有效记录，生成并返回任务记录
                $task = new Task();
                $task->model = $model;
                $task->query = $filter;
                $task->sheet_size = $this->sheet_size;
                $task->document_size = 5;
                $task->save();
            }

            dispatch(new MakeFile($task));
        }

        return $task;
    }
}