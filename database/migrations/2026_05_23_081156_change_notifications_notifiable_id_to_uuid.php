<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop l'index s'il existe
        DB::statement('DROP INDEX IF EXISTS notifications_notifiable_type_notifiable_id_index');

        // Changer notifiable_id de bigint vers uuid
        // Comme la table est vide (nouvellement créée), on peut juste alter
        DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE uuid USING notifiable_id::text::uuid');

        // Recréer l'index
        DB::statement('CREATE INDEX notifications_notifiable_type_notifiable_id_index ON notifications (notifiable_type, notifiable_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifications_notifiable_type_notifiable_id_index');
        DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE bigint USING 0');
        DB::statement('CREATE INDEX notifications_notifiable_type_notifiable_id_index ON notifications (notifiable_type, notifiable_id)');
    }
};
