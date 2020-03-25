<?php

namespace App\Console\Commands;

use App\Services\CourtSessionsService;
use App\Services\RedisService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

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
     * @throws Exception
     */
    public function handle()
    {
        $currentDay = Carbon::now();

        if ($currentDay->dayOfWeek === 6 || $currentDay->dayOfWeek === 0) {
            $this->error("dayOfWeek === 6 or dayOfWeek === 0");
            return false;
        } else {
            $fetchedItems = $this->service->fetchItems();
            $this->service->checkFetchedItems($fetchedItems);
            //dd($fetchedItems[0]);

            $itemsFromApi = $this->service->getCurrentDayItems($fetchedItems)
                ->map(function ($item) {
                    return [
                        'date' => $item['date'],
                        'judge' => $item['judge'],
                        'number' => $item['number'],
                        'involved' => $item['involved'],
                        'description' => $item['description']
                    ];
                })
                ->values();

            //dump($itemsFromApi->first());

            $itemsFromRedis = $this->service->getCurrentDayItemsFromRedis()
                ->map(function ($item) {
                    return [
                        'date' => $item['Час'],
                        'judge' => $item['Склад суду'],
                        'number' => $item['Номер справи'],
                        'involved' => $item['Сторони по справі'],
                        'description' => $item['Суть позову']
                    ];
                });

            //dd($itemsFromRedis->first());

            $countFromApi = $itemsFromApi->count();
            $countFromRedis = $itemsFromRedis->count();

            if ($countFromApi === 0 || $countFromRedis === 0) {
                $this->error("Day of week != 6 or != 0. Count itemsFromApi or itemsFromRedis = 0 !!!");
                return false;
            } else {

                if (($countFromApi !== $countFromRedis) || $this->isEqual($itemsFromApi, $itemsFromRedis) === false) {
                    //dd('good. update redis and send to pusher');
                    $this->info("Данные разнятся. Обновляем!!!");

                    RedisService::updateData($fetchedItems);

                    //RedisService::insertToRedis($fetchedItems);

                    //send to pusher
                    return true;
                } else {
                    $this->info("Данные одинаковы");
                    return true;
                }
            }
        }
    }

    /**
     * @param Collection $itemsFromApi
     * @param Collection $itemsFromRedis
     * @return bool
     * @throws Exception
     */
    private function isEqual(Collection $itemsFromApi, Collection $itemsFromRedis): bool
    {
        //Сравниваем 2 массива. Если они разные - записываем в редис новые данные за все дни
        // + отправляем данные в pusher за текущий день
        /**@var */
        foreach ($itemsFromApi as $key => $item) {
            //dd($item);
            $item['judge'] = 'zalupa';
            //unset($item['date']);
            //dump($item);
            //dd($itemsFromRedis[$key]);

            //dd(collect($item)->diff(collect($itemsFromRedis[$key]))->count());

            if (count($item) !== count($itemsFromRedis[$key])) {
                throw new Exception('count($item) !== count($itemsFromRedis[$key])');
                //return false;
                //dd(1);
            }

            //если в массивах разные данные
            if (collect($item)->diff(collect($itemsFromRedis[$key]))->count() !== 0 ) {
                return false;
                //dd(2);
            }
        }

        return true;
    }
}
