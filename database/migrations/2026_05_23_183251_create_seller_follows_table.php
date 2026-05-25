<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_follows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('follower_user_id');
            $table->uuid('seller_user_id')->nullable();
            $table->uuid('seller_agency_id')->nullable();
            $table->timestamps();

            $table->foreign('follower_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('seller_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('seller_agency_id')->references('id')->on('agencies')->cascadeOnDelete();

            $table->index('follower_user_id');
            $table->index('seller_user_id');
            $table->index('seller_agency_id');

            // Un follower ne peut suivre qu'une fois le même seller
            $table->unique(['follower_user_id', 'seller_user_id'], 'unique_follower_seller_user');
            $table->unique(['follower_user_id', 'seller_agency_id'], 'unique_follower_seller_agency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_follows');
    }
};
