<?php

namespace App\Services;

use App\Events\SendCourtSessionsToPusherWithQueue;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class RedisService
{
    private int $key;
    /**
     * @var string
     */
    private string $date;
    /**
     * @var string
     */
    private string $number;
    /**
     * @var string
     */
    private string $judges;
    /**
     * @var string
     */
    private string $involved;
    /**
     * @var string
     */
    private string $description;

    /**
     * @var string
     */
    private string $address;

    /**
     * @var string
     */
    private string $room;


    //public static string $prefix = 'court_session:';

    public const MY_PREFIX = 'court_session';
    public const SEARCH_PATTERN = self::MY_PREFIX . ':*';


    /**
     * RedisService constructor.
     * @param int $key
     * @param string $date
     * @param string $number
     * @param string $judges
     * @param string|null $involved
     * @param string|null $description
     * @param string|null $address
     * @param string|null $room
     */
    public function __construct(
        int $key,
        string $date,
        string $number,
        string $judges,
        string $involved = null,
        string $description = null,
        string $address = null,
        string $room = null
    ) {
        $this->key = $key;
        $this->date = $date;
        $this->number = $number;
        $this->judges = $judges;
        $this->involved = $involved;
        $this->description = $description;
        $this->address = $address;
        $this->room = $room;
    }


    public function store()
    {
        $key = self::getKey($this->date, $this->number);
        //dump($key);
        Redis::hmset($key, [
            'key'         => $this->key,
            'date'        => $this->date,
            'number'      => $this->number,
            'judge'       => $this->judges,
            'involved'    => $this->involved,
            'description' => $this->description,
            'add_address' => $this->address,
            'courtroom'   => $this->room,
        ]);
    }

    /**
     * @param $date
     * @param $number
     * @return RedisService|bool
     */
    public static function find($date, $number)
    {
        $key = self::getKey($date, $number);
        $stored = Redis::hgetall($key);
        //dd($stored);
        if (!empty($stored)) {
            return new self(
                $stored['date'],
                $stored['number'],
                $stored['judges'],
                $stored['involved'],
                $stored['description'],
                $stored['add_address'],
                $stored['room'],
            );
        }
        return false;
    }

    /**
     * @return Collection
     * @throws Exception
     */
    public static function getAll(): Collection
    {
        $frameworkPrefix = config('database.redis.options.prefix');
        // фреймворк по умолчанию подставляет префикс rozklad_zasidan_database_
        // общий префикс получается 'rozklad_zasidan_database_court_session:*'
        $keys = Redis::keys(self::SEARCH_PATTERN);
        //dd($keys);
        if (count($keys) === 0) {
            $errorMessage = "No keys with pattern - " . $frameworkPrefix . self::SEARCH_PATTERN ;
            throw new Exception($errorMessage);
        }
        $courtSessions = [];
        foreach ($keys as $key) {
            //удаляем explodom префикс фреймворка
            $courtSessions[] = Redis::hgetall(explode($frameworkPrefix, $key)[1]);
        }
        return collect($courtSessions);
    }

    public static function removeOldKeys()
    {
        dump('Remove items from redis.');
        $frameworkPrefix = config('database.redis.options.prefix');
        $keys = Redis::keys(self::SEARCH_PATTERN);
        //Redis::hdel($keys);
        //dd(Redis::keys(self::SEARCH_PATTERN));
        foreach ($keys as $key) {
            Redis::del(explode($frameworkPrefix, $key)[1]);
        }
    }

    /**
     * @param string $date
     * @param string $number
     * @return string
     */
    public static function getKey(string $date, string $number): string
    {
        return self::MY_PREFIX . ':' . $date . '_' . $number;
    }

    /**
     * @return int
     */
    public static function getCountKeys(): int
    {
        return count(Redis::keys(self::SEARCH_PATTERN));
    }

    /**
     * @param Collection $courtSessions
     */
    public static function insertToRedis(Collection $courtSessions)
    {
        dump("Insert items to redis.");
        foreach ($courtSessions as $key => $item) {
            //dump($item);
            $courtSession = new self(
                $key,
                $item['date'],
                $item['number'],
                $item['judge'],
                $item['involved'],
                $item['description'],
                $item['add_address'],
                $item['courtroom'],
            );
            $courtSession->store();
        }
    }

    /**
     * @param Collection $fetchedItems
     */
    public static function updateData(Collection $fetchedItems, Collection $itemsToPusher) {
        //dd($fetchedItems);
        try {
            //Redis::transaction(function() use ($fetchedItems, $itemsToPusher) {
                //dd($fetchedItems);
                RedisService::removeOldKeys();
                RedisService::insertToRedis($fetchedItems);

                $itemsToPusher->each(function ($item, $key) {
                    //добавляем ключ clear для первого элемента массива -
                    //нужно для того, чтобы очистить массив с элементами на фронте
                    if ($key === 0 ) {
                        $item['clear'] = '';
                    }

                    broadcast(new SendCourtSessionsToPusherWithQueue($item));
                });
            //});
        } catch (Exception $e) {
            $errorMsg = sprintf(
                'Парсинг заседаний. Ошибка во время обновления данных. %s.  Class - %s, line - %d',
                $e->getMessage(),
                __CLASS__,
                __LINE__
            );
            dd($errorMsg);
        }
    }
}
