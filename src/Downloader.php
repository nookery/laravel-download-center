<?php

namespace DownloadCenter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Rap2hpoutre\FastExcel\FastExcel;

class Downloader
{

    protected $document_size = 1;

    public function document_size($size)
    {
        $this->document_size = $size;

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

        if ($count <= 20000) {
            // 数量较少时，直接输出并下载
            $path = storage_path('app/public/'.$model->getTable().'.xlsx');
            (new FastExcel($builder->get()))->export($path);

            return $path;
        } else {
            // 数量大时，查询是否有缓存，有则返回缓存文件路径，否则创建任务
            $task = \DownloadCenter\Models\Task::getTask($model, $filter);

            if (is_file($task->file_path)) {
                return $task->file_path;
            } else {
                return false;
            }
        }
    }
}