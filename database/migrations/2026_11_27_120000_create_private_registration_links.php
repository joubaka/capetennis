<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('private_registration_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_event_id')->index();
            $table->unsignedBigInteger('player_id')->index();
            $table->unsignedBigInteger('issued_by');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('order_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_registration_links');
    }
};
