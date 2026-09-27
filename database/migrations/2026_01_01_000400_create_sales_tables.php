<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedSmallInteger('travellers_adults')->default(1);
            $table->unsignedSmallInteger('travellers_children')->default(0);
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('trip_type', 20)->nullable();
            $table->string('channel', 20)->default('walk_in')->index();
            $table->string('status', 20)->default('new')->index();
            $table->string('lost_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_value', 12, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(10);
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('discount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('description');
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('markup', 5, 2)->default(0); // percentage
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destination');
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('status', 20)->default('confirmed')->index();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('enquiries');
    }
};
