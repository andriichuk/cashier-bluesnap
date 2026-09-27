<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bluesnap_customers', function (Blueprint $table): void {
            $table->id();
            $table->morphs('billable');
            $table->string('vaulted_shopper_id')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bluesnap_customers');
    }
};
