<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_channel_whatsapp_accounts', function (Blueprint $table) {
            $table->id();

            // Pflichtfeld: Team-Zugehörigkeit (FK später ergänzt)
            $table->unsignedBigInteger('team_id')->index();

            // Wer hat das Konto erstellt
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

            // Optional: individueller Benutzer (für private Konten)
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('phone_number')->unique();
            $table->string('phone_number_id')->unique(); // Meta Phone Number ID (Pflicht, da jede Nummer ihre eigene ID hat)
            $table->string('name')->nullable();
            $table->string('business_id')->nullable(); // Meta Business Account ID (optional, kann geteilt werden)
            $table->text('api_token')->nullable(); // WhatsApp Business API Token (optional, kann geteilt werden)
            $table->string('webhook_token', 40)->unique()->nullable();
            $table->string('webhook_verify_token', 40)->nullable();

            $table->string('ownership_type')->default('team'); // 'team' oder 'user'
            $table->string('sender_type')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();

            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // FK-Constraints nur hinzufügen, wenn Tabellen existieren
        if (Schema::hasTable('teams')) {
            Schema::table('comms_channel_whatsapp_accounts', function (Blueprint $table) {
                $table->foreign('team_id')
                      ->references('id')->on('teams')
                      ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('comms_channel_whatsapp_accounts', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')->on('users')
                      ->nullOnDelete();

                $table->foreign('created_by_user_id')
                      ->references('id')->on('users')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_channel_whatsapp_accounts');
    }
};

