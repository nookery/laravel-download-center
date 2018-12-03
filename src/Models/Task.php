<?php

namespace LaravelDownloader\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Task extends Model
{
    protected $table = 'downloader_task';

    /**
     * 设定model时，序列化后再存储
     *
     * @param  string  $value
     * @return void
     */
    public function setModelAttribute($value)
    {
        $this->attributes['model'] = serialize($value);
    }

    /**
     * 设定query时，序列化后再存储
     *
     * @param  string  $value
     * @return void
     */
    public function setQueryAttribute($value)
    {
        $this->attributes['query'] = http_build_query($value);
    }

    public function scopeOfValid($query)
    {
        return $query->where('updated_at', '>', Carbon::now()->subHours(2)->toDateTimeString())
            ->where('status', 'finished')
            ->whereNotNull('file_path');
    }

    public function scopeOfProcessing($query)
    {
        return $query->where('updated_at', '>', Carbon::now()->subMinutes(2)->toDateTimeString())
            ->where('status', 'processing');
    }
}
