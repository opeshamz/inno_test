<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name');
            $table->decimal('salary', 10, 2)->nullable();
            $table->string('country'); // USA | Germany

            // USA-specific fields
            $table->string('ssn')->nullable();
            $table->text('address')->nullable();

            // Germany-specific fields
            $table->text('goal')->nullable();
            $table->string('tax_id')->nullable();

            $table->timestamps();

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
