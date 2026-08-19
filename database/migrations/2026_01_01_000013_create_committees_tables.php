<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Labeled "Event Management" in the UI (renamed from "Committee") for every
        // event type — see NavLabelService.
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('committee_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();

            // Committee members are drawn from this event's pledge/contact list, not a
            // separate contact book, so we simply reference a pledge row. If that pledge
            // is deleted the membership drops too (matches prototype behaviour: a
            // removed pledger cannot remain a committee member).
            $table->foreignId('pledge_id')->constrained()->cascadeOnDelete();

            $table->string('title'); // e.g. "Chairperson", "Treasurer"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('committees');
    }
};
