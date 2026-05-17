<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('vehicle_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'vehicle_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->index('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
