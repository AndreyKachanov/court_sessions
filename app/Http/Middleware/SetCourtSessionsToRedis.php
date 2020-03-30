<?php

namespace App\Http\Middleware;

use App\Services\RedisService;
use App\Services\CourtSessionsService;
use Carbon\Carbon;
use Closure;
use Exception;

class SetCourtSessionsToRedis
{
    private CourtSessionsService $service;

    public function __construct(CourtSessionsService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     * @throws Exception
     */
    public function handle($request, Closure $next)
    {
        $currentDay = Carbon::now();

        //dd("1");
        if (RedisService::getCountKeys() === 0) {
            $fetchedItems = $this->service->fetchItems();
            //$fetchedItems = $fetchedItems->map(function ($item, $key) {
            //    if ($key == 0) {
            //        $item['date'] = '30.03.2020 09:00';
            //        $item['judge'] = 'Иванов Петров Сидоров';
            //    }
            //    if ($key == 1) {
            //        $item['date'] = '30.03.2020 23:00';
            //        $item['judge'] = 'Чумак Чумак Чумак';
            //    }
            //    if ($key == 2) {
            //        $item['date'] = '30.03.2020 23:05';
            //        $item['judge'] = 'Качанов Качанов Качанов';
            //        $item['add_address'] = '03057, м. Київ, просп. Перемоги, 41';
            //    }
            //
            //    if ($key == 3) {
            //        $item['date'] = '31.03.2020 23:10';
            //        $item['judge'] = '5454544545455454';
            //        $item['add_address'] = '03057, м. Київ, просп. Перемоги, 41';
            //    }
            //
            //    return $item;
            //});
            //dd($fetchedItems);

            $this->service->checkFetchedItems($fetchedItems);
            //dd("stop");
            RedisService::insertToRedis($fetchedItems);
        }

        elseif ($currentDay->dayOfWeek !== 0  || $currentDay->dayOfWeek !== 6) {

        //Получаем первый элемент из редиса и сравниваем, текущий ли это день
            //Если не текущий - очщаем редис
            //Нужно для того, чтобы каждое утро были свежие данные
            //dd(RedisService::getAll());
            //dd(RedisService::getAll()->sortBy('key')->values()[0]['date']);

            //$firstItemDate = '29.03.2020 09:00';
            $firstItemDate = Carbon::parse(RedisService::getAll()->sortBy('key')->values()[0]['date']);
            //dump($firstItemDate);
            //dump(Carbon::now());
            //dd(Carbon::parse($firstItemDate)->isToday());
            if (!Carbon::parse($firstItemDate)->isToday()) {
                //dd("a");
                RedisService::removeOldKeys();

                $fetchedItems = $this->service->fetchItems();

                $this->service->checkFetchedItems($fetchedItems);
                RedisService::insertToRedis($fetchedItems);
            }

            //dd($firstItemDate);
        }

        return $next($request);
    }
}
