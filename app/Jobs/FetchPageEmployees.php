<?php

namespace App\Jobs;

use App\Models\CustomerServiceRepresentative;
use App\Models\Page;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchPageEmployees implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Page $page)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$this->page->shop_id.'/users?api_key='.$this->page->pos_token)
            ->throw();

        $response = $response->json();

        $data = $response['data'];

        $employees = collect($data)
            ->map(function ($data) {
                return $data['user'];
            })->filter(function ($value, $key) {
                return isset($value['fb_id']);
            })
            ->map(function ($data) {
                return [
                    'id' => $data['fb_id'],
                    'uuid' => $data['id'],
                    'name' => $data['name'],
                    'email' => $data['email'],
                ];
            })
            ->toArray();

        $employeeIds = collect($employees)->map(fn ($employee) => $employee['id'])->toArray();

        CustomerServiceRepresentative::upsert($employees, ['id'], ['uuid', 'name', 'email']);

        $this->page->customerServiceRepresentatives()->sync($employeeIds);
    }
}
