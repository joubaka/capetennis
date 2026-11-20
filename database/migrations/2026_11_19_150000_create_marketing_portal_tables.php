<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('academy');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('town')->nullable();
            $table->unsignedSmallInteger('courts')->nullable();
            $table->string('status', 30)->default('prospect');
            $table->string('preference', 20)->default('none');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->index('academy');
        });
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subject')->nullable();
            $table->mediumText('email_body');
            $table->mediumText('whatsapp_body');
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_contacts');
    }
};
