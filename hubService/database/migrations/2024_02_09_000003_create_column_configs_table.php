<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('column_configs', function (Blueprint $table) {
            $table->id();
            $table->string('country', 10)->unique()->index();
            $table->json('columns');   // array of column definition objects
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('column_configs');
    }
};
