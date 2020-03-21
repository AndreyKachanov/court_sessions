<?php

namespace App\Http\Middleware;

use App\Services\RedisService;
use App\Services\CourtSessionsService;
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
            //dd($fetchedItems);
            $this->service->checkFetchedItems($fetchedItems);
            //dd("stop");
            RedisService::insertToRedis($fetchedItems);
        }

        return $next($request);
    }
}
