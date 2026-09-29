<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')
                ->constrained('blogs')
                ->cascadeOnDelete();

            $table->string('locale', 5); // fa, en, de, fr, es, ar

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('title_1')->nullable();
            $table->text('description_1')->nullable();

            $table->string('title_2')->nullable();
            $table->text('description_2')->nullable();

            $table->string('title_3')->nullable();
            $table->text('description_3')->nullable();

            $table->string('title_4')->nullable();
            $table->text('description_4')->nullable();

            $table->string('title_5')->nullable();
            $table->text('description_5')->nullable();

            $table->timestamps();

            $table->unique(['blog_id', 'locale']); // هر زبان فقط یک بار برای هر مقاله
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_translations');
    }
};
