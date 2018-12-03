<?php

namespace LaravelDownloader\Jobs;

use Illuminate\Support\Facades\Storage;

class DeleteDirectory extends Job
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

	protected $directory;

	public function __construct($directory = '') {
		$this->directory = $directory;
	}

	/**
	 * 执行任务
	 *
	 * @return string
	 */
	public function handle()
	{
        Storage::deleteDirectory($this->directory);
	}
}
