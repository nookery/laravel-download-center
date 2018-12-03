<?php

namespace LaravelDownloader\Jobs;

use Illuminate\Support\Facades\Storage;

class DeleteFile extends Job
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
     * 要删除的文件的路径
     *
     */
	public $file;

	public function __construct($file = '')
    {
		$this->file = $file;
	}

	/**
	 * 执行任务
	 *
	 * @return string
	 */
	public function handle()
	{
        Storage::delete($this->file);
	}
}
