<?php

namespace Someshwer\WorldCountries\Lib;

use Illuminate\Encryption\Encrypter;
use Someshwer\WorldCountries\Data\DataRepository;
use Someshwer\WorldCountries\Helpers\MyPaginate;

/**
 * Author: Someshwer Bandapally
 * Date: 14-07-2018.
 *
 * This class provides ISO codes data
 *
 * Class ISOCodes
 */
class ISOCodes extends TimeZones
{
    /**
     * @var DataRepository
     */
    private $data;

    /**
     * @var string
     */
    private $en_key = 'Someshwer1@2#BandapallySomeshwer';

    /**
     * @var string
     */
    private $cipher = 'AES-256-CBC';

    /**
     * ISOCodes constructor.
     *
     * @param DataRepository $dataRepository
     */
    public function __construct(DataRepository $dataRepository)
    {
        parent::__construct($dataRepository);
        $this->data = $dataRepository;
    }

    /**
     * Optimize ISO data.
     *
     * @param $all_iso_data
     *
     * @return string
     */
    private function optimizeISOData($all_iso_data)
    {
        $str_length = strlen($all_iso_data) - 4;
        $all_iso_trimmed_data = substr($all_iso_data, 0, 2).substr($all_iso_data, 3, $str_length);
        // $hash = new Encrypter($this->en_key, Config::get('app.cipher'));
        $hash = new Encrypter($this->en_key, $this->cipher);
        $all_iso = $hash->decrypt($all_iso_trimmed_data);

        return $all_iso;
    }

    /**
     * Get optimized ISO data.
     *
     * @return mixed
     */
    private function getOptimizedIsoData()
    {
        $all_countries_iso_data = $this->data->countriesISOData();
        $iso_data = $this->optimizeISOData($all_countries_iso_data);

        return $iso_codes = json_decode($iso_data, true);
    }

    /**
     * Optimize ISO result.
     *
     * @param $iso_codes
     * @param $alpha_code
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    private function optimizeIsoResult($iso_codes, $alpha_code)
    {
        $group_by = ctype_digit($alpha_code) ? 'country_numeric_code' : ((strlen($alpha_code) == 2) ? 'alpha_2' : 'alpha_3');
        $alpha_code = ctype_digit($alpha_code) ? (int) $alpha_code : strtoupper($alpha_code);
        $result = collect($iso_codes)->groupBy($group_by)->get($alpha_code);
        if ($result == null) {
            return $this->isoErrorResponse();
        } else {
            return response(['success' => true, 'iso_info' => $result->first()], 200);
        }
    }

    /**
     * Format regions.
     *
     * @param $iso_codes
     *
     * @return array
     */
    private function formatRegions($iso_codes)
    {
        $regions = collect($iso_codes)->groupBy('region')->all();
        $new_regions = [];
        foreach ($regions as $key => $region) {
            if ($key == '') {
                $key = $region[0]['name'];
            }
            $new_regions[$key] = $region;
        }

        return $new_regions;
    }

    /**
     * Filter regions.
     *
     * @param $regions
     * @param $region
     *
     * @return static
     */
    private function filterRegions($regions, $region)
    {
        return collect($regions)->filter(function ($item, $key) use ($region) {
            return ($region == null) ? true : (strpos(strtolower($key), strtolower($region)) === 0) ? true : false;
        });
    }

    /**
     * Get ISO codes. This method returns all ISO codes data.
     *
     * This method also supports pagination.
     * If pagination is enabled for states in config file
     * then the result contains paginated data otherwise all records
     * wil be directly returned.
     *
     * @param null $page_number
     *
     * @return mixed
     */
    public function isoCodes($page_number = null)
    {
        $page_number = ($page_number == null) ? 1 : $page_number;
        $iso_data = $this->getOptimizedIsoData();
        if (config('world.pagination.iso_codes') == false) {
            return $iso_data;
        }
        $per_page = config('world.pagination.iso_per_page');
        $ceil_val = ceil(count($iso_data) / $per_page);
        $request_url = request()->url();
        $total_records = count($iso_data);
        $pagination_data = MyPaginate::getPagination(
            $request_url,
            $page_number,
            $per_page,
            $ceil_val,
            $total_records
        );
        $data = collect($iso_data)->forPage($page_number, $per_page)->values();
        $pagination_data['data'] = $data;

        return $pagination_data;
    }

    /**
     * Get regions.
     *
     * @return static
     */
    public function regions()
    {
        $iso_codes = $this->getOptimizedIsoData();
        $regions_data = $this->formatRegions($iso_codes);
        $regions = array_keys($regions_data);

        return collect($regions)->map(function ($item) {
            return ['key' => strtolower($item), 'name' => $item];
        });
    }

    /**
     * Search ISO codes by country name,
     * or by iso code, or by numeric country code.
     *
     * @param $key
     *
     * @return array
     */
    public function searchIsoCodes($key = null)
    {
        if ($key == null) {
            return [];
        }
        $iso_data = $this->getOptimizedIsoData();

        return array_values(array_filter($iso_data, function ($item) use ($key) {
            return str_contains(strtolower($item['name']), strtolower($key)) ||
                str_contains(strtolower($item['alpha_2']), strtolower($key)) ||
                str_contains(strtolower($item['alpha_3']), strtolower($key)) ||
                str_contains(strtolower($item['country_numeric_code']), strtolower($key));
        }));
    }

    /**
     * Filter ISO info by country name.
     *
     * @param $iso_codes
     * @param $name
     *
     * @return static
     */
    private function filterIsoInfoByCountryName($iso_codes, $name)
    {
        return collect($iso_codes)->map(function ($item, $key) {
            $item['display_name'] = $item['name'];
            $item['name'] = strtolower(studly_case($item['name']));

            return $item;
        })->groupBy('name')->filter(function ($item, $key) use ($name) {
            return strpos($key, $name) === 0;
        })->collapse();
    }

    /**
     * Get ISO info by country name.
     *
     * @param null $name
     *
     * @return array|ISOCodes
     */
    public function isoInfoByCountryName($name = null)
    {
        if ($name == null) {
            return [];
        }
        $iso_codes = $this->getOptimizedIsoData();
        $result = $this->filterIsoInfoByCountryName($iso_codes, $name);
        if ($result == null) {
            return [];
        }

        return $result;
    }

    /**
     * Get ISO info by code.
     *
     * @param null $code
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function isoInfoByCode($code = null)
    {
        if ($code == null) {
            return $this->isoErrorResponse('NO_PARAM');
        }
        $iso_codes = $this->getOptimizedIsoData();

        return $this->optimizeIsoResult($iso_codes, $code);
    }

    /**
     * ISO codes by region.
     *
     * @param null $region
     *
     * @return ISOCodes
     */
    public function isoCodesByRegion($region = null)
    {
        $iso_codes = $this->getOptimizedIsoData();
        $regions = $this->formatRegions($iso_codes);

        return $this->filterRegions($regions, $region);
    }

    /**
     * Format ISO error response.
     *
     * @param null $param
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    private function isoErrorResponse($param = null)
    {
        $status = 'INVALID_CODE';
        $message = 'Invalid ISO code!';
        if ($param == 'NO_PARAM') {
            $status = 'CODE_REQUIRED';
            $message = 'ISO code parameter is required';
        }

        return response(['error' => true, 'status' => $status, 'message' => $message], 422);
    }
}
