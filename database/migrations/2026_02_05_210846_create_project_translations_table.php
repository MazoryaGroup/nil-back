<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('locale', 5); // fa, en, de, fr, es, ar
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('title_1')->nullable();
            $table->text('description_1')->nullable();
            $table->string('title_2')->nullable();
            $table->text('description_2')->nullable();
            $table->string('title_3')->nullable();
            $table->text('description_3')->nullable();
            $table->string('title_4')->nullable();
            $table->text('description_4')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'locale']); // یک زبان فقط یک بار برای هر پروژه
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_translations');
    }
};
