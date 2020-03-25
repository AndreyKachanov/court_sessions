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
        //dd("1");
        if (RedisService::getCountKeys() === 0) {
            $fetchedItems = $this->service->fetchItems();

            $this->service->checkFetchedItems($fetchedItems);
            //dd("stop");
            RedisService::insertToRedis($fetchedItems);
        } else {
            //Получаем первый элемент из редиса и сравниваем, текущий ли это день
            //Если не текущий - очщаем редис
            //Нужно для того, чтобы каждое утро были свежие данные
            //dd(RedisService::getAll());
            //dd(RedisService::getAll()->sortBy('key')->values()[0]['date']);
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
