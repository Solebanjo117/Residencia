<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NOTIFICATION_TYPES = [
        'NEW_ASSIGNMENT',
        'WINDOW_OPEN',
        'TASK_DUE_SOON',
        'WINDOW_CLOSING',
        'SUBMISSION_APPROVED',
        'SUBMISSION_REJECTED',
        'GENERAL',
    ];

    private const SCHEDULE_TYPES = [
        'WINDOW_OPEN',
        'TASK_DUE_SOON',
        'WINDOW_CLOSING',
    ];

    public function up(): void
    {
        $this->updateEnums(self::NOTIFICATION_TYPES, self::SCHEDULE_TYPES);
    }

    public function down(): void
    {
        DB::table('notifications')
            ->where('type', 'TASK_DUE_SOON')
            ->update(['type' => 'WINDOW_CLOSING']);

        DB::table('notification_schedules')
            ->where('notification_type', 'TASK_DUE_SOON')
            ->delete();

        $this->updateEnums(
            [
                'NEW_ASSIGNMENT',
                'WINDOW_OPEN',
                'WINDOW_CLOSING',
                'SUBMISSION_APPROVED',
                'SUBMISSION_REJECTED',
                'GENERAL',
            ],
            [
                'WINDOW_OPEN',
                'WINDOW_CLOSING',
            ]
        );
    }

    private function updateEnums(array $notificationTypes, array $scheduleTypes): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTables($notificationTypes, $scheduleTypes);

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE notifications MODIFY type ENUM ('.$this->quotedEnumValues($notificationTypes).') NOT NULL');
        DB::statement('ALTER TABLE notification_schedules MODIFY notification_type ENUM ('.$this->quotedEnumValues($scheduleTypes).') NOT NULL');
    }

    private function quotedEnumValues(array $values): string
    {
        return collect($values)
            ->map(fn (string $value) => "'{$value}'")
            ->implode(', ');
    }

    private function rebuildSqliteTables(array $notificationTypes, array $scheduleTypes): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::rename('notifications', 'notifications_old');
        Schema::create('notifications', function (Blueprint $table) use ($notificationTypes) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', $notificationTypes);
            $table->string('title', 160);
            $table->string('message', 500);
            $table->string('related_entity_type', 60)->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('read_at')->nullable();
        });

        DB::table('notifications_old')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row) => DB::table('notifications')->insert((array) $row));

        Schema::drop('notifications_old');

        Schema::rename('notification_schedules', 'notification_schedules_old');
        Schema::create('notification_schedules', function (Blueprint $table) use ($scheduleTypes) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('evidence_item_id')->constrained('evidence_items');
            $table->dateTime('notify_at');
            $table->enum('notification_type', $scheduleTypes);
            $table->boolean('is_sent')->default(false);
            $table->dateTime('created_at')->useCurrent();
        });

        DB::table('notification_schedules_old')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row) => DB::table('notification_schedules')->insert((array) $row));

        Schema::drop('notification_schedules_old');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
