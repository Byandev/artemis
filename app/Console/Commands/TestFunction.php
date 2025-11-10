<?php

namespace App\Console\Commands;

use App\Http\Controllers\Workspaces\ProductController;
use App\Jobs\CheckVideoCreativeStatus;
use App\Jobs\FetchPageOrders;
use App\Jobs\GenerateVideoCreative;
use App\Models\Page;
use App\Models\Product;
use App\Models\VideoCreative;
use Illuminate\Console\Command;

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
        Page::where('created_at', '<=', now()->subDays(3))
            ->get()
            ->each(function (Page $page, $i) {
                $page->update(['orders_last_synced_at' => null]);

                dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subMonth(2)->startOfMonth()->unix(), \Carbon\Carbon::now()->unix()))
                    ->delay(now()->addMinutes($i * 15));
            });
//        $path = storage_path('app/private/test.json');
//        $items = json_decode(file_get_contents($path), true);
//        $product = Product::first();
//
//        foreach ($items as $item) {
//            VideoCreative::create([
//                'product_id' => $product->id,
//                'summary' => $item['summary'],
//                'persona' => $item['persona'],
//                'prompt' => $item['prompt'],
//                'caption' => $item['post_caption'],
//            ]);
//        }
//
//        VideoCreative::whereIn('status', ['pending', 'failed'])
//            ->get()
//            ->each(function ($creative, $i) {
//                dispatch(new GenerateVideoCreative($creative))->delay(now()->addSeconds($i * 5));
//            });
//
//
//        dd("DONE");
//
//
//
//
//        dd("Done");
//
//        $page = Page::find(541830885691274);
//
//        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::parse($page->orders_last_synced_at)->unix(), \Carbon\Carbon::now()->unix()));
    }
}
