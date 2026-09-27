<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bluesnap_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('billable');
            $table->string('type');
            $table->string('bluesnap_id')->unique();
            $table->string('plan_id')->index();
            $table->string('status')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 3)->nullable();
            $table->string('recurring_amount')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['billable_type', 'billable_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bluesnap_subscriptions');
    }
};
