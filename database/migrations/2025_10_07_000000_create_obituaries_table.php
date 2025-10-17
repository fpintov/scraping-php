<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obituaries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('cemetery');
            $table->string('deceased_name');
            $table->timestamps();
            $table->unique(['date', 'cemetery', 'deceased_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obituaries');
    }
};



