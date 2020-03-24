<?php

namespace App\Console\Commands;

use App\Services\CourtSessionsService;
use Illuminate\Console\Command;

class CourtSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse:sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse court sessions';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    private $service;

    public function __construct(CourtSessionsService $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $fetchedItems = $this->service->fetchItems();
        $this->service->checkFetchedItems($fetchedItems);
        //dd($fetchedItems[0]);

        $itemsFromApi = $this->service->getCurrentDayItems($fetchedItems);
        dump($itemsFromApi->first());
        //dump($fetchedItems[2]);
        //$items = $this->service->getCurrentTimeItemsFromRedis();
        $itemsFromRedis = $this->service->getCurrentDayItemsFromRedis();
        dd($itemsFromRedis->first());


    }
}
