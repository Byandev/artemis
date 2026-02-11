<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Botcake\Models\Sequence;
use Modules\Botcake\Models\SequenceMessage;

class TestFunction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-function';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sequence = Sequence::where('sequence_id', 3160615)->first()->load('page');

        $response = Http::withHeaders([
            'access-token' => $sequence->page->botcake_token,
        ])
            ->get("https://botcake.io/api/public_api/v1/pages/{$sequence->page->id}/sequences/{$sequence->sequence_id}/statistics")
            ->throw()
            ->json();

        $items = collect($response['data'])->map(function ($item) use ($sequence) {
            return [
                'sequence_id' => $sequence->id,
                'message_id' => $item['message_id'],
                'delivery' => $item['delivery'],
                'name' => $item['name'],
                'seen' => $item['seen'],
                'sent' => $item['sends'],
                'total_phone_number' => $item['total_phone_number'],
            ];
        })->toArray();

        SequenceMessage::upsert($items, ['message_id', 'sequence_id']);
    }
}
