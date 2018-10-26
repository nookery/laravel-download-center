<?php

namespace DownloadCenter\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Rap2hpoutre\FastExcel\FastExcel;

class Downloader extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'downloader';
    }
}