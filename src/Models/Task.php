<?php

namespace LaravelDownloader\Models;

use DownloadCenter\Jobs\DownloadJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Task extends Model
{
    protected $table = 'downloader_task';

    /**
     * 根据筛选条件，返回数据库中对应的记录
     *
     * @param Model $model
     * @param array $filter
     * @return mixed
     */
    public static function getTask(Model $model, $filter = [])
    {
        // 找出一个文件有效的记录
        $task = self::where('hash', self::getTaskHash($model, $filter))
            ->where('updated_at', '>', Carbon::now()->subHours(2)->toDateTimeString())
            ->where('status', 'finished')
            ->whereNotNull('file_path')
            ->first();

        if (!$task || ($task && !is_file($task->file_path))) {
            // 找出一个有效的正在进行的记录
            $task = self::where('hash', self::getTaskHash($model, $filter))
                ->where('updated_at', '>', Carbon::now()->subMinutes(5)->toDateTimeString())
                ->where('status', 'processing')
                ->first();

            if (!$task) {
                // 找不到有效记录，生成并返回任务记录
                $taskId = self::insertGetId([
                    'model' => serialize($model),
                    'sheet_size' => 10000,
                    'document_size' => 5,
                    'query' => http_build_query($filter),
                    'hash' => self::getTaskHash($model, $filter)
                ]);
                $task = self::find($taskId);
            }

            dispatch(new DownloadJob($task));
        }

        return $task;
    }

    /**
     * 返回任务的hash值
     *
     * @param Model $model
     * @param array $filter
     * @return string
     */
    private static function getTaskHash(Model $model, $filter = [])
    {
        return md5(serialize($model).http_build_query($filter));
    }
}
