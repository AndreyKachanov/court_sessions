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
     * @return bool
     * @throws Exception
     */
    public function handle()
    {
        $currentDay = Carbon::now();

        if ($currentDay->dayOfWeek === 6 || $currentDay->dayOfWeek === 0) {
            $this->error("dayOfWeek === 6 or dayOfWeek === 0");
            return false;
        }

        $fetchedItems = $this->service->fetchItems();
        $this->service->checkFetchedItems($fetchedItems);
        //dd($fetchedItems->slice(0, 4));

        //$fetchedItems = $fetchedItems->map(function ($item, $key) {
        //    if ($key == 0) {
        //        $item['date'] = '30.03.2020 09:00';
        //        $item['judge'] = 'Петров Сидоров';
        //    }
        //    if ($key == 1) {
        //        $item['date'] = '30.03.2020 23:00';
        //        $item['judge'] = 'saasfasfsadff sa asfsadfsadf ';
        //    }
        //    if ($key == 2) {
        //        $item['date'] = '30.03.2020 23:05';
        //        $item['judge'] = '4454 Качввыаанов Качанов';
        //        $item['add_address'] = '03057, м. Київ, просп. Перемоги, 41';
        //    }
        //
        //    if ($key == 3) {
        //        $item['date'] = '30.03.2020 23:50';
        //        $item['judge'] = '45';
        //        $item['add_address'] = '03057, м. Київ, просп. Перемоги, 41';
        //    }
        //
        //    return $item;
        //});

        //dd($fetchedItems->slice(0, 4));
        $currentTimeRedisItems = $this->service->getCurrentTimeItemsFromRedis()
            ->map(function ($item) {
            return [
                'date'        => $item['Час'],
                'judge'       => $item['Склад суду'],
                'number'      => $item['Номер справи'],
                'involved'    => $item['Сторони по справі'],
                'description' => $item['Суть позову'],
                'courtroom'   => $item['Зал'],
                'key'         => $item['key'],
            ];
        });

        //Переносит номер зала из редиса в массив который мы будем записывать в редис
        //нужно для того, чтобы сохранить номера залов, которые ввел пользователь, и которые
        //сохранились в редис
        if ($currentTimeRedisItems->count() > 0) {
            $fetchedItems = $fetchedItems->map(function ($item) use ($currentTimeRedisItems) {
                foreach ($currentTimeRedisItems as $rItem) {
                    if ($item['date'] === $rItem['date'] && $item['number'] === $rItem['number']) {
                        $item['courtroom'] = $rItem['courtroom'];
                    }
                }
                return $item;
            });
        }

        //$currentTimeRedisItems = collect(
        //    [
        //        [
        //            "date" => "29.03.2020 09:00",
        //            "judge" => "6666654444444444444",
        //            "number" => "757/32971/19-к",
        //            "involved" => "Державний обвинувач: Спеціалізована антикорупційна прокуратура Офісу Генерального прокурора, обвинувачений: Білик Ганна Олексіївна, захисник: Вилков Сергій Валентинович, захисник: Головненко Дмитро Олександрович, захисник: Білик Олександр Миколайович, захисник: Голосій Ростислав Анатолійович",
        //            "description" => "Прийняття пропозиції, обіцянки або одержання неправомірної вигоди службовою особою",
        //            "courtroom" => "66",
        //            "key" => "1"
        //        ],
        //        [
        //            "date" => "29.03.2020 09:10",
        //            "judge" => "Луценко Снигур Дубинко",
        //            "number" => "522/10468/19",
        //            "involved" => "захисник: Вилков Сергій Валентинович, захисник: Головненко Дмитро Олександрович, захисник: Білик Олександр Миколайович, захисник: Голосій Ростислав Анатолійович",
        //            "description" => "Прийняття пропозиції, обіцянки або одержання неправомірної вигоди службовою особою",
        //            "courtroom" => "77",
        //            "key" => "2"
        //        ],
        //        [
        //            "date" => "29.03.2020 10:00",
        //            "judge" => "Танасевич О.В. Танасевич О.В. Танасевич О.В.",
        //            "number" => "444/1010/21",
        //            "involved" => "Державний захисник: Вилков Сергій Валентинович, захисник: Головненко Дмитро Олександрович, захисник: Білик Олександр Миколайович, захисник: Голосій Ростислав Анатолійович",
        //            "description" => "safd asdf sadf hkjsahdf kashdfkjhsakdjf sdalf kjalskdjf lksajd flыдвлао длыво адою",
        //            "courtroom" => "777",
        //            "key" => "3"
        //        ],
        //    ]
        //);

        //dd($currentTimeRedisItems);

        //$currentTimeApiItems = collect(
        //    [
        //        [
        //            "date" => "29.03.2020 09:00",
        //            "judge" => "6666654444444444444",
        //            "number" => "757/32971/19-к",
        //            "involved" => "Державний обвинувач: Спеціалізована антикорупційна прокуратура Офісу Генерального прокурора, обвинувачений: Білик Ганна Олексіївна, захисник: Вилков Сергій Валентинович, захисник: Головненко Дмитро Олександрович, захисник: Білик Олександр Миколайович, захисник: Голосій Ростислав Анатолійович",
        //            "description" => "Прийняття пропозиції, обіцянки або одержання неправомірної вигоди службовою особою",
        //            "courtroom" => "66",
        //            "key" => "1"
        //        ],
        //        [
        //            "date" => "29.03.2020 09:10",
        //            "judge" => "Луценко Снигур Дубинко",
        //            "number" => "522/10468/19",
        //            "involved" => "захисник: Вилков Сергій Валентинович, захисник: Головненко Дмитро Олександрович, захисник: Білик Олександр Миколайович, захисник: Голосій Ростислав Анатолійович",
        //            "description" => "Прийняття пропозиції, обіцянки або одержання неправомірної вигоди службовою особою",
        //            "courtroom" => "77",
        //            "key" => "2"
        //        ],
        //        [
        //            "date" => "29.03.2020 10:00",
        //            "judge" => "Танасевич О.В. Танасевич О.В. Танасевич О.В.",
        //            "number" => "444/1010/21",
        //            "involved" => "Державний захисник: Вилков Сергій Валентинович, захисник: Головненко Дмитро Олександрович, захисник: Білик Олександр Миколайович, захисник: Голосій Ростислав Анатолійович",
        //            "description" => "safd asdf sadf hkjsahdf kashdfkjhsakdjf sdalf kjalskdjf lksajd flыдвлао длыво адою",
        //            "courtroom" => "777",
        //            "key" => "3"
        //        ],
        //    ]
        //);

        $currentTimeApiItems = $this->service->getMoreCurrentTimeItems($fetchedItems)
            ->map(function ($item, $key) {
                return [
                    'date'        => $item['date'],
                    'judge'       => $item['judge'],
                    'number'      => $item['number'],
                    'involved'    => $item['involved'],
                    'description' => $item['description'],
                    'courtroom'   => $item['courtroom'],
                    'key'         => (string)$key
                ];
            })->values();

        $countCurrentTimeApiItems = $currentTimeApiItems->count();
        $countCurrentTimeRedisItems = $currentTimeRedisItems->count();

        echo "-----------------------------------------------" . PHP_EOL;
        $this->info("countCurrentTimeApiItems = " . $countCurrentTimeApiItems);
        $this->info("countCurrentTimeRedisItems = " . $countCurrentTimeRedisItems);

        // размер массивов не совпадает, или если массив из апи не идентичен массиву из редиса
        if (
            ($countCurrentTimeApiItems !== $countCurrentTimeRedisItems) ||
            !$this->isEqual($currentTimeApiItems, $currentTimeRedisItems)
        ) {
            dump(Carbon::now()->format('d-m-Y H:i:s'));
            $this->info("Update redis.");
            echo "currentTimeApiItems = ";
            dump($currentTimeApiItems->toArray());
            echo "currentTimeRedisItems = ";
            dump($currentTimeRedisItems->toArray());

            $dataToPusher = $this->service->convertItems($currentTimeApiItems);
            RedisService::updateData($fetchedItems, $dataToPusher);
            $this->info("Send data to pusher");
            echo "-----------------------------------------------" . PHP_EOL;
            return true;
        } else {
            $this->info("Данные одинаковы");
            echo "-----------------------------------------------" . PHP_EOL;
            return false;
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
        //dump($itemsFromApi);
        //dd($itemsFromRedis);
        //Сравниваем 2 массива. Если они разные - записываем в редис новые данные за все дни
        // + отправляем данные в pusher за текущий день
        /**@var */
        foreach ($itemsFromApi as $key => $item) {
            //dump($item);
            //$item['judge'] = 'zalupa';
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
