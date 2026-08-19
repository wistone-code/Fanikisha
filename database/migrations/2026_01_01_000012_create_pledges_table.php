<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Called "Pledges" in the UI for most event types, "Contribution" for
        // Graduation/Baptism, and "Condolences" for Funeral — see NavLabelService.
        // The underlying data shape never changes, only the label.
        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Always stored normalized to +255 (Tanzania) via PhoneNumberService so
            // wa.me / sms: links always resolve correctly. See PledgeObserver.
            $table->string('phone')->nullable();

            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);

            // Set the first time an admin activates this pledge's invitation link
            // (only possible once amount <= paid). Presence of a token = "Active".
            $table->string('invite_token', 40)->nullable()->unique();

            $table->timestamps();

            $table->index(['event_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
