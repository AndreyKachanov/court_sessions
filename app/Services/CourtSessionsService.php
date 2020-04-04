<?php


namespace App\Services;

use App\Events\SendCourtSessionsToPusherWithQueue;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class CourtSessionsService
 * @package App\Services
 */
class CourtSessionsService
{
    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var string
     */
    private string $needAddress;

    /**
     * CourtSessionsService constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->needAddress = trim(config('court_sessions.need_address'));
    }

    /**
     * @return Collection
     */
    public function fetchItems(): Collection
    {
        //dump("get items from court.gov.ua");
        $url = 'https://hcac.court.gov.ua/new.php';
        $method = 'POST';
        $headers = [
            'Cookie'           => 'PHPSESSID=qmvdf773bcv16tlqpu3p0148k7',
            'Origin'           => 'https://hcac.court.gov.ua',
            'Accept-Encoding'  => 'gzip, deflate, br',
            'Accept-Language'  => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'User-Agent'       => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Cache-Control'    => 'max-age=0',
            'X-Requested-With' => 'XMLHttpRequest',
            'Connection'       => 'keep-alive',
            'Referer'          => 'https://hcac.court.gov.ua/hcac/gromadyanam/hcac'
        ];

        $formParams = [
            'q_court_id' => '4910'
        ];

        $data = $this->getResponseByGuzzleClient($method, $url, $headers, $formParams);
        return collect($data);
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        $arr = [];
        $columns = config('court_sessions.columns');
        foreach ($columns as $value) {
            $arr[] = [
                'key'      => $value['name'],
                'sortable' => $value['sortable']
            ];
        }

        return $arr;
    }

    /**
     * @return Collection
     * @throws Exception
     */
    public function getCurrentDayItemsFromRedis(): Collection
    {
        $itemsFromRedis = RedisService::getAll()->sortBy('key')->values();
        $courtSessions = $this->getCurrentDayItems($itemsFromRedis);
        return $this->convertItems($courtSessions);
    }

    /**
     * @return Collection
     */
    public function getCurrentTimeItemsFromRedis(): Collection
    {
        try {
            $itemsFromRedis = RedisService::getAll()->sortBy('key')->values();
            //dump($itemsFromRedis);
        } catch (Exception $e) {
            $errorMsg = sprintf(
                'Error get all items from redis. %s.  Class - %s, line - %d',
                $e->getMessage(),
                __CLASS__,
                __LINE__
            );
            dd($errorMsg);
        }

        $courtSessions = $this->getMoreCurrentTimeItems($itemsFromRedis);
        return $this->convertItems($courtSessions);
    }

    /**
     * @return Collection
     */
    public function getItemsFromRedis(): Collection
    {
        try {
            $items = RedisService::getAll()->sortBy('key')->values();
        } catch (Exception $e) {
            $errorMsg = sprintf(
                'Error get all items from redis. %s.  Class - %s, line - %d',
                $e->getMessage(),
                __CLASS__,
                __LINE__
            );
            dd($errorMsg);
        }

        return $items;
    }

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function getCurrentDayItems(Collection $collection): Collection
    {
        $collection = $collection->filter(function ($item, $key) {
            //dd($item);
            if (!isset($item['date'])) {
                dd("No key 'date' in this key ->> " . $key);
            }
            $isToday = Carbon::parse($item['date'])->isToday();
            $needAddress = ($item['add_address'] === $this->needAddress);
            return $isToday && $needAddress;
        });
        return $collection;
    }

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function getFirstMondayItems(Collection $collection): Collection
    {
        $firstMonday = Carbon::now()->modify('next monday');
        return $collection->filter(function ($item, $key) use ($firstMonday) {
            $itemDate = $item['date'];
            if (!isset($itemDate)) {
                dd("No key 'date' in this key ->> " . $key);
            }

            $needAddress = ($item['add_address'] === $this->needAddress);
            $isFirstMonday = Carbon::parse($itemDate)->diffInDays($firstMonday) == 0;

            return $needAddress && $isFirstMonday;
        });
    }

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function getMoreCurrentTimeItems(Collection $collection): Collection
    {
        $collection = $collection = $collection->filter(function ($item, $key) {
            if (!isset($item['date'])) {
                dd("No key 'date' in this key ->> " . $key);
            }
            $dateInCollection = Carbon::parse($item['date']);

            $isToday = Carbon::parse($item['date'])->isToday();
            $greaterCurrent = $dateInCollection->greaterThan(Carbon::now());
            $needAddress = ($item['add_address'] === $this->needAddress);
            return $isToday && $greaterCurrent && $needAddress;
        });

        return $collection;
    }

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function convertItems(Collection $collection): Collection
    {
        $arr = [];
        $columns = config('court_sessions.columns');
        foreach ($columns as $column) {
            $columnKeys[] = $column['name'];
        }
        $columnKeys[] = 'key';
        foreach ($collection as $item) {
            unset($item['add_address']);
            $arr[] = array_combine($columnKeys, $this->sortArrayKeys($item));
        }
        return collect($arr);
    }

    /**
     * @param Collection $items
     * @throws Exception
     */
    public function checkFetchedItems(Collection $items)
    {
        if ($items->count() == 0) {
            throw new Exception('The returned array from court.gov.ua has 0 elements');
        }

        $items->each(function (array $item) {
            if ($item['date'] == '' || $item['judge'] == '' || $item['number'] == '') {
                $errorMessage = "The returned array has empty values => " . print_r($item, true);
                throw new Exception($errorMessage);
            }
        });
    }

    /**
     * @param Collection $itemsFromApi
     * @param Collection $itemsFromRedis
     * @return bool
     * @throws Exception
     */
    public function isEqual(Collection $itemsFromApi, Collection $itemsFromRedis): bool
    {
        //Сравниваем 2 массива. Если они разные - записываем в редис новые данные за все дни
        foreach ($itemsFromApi as $key => $item) {
            if (count($item) !== count($itemsFromRedis[$key])) {
                throw new Exception('count($item) !== count($itemsFromRedis[$key])');
            }
            //если в массивах разные данные
            if (collect($item)->diff(collect($itemsFromRedis[$key]))->count() !== 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param Collection $itemsToPusher
     */
    public function sendToPusher(Collection $itemsToPusher)
    {
        $itemsToPusher->each(function ($item, $key) {
            //добавляем ключ clear для первого элемента массива -
            //нужно для того, чтобы очистить таблицу во vue js
            if ($key === 0) {
                $item['clear'] = '';
            }
            broadcast(new SendCourtSessionsToPusherWithQueue($item));
        });
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param array $formParams
     * @return array
     */
    private function getResponseByGuzzleClient(string $method, string $url, array $headers, array $formParams): array
    {
        $arr = [];
        try {
            $response = $this->client->request($method, $url, [
                'headers'     => $headers,
                'form_params' => $formParams
            ])->getBody()->getContents();

            $arr = json_decode($response, true);

            if (isset($arr['error'])) {
                $error = $arr['error'];
                $errorMsg = sprintf(
                    'Response by guzzle client for court sessions has error. Error code - %d, error msg - %s. Class - %s, line - %d, query - %s',
                    $error['error_code'],
                    $error['error_msg'],
                    __CLASS__,
                    __LINE__,
                    $url
                );
                dd($errorMsg);
            }
        } catch (Exception $e) {
            $errorMsg = sprintf(
                'Error during Guzzle request. %s.  Class - %s, line - %d',
                $e->getMessage(),
                __CLASS__,
                __LINE__
            );
            dd($errorMsg);
        }
        return $arr;
    }

    /**
     * Sorting array by keys
     *
     * @param array $item
     * @return array
     */
    private function sortArrayKeys(array $item): array
    {
        $order = [
            'date',
            'judge',
            'number',
            'involved',
            'description',
            'courtroom',
        ];
        return array_replace(array_flip($order), $item);
    }
}
