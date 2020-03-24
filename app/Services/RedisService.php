<?php

namespace App\Services;

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

    /**
     * @var string
     */
    public static string $prefix = 'court_session:';

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
     */
    public static function getAll(): Collection
    {
        $keys = Redis::keys(self::$prefix . '*');
        $courtSessions = [];
        $frameworkPrefix = config('database.redis.options.prefix');
        foreach ($keys as $key) {
            $stored = Redis::hgetall(explode($frameworkPrefix, $key)[1]);
            $courtSessions[] = $stored;
        }
        return collect($courtSessions);
    }

    public static function removeOldKeys()
    {
        $frameworkPrefix = config('database.redis.options.prefix');
        $keys = Redis::keys(self::$prefix . '*');
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
        return self::$prefix . '_' . $date . '_' . $number;
    }

    /**
     * @return int
     */
    public static function getCountKeys(): int
    {
        return count(Redis::keys(self::$prefix . '*'));
    }

    /**
     * @param Collection $courtSessions
     */
    public static function insertToRedis(Collection $courtSessions)
    {
        dump("insert to redis");
        foreach ($courtSessions as $key => $item) {
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
}
