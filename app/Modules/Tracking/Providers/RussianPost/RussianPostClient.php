<?php

namespace App\Modules\Tracking\Providers\RussianPost;

use App\Modules\Tracking\Providers\RussianPost\DTOs\OperationHistoryRequestDTO;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\OperationHistoryResponseDTO;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\PostalOrderEventResponseDTO;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;
use SoapVar;
use stdClass;

/**
 * Клиент для взаимодействия с API Почты России (SOAP).
 */
class RussianPostClient
{
    private const WSDL_URL = 'https://tracking.russianpost.ru/rtm34?wsdl';
    private const SERVICE_URL = 'https://tracking.russianpost.ru/rtm34';

    private string $login;
    private string $password;
    private ?SoapClient $soapClient = null;

    /**
     * @param string $login Логин для доступа к API
     * @param string $password Пароль для доступа к API
     */
    public function __construct(string $login, string $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Получить историю операций по трек-номеру.
     *
     * @param OperationHistoryRequestDTO $requestDto DTO с данными запроса
     * @return array<int, OperationHistoryResponseDTO> Массив DTO событий отслеживания
     * @throws \Exception
     */
    public function getOperationHistory(OperationHistoryRequestDTO $requestDto): array
    {
        $soapClient = null;
        try {
            $soapClient = $this->getSoapClient();

            $operationHistoryRequestData = [
                new SoapVar($requestDto->barcode, XSD_STRING, null, null, 'Barcode', 'http://russianpost.org/operationhistory/data'),
                new SoapVar($requestDto->messageType, XSD_STRING, null, null, 'MessageType', 'http://russianpost.org/operationhistory/data'),
                new SoapVar($requestDto->language, XSD_STRING, null, null, 'Language', 'http://russianpost.org/operationhistory/data'),
            ];

            $operationHistoryRequest = new SoapVar(
                $operationHistoryRequestData,
                SOAP_ENC_OBJECT,
                null,
                null,
                'OperationHistoryRequest',
                'http://russianpost.org/operationhistory/data'
            );

            $authDataArray = [
                new SoapVar($this->login, XSD_STRING, null, null, 'login', 'http://russianpost.org/operationhistory/data'),
                new SoapVar($this->password, XSD_STRING, null, null, 'password', 'http://russianpost.org/operationhistory/data'),
            ];

            $authHeader = new SoapVar(
                $authDataArray,
                SOAP_ENC_OBJECT,
                null,
                null,
                'AuthorizationHeader',
                'http://russianpost.org/operationhistory/data'
            );

            $bodyData = [
                'OperationHistoryRequest' => $operationHistoryRequest,
                'AuthorizationHeader' => $authHeader
            ];

            $params = new SoapVar(
                $bodyData,
                SOAP_ENC_OBJECT,
                null,
                null,
                'getOperationHistory',
                'http://russianpost.org/operationhistory'
            );

            Log::channel('tracking')->debug('RussianPostClient: Sending getOperationHistory request', [
                'barcode' => $requestDto->barcode,
                'messageType' => $requestDto->messageType,
                'language' => $requestDto->language,
            ]);

            $response = $soapClient->__soapCall('getOperationHistory', [$params]);
            $recordCount = 0;

            if (isset($response->OperationHistoryData->historyRecord)) {
                $records = $response->OperationHistoryData->historyRecord;
                $recordCount = is_array($records) ? count($records) : (is_object($records) ? 1 : 0);
            }

            Log::channel('tracking')->debug('RussianPostClient: Received getOperationHistory response', [
                'barcode' => $requestDto->barcode,
                'record_count' => $recordCount,
            ]);

            return $this->parseOperationHistoryResponse($response);

        } catch (SoapFault $soapFault) {
            if ($soapClient) {
                Log::channel('tracking')->debug('RussianPostClient SOAP Request (getOperationHistory) - On SOAP Fault', [
                    'headers' => $soapClient->__getLastRequestHeaders(),
                    'body' => $soapClient->__getLastRequest(),
                ]);

                Log::channel('tracking')->debug('RussianPostClient SOAP Response (getOperationHistory) - On SOAP Fault', [
                    'headers' => $soapClient->__getLastResponseHeaders(),
                    'body' => $soapClient->__getLastResponse(),
                ]);
            }

            Log::channel('tracking')->error('RussianPostClient: SOAP Fault occurred in getOperationHistory', [
                'barcode' => $requestDto->barcode ?? 'N/A',
                'faultcode' => $soapFault->faultcode ?? 'N/A',
                'faultstring' => $soapFault->faultstring ?? 'N/A',
                'faultactor' => $soapFault->faultactor ?? null,
                'detail' => $soapFault->detail ?? null,
            ]);

            if (isset($soapFault->faultcode)) {
                if (strpos($soapFault->faultcode, 'AuthorizationFault') !== false) {
                    throw new \Exception("RussianPost API Authorization Error: " . $soapFault->faultstring, 401, $soapFault);
                } elseif (strpos($soapFault->faultcode, 'OperationHistoryFault') !== false) {
                    throw new \Exception("RussianPost API OperationHistory Error (Possibly Invalid Track Number): " . $soapFault->faultstring, 400, $soapFault);
                } elseif (strpos($soapFault->faultcode, 'LanguageFault') !== false) {
                    throw new \Exception("RussianPost API Language Error: " . $soapFault->faultstring, 400, $soapFault);
                }
            }

            throw new \Exception("RussianPost API SOAP Error: " . $soapFault->getMessage(), 502, $soapFault);

        } catch (\Exception $e) {
            Log::channel('tracking')->error('RussianPostClient: General Error occurred in getOperationHistory', [
                'barcode' => $requestDto->barcode ?? 'N/A',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception("RussianPost API General Error in getOperationHistory: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Получить информацию об операциях с наложенным платежом по трек-номеру.
     *
     * @param string $barcode Трек-номер отправления
     * @param string $language Язык ответа (RUS, ENG)
     * @return array Массив событий наложенного платежа
     * @throws \Exception
     */
    public function getPostalOrderEvents(string $barcode, string $language = 'RUS'): array
    {
        $soapClient = null;
        try {
            $soapClient = $this->getSoapClient();

            $inputData = [
                new SoapVar($barcode, XSD_STRING, null, null, 'Barcode', 'http://www.russianpost.org/RTM/DataExchangeESPP/Data'),
                new SoapVar($language, XSD_STRING, null, null, 'Language', 'http://www.russianpost.org/RTM/DataExchangeESPP/Data'),
            ];

            $input = new SoapVar(
                $inputData,
                SOAP_ENC_OBJECT,
                null,
                null,
                'PostalOrderEventsForMailInput',
                'http://www.russianpost.org/RTM/DataExchangeESPP/Data'
            );

            $authDataArray = [
                new SoapVar($this->login, XSD_STRING, null, null, 'login', 'http://russianpost.org/operationhistory/data'),
                new SoapVar($this->password, XSD_STRING, null, null, 'password', 'http://russianpost.org/operationhistory/data'),
            ];

            $authHeader = new SoapVar(
                $authDataArray,
                SOAP_ENC_OBJECT,
                null,
                null,
                'AuthorizationHeader',
                'http://russianpost.org/operationhistory/data'
            );

            $bodyData = [
                'PostalOrderEventsForMailInput' => $input,
                'AuthorizationHeader' => $authHeader
            ];

            $params = new SoapVar(
                $bodyData,
                SOAP_ENC_OBJECT,
                null,
                null,
                'PostalOrderEventsForMail',
                'http://russianpost.org/operationhistory'
            );

            Log::channel('tracking')->debug('RussianPostClient: Sending PostalOrderEventsForMail request (Body Auth)', [
                'barcode' => $barcode,
                'language' => $language,
            ]);

            $response = $soapClient->__soapCall('PostalOrderEventsForMail', [$params]);
            $eventCount = 0;

            if (isset($response->PostalOrderEventsForMailOutput)) {
                $events = $response->PostalOrderEventsForMailOutput->PostalOrderEvent ?? [];
                $eventCount = is_array($events) ? count($events) : (is_object($events) ? 1 : 0);
            }

            Log::channel('tracking')->debug('RussianPostClient: Received PostalOrderEventsForMail response (Body Auth)', [
                'barcode' => $barcode,
                'event_count' => $eventCount,
            ]);

            return $this->parsePostalOrderEventsResponse($response);

        } catch (SoapFault $soapFault) {
            if ($soapClient) {
                Log::channel('tracking')->debug('RussianPostClient SOAP Request (PostalOrderEventsForMail) - On SOAP Fault (Body Auth)', [
                    'headers' => $soapClient->__getLastRequestHeaders(),
                    'body' => $soapClient->__getLastRequest(),
                ]);

                Log::channel('tracking')->debug('RussianPostClient SOAP Response (PostalOrderEventsForMail) - On SOAP Fault (Body Auth)', [
                    'headers' => $soapClient->__getLastResponseHeaders(),
                    'body' => $soapClient->__getLastResponse(),
                ]);
            }

            Log::channel('tracking')->error('RussianPostClient: SOAP Fault occurred in PostalOrderEventsForMail (Body Auth)', [
                'barcode' => $barcode ?? 'N/A',
                'faultcode' => $soapFault->faultcode ?? 'N/A',
                'faultstring' => $faultString = ($soapFault->faultstring ?? 'N/A'),
                'faultactor' => $soapFault->faultactor ?? null,
                'detail' => $soapFault->detail ?? null,
            ]);

            if (isset($soapFault->faultcode)) {
                if (strpos($soapFault->faultcode, 'AuthorizationFault') !== false) {
                    throw new \Exception("RussianPost API Authorization Error (PostalOrder): " . $soapFault->faultstring, 401, $soapFault);
                }

                if (strpos($soapFault->faultcode, 'PostalOrderEventsForMailFault') !== false) {
                    Log::channel('tracking')->warning('RussianPostClient: PostalOrderEventsForMailFault. Likely no postal order data for barcode.', [
                        'barcode' => $barcode,
                        'faultcode' => $soapFault->faultcode,
                        'faultstring' => $faultString,
                    ]);

                    return [];
                }
            }

            if (
                ($soapFault->faultcode ?? '') === 'S:Receiver' &&
                (
                    strpos($faultString, 'Обратитесь в службу поддержки') !== false ||
                    strpos($faultString, 'Please contact support') !== false
                )
            ) {
                Log::channel('tracking')->warning('RussianPostClient: PostalOrderEventsForMail returned generic server error (S:Receiver). Likely no postal order data for barcode.', [
                    'barcode' => $barcode,
                    'faultcode' => $soapFault->faultcode,
                    'faultstring' => $faultString,
                ]);

                return [];
            }

            throw new \Exception("RussianPost API SOAP Error (PostalOrder): " . $soapFault->getMessage(), 502, $soapFault);

        } catch (\Exception $e) {
            Log::channel('tracking')->error('RussianPostClient: General Error occurred in PostalOrderEventsForMail (Body Auth)', [
                'barcode' => $barcode ?? 'N/A',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception("RussianPost API General Error in PostalOrderEventsForMail: " . $e->getMessage(), 500, $e);
        }
    }


    /**
     * @return SoapClient
     * @throws SoapFault
     */
    private function getSoapClient(): SoapClient
    {
        if ($this->soapClient === null) {
            $options = [
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => 30,
//                'stream_context' => stream_context_create([
//                    'ssl' => [
//                        // 'verify_peer' => false, // Отключение проверки SSL
//                        // 'verify_peer_name' => false, // Отключение проверки имени пира
//                        // 'allow_self_signed' => true, // Разрешить самоподписанные сертификаты
//                        // 'cafile' => '/path/to/cacert.pem', // Путь к файлу CA
//                    ]
//                ]),
            ];

            $this->soapClient = new SoapClient(self::WSDL_URL, $options);
        }

        return $this->soapClient;
    }

    /**
     * @param object $response Ответ от SOAP API
     * @return array<int, OperationHistoryResponseDTO>
     */
    private function parseOperationHistoryResponse(object $response): array
    {
        $events = [];
        if (!isset($response->OperationHistoryData) ||
            !isset($response->OperationHistoryData->historyRecord)
        ) {
            return $events;
        }

        $historyRecords = $response->OperationHistoryData->historyRecord;
        if (!is_array($historyRecords)) {
            $historyRecords = [$historyRecords];
        }

        foreach ($historyRecords as $record) {
            if ($record instanceof stdClass) {
                try {
                    $eventDto = new OperationHistoryResponseDTO($this->objectToArray($record));
                    $events[] = $eventDto;
                } catch (\Exception $e) {
                    Log::channel('tracking')->warning('RussianPostClient: Failed to parse history record', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        usort($events, function (OperationHistoryResponseDTO $a, OperationHistoryResponseDTO $b) {
            return $a->operationDate <=> $b->operationDate;
        });

        return $events;
    }

    /**
     * @param object $response Ответ от SOAP API
     * @return array<int, PostalOrderEventResponseDTO> Массив DTO событий наложенного платежа
     */
    private function parsePostalOrderEventsResponse(object $response): array
    {
        $events = [];
        if (!isset($response->PostalOrderEventsForMailOutput) ||
            !isset($response->PostalOrderEventsForMailOutput->PostalOrderEvent)
        ) {
            return $events;
        }

        $postalOrderEvents = $response->PostalOrderEventsForMailOutput->PostalOrderEvent;
        if (!is_array($postalOrderEvents)) {
            $postalOrderEvents = [$postalOrderEvents];
        }

        foreach ($postalOrderEvents as $event) {
            if ($event instanceof stdClass) {
                try {
                    $eventDto = new PostalOrderEventResponseDTO($this->objectToArray($event));
                    $events[] = $eventDto;
                } catch (\Exception $e) {
                    Log::channel('tracking')->warning('RussianPostClient: Failed to parse PostalOrderEvent record', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        usort($events, function (PostalOrderEventResponseDTO $a, PostalOrderEventResponseDTO $b) {
            return $a->eventDateTime <=> $b->eventDateTime;
        });

        return $events;
    }

    private function objectToArray($data)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            return array_map([$this, 'objectToArray'], $data);
        }

        return $data;
    }
}
