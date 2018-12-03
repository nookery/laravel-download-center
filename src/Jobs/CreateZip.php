<?php

namespace LaravelDownloader\Jobs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class CreateZip extends Job
{
    /**
     * 任务最大尝试次数。
     *
     * @var int
     */
    public $tries = 1;

    /**
     * 任务运行的超时时间。
     *
     * @var int
     */
    public $timeout = 1800;

    /**
     * 要处理的文件夹
     *
     * @var Collection
     */
    public $directory;

    public function __construct($directory = '')
    {
        $this->directory = $directory;
    }

    /**
     * 生成压缩文件
     *
     */
    public function handle()
    {
        $zipper = new \Chumper\Zipper\Zipper;
        $zipper->make($this->directory.'.zip')->add($this->directory)->close();
    }
}
