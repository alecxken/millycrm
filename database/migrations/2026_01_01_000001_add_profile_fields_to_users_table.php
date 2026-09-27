<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('email');
            $table->string('phone')->nullable()->after('job_title');
            $table->string('avatar_color', 16)->default('teal')->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'phone', 'avatar_color', 'is_active']);
        });
    }
};
