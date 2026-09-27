<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->string('direction', 20)->default('outbound');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->nullableMorphs('related');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('general');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->string('priority', 20)->default('normal');
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category', 20)->index();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('nps_score');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('ticket_notes');
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('interactions');
    }
};
