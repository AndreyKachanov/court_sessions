<?php

namespace App\Http\Controllers;

use App\Services\CourtSessionsService;
use App\Services\RedisService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * @var CourtSessionsService
     */
    private CourtSessionsService $service;

    public function __construct(CourtSessionsService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $fields = $this->service->getFields();
        //dd($fields);
        //$items = $this->service->getCurrentDayItemsFromRedis();
        $items = $this->service->getCurrentTimeItemsFromRedis();
        //dd($items);
        return view('court_sessions.index', [
            'fields' => collect($fields)->toJson(),
            'items' => $items->toJson()
        ]);
    }

    public function setRoomNumber(Request $request)
    {
        if (isset($request->key)) {
            $keyFromRequest = $request->key;
            $roomFromRequest = $request->Зал;

            $itemsFromRedis = $this->service->getItemsFromRedis();
            $itemsFromRedis->transform(function ($item) use ($keyFromRequest, $roomFromRequest) {
                $item['courtroom'] = ($item['key'] === $keyFromRequest) ? $roomFromRequest : $item['courtroom'];
                return $item;
            });

            RedisService::insertToRedis($itemsFromRedis);
        }
    }
}
