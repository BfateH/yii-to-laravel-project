<?php

namespace App\Modules\Tracking\Providers\RussianPost\DTOs\Responses;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * DTO для представления одного события (historyRecord) из ответа getOperationHistory API Почты России.
 */
class OperationHistoryResponseDTO
{
    public readonly ?DateTimeInterface $operationDate;
    public readonly ?int $operationTypeId;
    public readonly ?string $operationTypeName;
    public readonly ?int $operationAttrId;
    public readonly ?string $operationAttrName;
    public readonly ?string $operationAddressIndex;
    public readonly ?string $operationAddressDescription;
    public readonly ?string $destinationAddressIndex;
    public readonly ?string $destinationAddressDescription;
    public readonly ?int $countryOperId;
    public readonly ?string $countryOperCode2A;
    public readonly ?string $countryOperCode3A;
    public readonly ?string $countryOperNameRU;
    public readonly ?string $countryOperNameEN;
    public readonly ?string $itemBarcode;
    public readonly ?int $itemMass;
    public readonly ?int $payment; // Сумма наложенного платежа в копейках
    public readonly ?int $value;   // Сумма объявленной ценности в копейках
    public readonly array $rawData;

    public function __construct(array $historyRecordData)
    {
        $operationParams = $historyRecordData['OperationParameters'] ?? [];
        $this->operationDate = new DateTimeImmutable($operationParams['OperDate'] ?? 'now');
        $this->operationTypeId = (int)($operationParams['OperType']['Id'] ?? 0);
        $this->operationTypeName = (string)($operationParams['OperType']['Name'] ?? '');
        $this->operationAttrId = (int)($operationParams['OperAttr']['Id'] ?? 0);
        $this->operationAttrName = (string)($operationParams['OperAttr']['Name'] ?? '');
        $addressParams = $historyRecordData['AddressParameters'] ?? [];
        $operationAddress = $addressParams['OperationAddress'] ?? [];
        $this->operationAddressIndex = !empty($operationAddress['Index']) ? (string)$operationAddress['Index'] : null;
        $this->operationAddressDescription = !empty($operationAddress['Description']) ? (string)$operationAddress['Description'] : null;
        $destinationAddress = $addressParams['DestinationAddress'] ?? [];
        $this->destinationAddressIndex = !empty($destinationAddress['Index']) ? (string)$destinationAddress['Index'] : null;
        $this->destinationAddressDescription = !empty($destinationAddress['Description']) ? (string)$destinationAddress['Description'] : null;
        $countryOper = $addressParams['CountryOper'] ?? [];
        $this->countryOperId = !empty($countryOper['Id']) ? (int)$countryOper['Id'] : null;
        $this->countryOperCode2A = !empty($countryOper['Code2A']) ? (string)$countryOper['Code2A'] : null;
        $this->countryOperCode3A = !empty($countryOper['Code3A']) ? (string)$countryOper['Code3A'] : null;
        $this->countryOperNameRU = !empty($countryOper['NameRU']) ? (string)$countryOper['NameRU'] : null;
        $this->countryOperNameEN = !empty($countryOper['NameEN']) ? (string)$countryOper['NameEN'] : null;
        $itemParams = $historyRecordData['ItemParameters'] ?? [];
        $this->itemBarcode = !empty($itemParams['Barcode']) ? (string)$itemParams['Barcode'] : null;
        $this->itemMass = !empty($itemParams['Mass']) ? (int)$itemParams['Mass'] : null;
        $financeParams = $historyRecordData['FinanceParameters'] ?? [];
        $this->payment = !empty($financeParams['Payment']) ? (int)$financeParams['Payment'] : null;
        $this->value = !empty($financeParams['Value']) ? (int)$financeParams['Value'] : null;
        $this->rawData = $historyRecordData;
    }

    /**
     *
     * @return array
     */
    public function toTrackingEventAttributes(): array
    {
        return [
            'operation_date' => $this->operationDate,
            'operation_type_id' => $this->operationTypeId,
            'operation_type_name' => $this->operationTypeName,
            'operation_attr_id' => $this->operationAttrId,
            'operation_attr_name' => $this->operationAttrName,
            'operation_address_index' => $this->operationAddressIndex,
            'operation_address_description' => $this->operationAddressDescription,
            'destination_address_index' => $this->destinationAddressIndex,
            'destination_address_description' => $this->destinationAddressDescription,
            'country_oper_id' => $this->countryOperId,
            'country_oper_code2a' => $this->countryOperCode2A,
            'country_oper_code3a' => $this->countryOperCode3A,
            'country_oper_name_ru' => $this->countryOperNameRU,
            'country_oper_name_en' => $this->countryOperNameEN,
            'item_barcode' => $this->itemBarcode,
            'item_mass' => $this->itemMass,
            'payment' => $this->payment,
            'value' => $this->value,
            'raw_data' => $this->rawData,
        ];
    }
}
