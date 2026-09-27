<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bluesnap_transactions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('billable');
            $table->string('bluesnap_id')->unique();
            $table->string('subscription_bluesnap_id')->nullable()->index();
            $table->string('type')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('amount')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bluesnap_transactions');
    }
};
