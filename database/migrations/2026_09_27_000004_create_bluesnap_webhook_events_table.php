<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bluesnap_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->char('event_key', 64)->unique();
            $table->string('transaction_type')->index();
            $table->string('reference_number')->nullable()->index();
            $table->string('subscription_bluesnap_id')->nullable()->index();
            $table->string('status')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bluesnap_webhook_events');
    }
};
