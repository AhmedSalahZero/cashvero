<?php 
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use Exception;

class ExchangeRateService
{
	
	use AuthTrait;

    
    public function getBaseCurrency()
    {
        try {
            $company = $this->execute('res.company', 'search_read', [
                [['id', '!=', 0]], // Fetch the main company
                ['currency_id']
            ]);
            if (empty($company)) {
                throw new Exception('No company found');
            }
			
            return $this->execute('res.currency', 'read', [
                [$company[0]['currency_id'][0]],
                ['name', 'symbol']
            ])[0];
        } catch (Exception $e) {
            throw new Exception('Failed to fetch base currency: ' . $e->getMessage());
        }
    }

    /**
     * Get currency ID by currency code (e.g., USD, EUR)
     * @param string $currencyCode
     * @return int Currency ID
     */
    public function getCurrencyId($currencyCode)
    {

            $currencies = $this->execute('res.currency', 'search_read', [
                [
					['name', '=', strtoupper($currencyCode)], ['active', '=', true]],
                ['id']
            ]);
            if (empty($currencies)) {
                throw new Exception("Currency {$currencyCode} not found or inactive");
            }
            return $currencies[0]['id'];
        
    }

    public function getExchangeRates($currencyCode, $date = null, $startDate = null, $endDate = null)
    {
        try {
            // Step 1: Get currency ID
            $currencyId = $this->getCurrencyId($currencyCode);

            // Step 2: Get base currency
            $baseCurrency = $this->getBaseCurrency();

            // Step 3: Build domain for exchange rates
            $domain = [['currency_id', '=', $currencyId]];
           
            // Step 4: Fetch exchange rates
            $rates = $this->execute('res.currency.rate', 'search_read', [
				$domain,
                ['name', 'rate', 'currency_id', 'company_id'],
            ]);
            if (empty($rates) && $date) {
                throw new Exception("No exchange rate found for {$currencyCode} on {$date}");
            }

            // Step 5: Map rates to include direct rate (1 foreign unit = X base units)
            $result = [
                'currency' => $currencyCode,
                'base_currency' => $baseCurrency['name'],
                'rates' => array_map(function ($rate) {
                    return [
                        'date' => $rate['name'],
                        'rate' => $rate['rate'], // Inverse rate (1 foreign unit = rate base units)
                        'direct_rate' => $rate['rate'] ? 1 / $rate['rate'] : 0, // Direct rate (1 base unit = X foreign units)
                        'company_id' => $rate['company_id'] ? $rate['company_id'][0] : null
                    ];
                }, $rates)
            ];

            return $result;
        } catch (Exception $e) {
            throw new Exception('Failed to fetch exchange rates: ' . $e->getMessage());
        }
    }
	 
}
?>
