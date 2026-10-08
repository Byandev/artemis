<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TaskManagement\Support\TicketCodes;

/**
 * Spaces carry a ticket code of their own, so a task in a list directly under
 * the space "Artemis" is ART-1 rather than the catch-all TSK-1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_spaces', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
            $table->unsignedInteger('last_ticket_number')->default(0)->after('code');
        });

        DB::table('task_spaces')->orderBy('id')->each(function (object $space): void {
            DB::table('task_spaces')->where('id', $space->id)->update([
                'code' => TicketCodes::firstFreeFor($space->workspace_id, $space->name),
            ]);
        });

        // The module has not shipped, so the few TSK tickets that exist were
        // issued before spaces had codes. Renumber them under their space's
        // code, oldest first, rather than leave a prefix nothing uses.
        DB::table('task_spaces')->orderBy('id')->each(function (object $space): void {
            $code = DB::table('task_spaces')->where('id', $space->id)->value('code');

            $tasks = DB::table('tasks')
                ->join('task_lists', 'task_lists.id', '=', 'tasks.task_list_id')
                ->where('task_lists.space_id', $space->id)
                ->whereNull('task_lists.folder_id')
                ->where('tasks.ticket_code', TicketCodes::FALLBACK)
                ->orderBy('tasks.created_at')
                ->orderBy('tasks.id')
                ->pluck('tasks.id');

            $number = (int) DB::table('tasks')
                ->where('workspace_id', $space->workspace_id)
                ->where('ticket_code', $code)
                ->max('ticket_number');

            foreach ($tasks as $taskId) {
                DB::table('tasks')->where('id', $taskId)->update(['ticket_code' => $code, 'ticket_number' => ++$number]);
            }

            DB::table('task_spaces')->where('id', $space->id)->update(['last_ticket_number' => $number]);
        });

        Schema::table('task_spaces', function (Blueprint $table) {
            $table->unique(['workspace_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('task_spaces', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'code']);
            $table->dropColumn(['code', 'last_ticket_number']);
        });
    }
};
