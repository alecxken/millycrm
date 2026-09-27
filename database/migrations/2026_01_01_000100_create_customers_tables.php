<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('individual')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('whatsapp', 40)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('country', 80)->nullable()->index();
            $table->text('passport_number')->nullable(); // encrypted cast
            $table->date('passport_expiry')->nullable()->index();
            $table->string('preferred_contact_channel', 20)->default('whatsapp');
            $table->string('source', 20)->default('walk_in')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lifecycle_stage', 20)->default('lead')->index();
            $table->boolean('marketing_consent')->default(false)->index();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable()->index();
            $table->timestamp('anonymised_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('seat_preference', 20)->nullable();
            $table->string('meal_preference', 40)->nullable();
            $table->string('budget_band', 20)->nullable()->index();
            $table->string('travel_style', 20)->nullable()->index();
            $table->json('preferred_airlines')->nullable();
            $table->json('preferred_destinations')->nullable();
            $table->text('special_needs')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 16)->default('slate');
            $table->timestamps();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'tag_id']);
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('context', 40)->default('customers');
            $table->json('filters');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customer_preferences');
        Schema::dropIfExists('customers');
    }
};
