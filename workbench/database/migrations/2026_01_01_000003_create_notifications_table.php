<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The standard Laravel database-notifications table — the bell (slice B3)
     * reads the authenticated user's rows through the typed endpoint.
     *
     * NOTE: keep in sync with the package migration
     * (`database/migrations/2026_01_01_000001_create_refilament_notifications_table.php`)
     * — this workbench copy drives the demo app and the test suite; the
     * package copy is what ships to consumers via refilament-migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
