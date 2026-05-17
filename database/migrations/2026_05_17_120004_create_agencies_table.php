<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('logo_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('rc_number', 50)->nullable();
            $table->string('phone_whatsapp', 20)->nullable();
            $table->string('phone_call', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->string('subscription_tier', 20)->default('free');
            $table->unsignedSmallInteger('quota_active_listings')->default(10);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('status');
            $table->index('subscription_tier');
        });

        DB::statement('ALTER TABLE agencies ADD COLUMN location GEOGRAPHY(POINT, 4326)');
        DB::statement('CREATE INDEX agencies_location_gix ON agencies USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
