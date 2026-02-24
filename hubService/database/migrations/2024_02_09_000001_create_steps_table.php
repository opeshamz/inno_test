<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steps', function (Blueprint $table) {
            $table->id();
            $table->string('country', 10)->index();   // USA, Germany, etc.
            $table->string('step_id', 50);            // dashboard, employees, documentation
            $table->string('label', 100);
            $table->string('icon', 50)->default('circle');
            $table->string('path', 100);
            $table->unsignedSmallInteger('order')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['country', 'step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steps');
    }
};
