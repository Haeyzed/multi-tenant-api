<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex('subject');
        });

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('subject_id', 36)->nullable()->change();
            $blueprint->index(['subject_id', 'subject_type'], 'subject');
        });
    }

    public function down(): void
    {
        $table = config('activitylog.table_name');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex('subject');
        });

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('subject_id')->nullable()->change();
            $blueprint->index(['subject_id', 'subject_type'], 'subject');
        });
    }
};
