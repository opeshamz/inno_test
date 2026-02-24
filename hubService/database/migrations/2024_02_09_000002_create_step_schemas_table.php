<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('step_id', 50)->index();   // dashboard, employees, documentation
            $table->string('country', 10)->index();   // USA, Germany, etc.
            $table->string('title', 100);
            $table->json('widgets');                  // array of widget configs
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['step_id', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_schemas');
    }
};
