<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The standard Laravel database-notifications table — the bell (slice B3)
     * reads the authenticated user's rows through the typed endpoint. Ships
     * with the package so `vendor:publish --tag=refilament-migrations` gives
     * consumers the table the notifications bell needs.
     *
     * NOTE: keep this in sync with the workbench copy
     * (`workbench/database/migrations/2026_01_01_000003_create_notifications_table.php`)
     * — this one is the consumer artifact, that one drives the workbench app
     * and the test suite. Same table, two contexts.
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
