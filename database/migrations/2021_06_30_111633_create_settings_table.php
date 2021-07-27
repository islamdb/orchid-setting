<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')
                ->unique()
                ->index()
                ->primary();
            $table->string('type')
                ->default('input-text');
            $table->string('group')
                ->default('System');
            $table->bigInteger('position')
                ->unsigned()
                ->default(1);
            $table->string('name');
            $table->text('description')
                ->nullable();
            $table->boolean('is_array_value')
                ->default(false);
            $table->longText('value')
                ->nullable();
            $table->longText('options')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
