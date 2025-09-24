<?php

namespace App\Http\Controllers;

use App\Modules\Providers\ProviderFactory;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShopogolicTestController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testGet(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            /**
             * /api/shopogolic/test?entity=warehouses
             * /api/shopogolic/test?entity=couriers [x]
             * /api/shopogolic/test?entity=orders
             * /api/shopogolic/test?entity=parcels
             * /api/shopogolic/test?entity=countries
             * /api/shopogolic/test?entity=regions
             * /api/shopogolic/test?entity=cities
             * /api/shopogolic/test?entity=hsCodes
             * /api/shopogolic/test?entity=users
             * /api/shopogolic/test?entity=addresses [x]
             * /api/shopogolic/test?entity=warehouses&filters[page]=1&filters[per_page]=10
             * /api/shopogolic/test?entity=couriers&filters[warehouse_id]=1
             * /api/shopogolic/test?entity=orders&filters[warehouse_id]=1
             * /api/shopogolic/test?entity=orders&filters[user_id]=123
             * /api/shopogolic/test?entity=orders&filters[status_id]=500
             * /api/shopogolic/test?entity=orders&filters[warehouse_id]=1&filters[status_id]=500
             * /api/shopogolic/test?entity=parcels&filters[warehouse_id]=1
             * /api/shopogolic/test?entity=parcels&filters[user_id]=123
             * /api/shopogolic/test?entity=parcels&filters[status_id]=500
             * /api/shopogolic/test?entity=parcels&filters[warehouse_id]=1&filters[user_id]=123
             * /api/shopogolic/test?entity=countries&filters[per_page]=10&filters[page]=1
             * /api/shopogolic/test?entity=regions&filters[country_code]=RU
             * /api/shopogolic/test?entity=regions&filters[country_code]=US&filters[page]=2
             * /api/shopogolic/test?entity=regions&filters[country_code]=RU&filters[expand][0]=country
             * /api/shopogolic/test?entity=cities&filters[region_id]=5
             * /api/shopogolic/test?entity=cities&filters[region_id]=5&filters[page]=1
             * /api/shopogolic/test?entity=cities&filters[region_id]=5&filters[expand][0]=country&filters[expand][1]=region
             * /api/shopogolic/test?entity=hsCodes&filters[per_page]=25&filters[page]=1
             * /api/shopogolic/test?entity=users&filters[email]=user@example.com
             * /api/shopogolic/test?entity=users&filters[email_like]=@gmail.com
             * /api/shopogolic/test?entity=addresses&filters[user_id]=123
             * /api/shopogolic/test?entity=addresses&filters[user_id]=123&filters[expand][0]=user
             * /api/shopogolic/test?entity=addresses&filters[user_id]=123&filters[expand][0]=country&filters[expand][1]=user
             * */

            $request->validate([
                'entity' => 'required|string|in:warehouses,couriers,orders,parcels,countries,regions,cities,hsCodes,users,addresses,calculate',
                'filters' => 'nullable|array',
            ]);

//            $envFilePath = base_path('.env');
//            if (file_exists($envFilePath)) {
//                $envLines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//                dump($envLines);
//            } else {
//                dump("Файл .env не найден");
//            }

            $entity = $request->input('entity');
            $filters = $request->input('filters', []);

            $provider = ProviderFactory::make('shopogolic');

            $data = match ($entity) {
                'warehouses' => $provider->getWarehouses($filters),
                'couriers' => $provider->getCouriers($filters),
                'orders' => $provider->getOrders($filters),
                'parcels' => $provider->getParcels($filters),
                'calculate' => $provider->calculateShipping($filters),
                'countries' => $provider->getCountries(
                    $filters['per_page'] ?? 20,
                    $filters['page'] ?? 1
                ),
                'regions' => $provider->getRegions(
                    $filters,
                    $filters['expand'] ?? [],
                    $filters['page'] ?? 1
                ),
                'cities' => $provider->getCities(
                    $filters,
                    $filters['expand'] ?? [],
                    $filters['page'] ?? 1
                ),
                'hsCodes' => $provider->getHsCodes(
                    $filters['per_page'] ?? 20,
                    $filters['page'] ?? 1
                ),
                'users' => $provider->getUsers($filters),
                'addresses' => $provider->getAddresses(
                    $filters,
                    $filters['expand'] ?? []
                ),
                default => throw ValidationException::withMessages([
                    'entity' => 'Unsupported entity type.'
                ]),
            };

            $serializedData = array_map(fn($item) => $item->toArray(), $data);

            return response()->json([
                'success' => true,
                'message' => "Successfully retrieved {$entity}.",
                'count' => count($serializedData),
                'data' => $serializedData,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (ShopogolicApiException $e) {
            Log::error('Shopogolic API Error in Test Controller: ' . $e->getMessage(), [
                'code' => $e->getStatusCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
                'code' => $e->getStatusCode(),
            ], 500);

        } catch (\Exception $e) {
            Log::error('Unexpected error in ShopogolicTestController: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
