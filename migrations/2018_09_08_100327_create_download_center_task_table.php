<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDownloadCenterTaskTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('download_center_task', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('hash', 32)->comment('哈希值，哈希值相同则认为可以作为缓存');
            $table->text('model')->comment('序列化后的Model');
            $table->enum('status', ['ready', 'processing', 'finished'])->comment('当前任务的处理状态');
            $table->text('query')->comment('查询条件，以此从Model中筛选数据');
            $table->integer('sheet_size')->comment('单个sheet中存放的记录的条数');
            $table->integer('document_size')->comment('单个Excel文件中存放的sheet的个数');
            $table->integer('offset')->comment('偏移量，即当前状态从第多少个开始取数据');
            $table->integer('total')->comment('要取出的数据总量');
            $table->string('file_path', 200)->comment('处理完毕的可供下载的文件路径');
            $table->timestamps();

            // 哈希值和更新时间的索引，以此判断缓存是否有效
            $table->index(['hash', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('download_center_task');
    }
}
