<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->timestamp('subscription_start')->nullable()->after('subscription_tier');
            $table->timestamp('subscription_end')->nullable()->after('subscription_start');
        });
    }
    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['subscription_start', 'subscription_end']);
        });
    }
};
