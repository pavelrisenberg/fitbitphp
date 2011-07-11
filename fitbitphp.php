<?php
/**
 * FitBitPHP v.0.60. Basic FitBit API wrapper for PHP using OAuth
 *
 * Note: Library is in beta and provided as-is. We hope to add features as API grows, however
 *       feel free to fork, extend and send pull requests to us.
 *
 * - https://github.com/heyitspavel/fitbitphp
 *
 *
 * Date: 2011/07/11
 * Requires OAuth 1.0.0, SimpleXML
 * @version 0.60 ($Id$)
 */


class FitBitPHP
{

    /**
     * API Constants
     *
     */
    private $authHost = 'www.fitbit.com';
    private $apiHost = 'api.fitbit.com';

    private $baseApiUrl;
    private $authUrl;
    private $requestTokenUrl;
    private $accessTokenUrl;


    /**
     * Class Variables
     *
     */
    protected $oauth;
    protected $oauth_Token, $oauth_Secret;

    protected $userId = '-';

    protected $metric = 0;
    protected $userAgent = 'FitBitPHP 0.60';
    protected $debug;


    /**
     * @param string $consumer_key Application consumer key for FitBit API
     * @param string $consumer_secret Application secret
     * @param int $debug Debug mode (0/1) enables OAuth internal debug
     * @param string $userAgent User-agent to use in API calls
     */
    public function __construct($consumer_key, $consumer_secret, $debug = 1, $userAgent = null)
    {
        $this->initUrls();

        $this->oauth = new OAuth($consumer_key, $consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);

        
        $this->debug = $debug;
        if (isset($userAgent))
            $this->userAgent = $userAgent;

        if ($debug)
            $this->oauth->enableDebug();
    }

    public function setEndpointBase($apiHost, $authHost)
    {
        $this->apiHost = $apiHost;
        $this->authHost = $authHost;

        $this->initUrls();
    }

    private function initUrls()
    {
        $this->baseApiUrl = 'http://' . $this->apiHost . '/1/';
        $this->authUrl = 'https://' . $this->authHost . '/oauth/authorize';
        $this->requestTokenUrl = 'https://' . $this->apiHost . '/oauth/request_token';
        $this->accessTokenUrl = 'https://' . $this->apiHost . '/oauth/access_token';
    }


    /**
     * @return OAuth debugInfo object. Debug should be enabled in __construct
     */
    public function oauthDebug()
    {
        return $this->oauth->debugInfo;
    }


    /**
     * Returns FitBit session status for frontend (i.e. 'Sign in with FitBit' implementations)
     *
     * @return int (0 - no session, 1 - just after successful authorization, 2 - session exist)
     */
    public function sessionStatus()
    {
        $session = session_id();
        if (empty($session)) {
            session_start();
        }
        if (empty($_SESSION['fitbit_Session']))
            $_SESSION['fitbit_Session'] = 0;

        return (int)$_SESSION['fitbit_Session'];
    }

    /**
     * Initialize session. Inits OAuth session, handles redirects to FitBit login/authorization if needed
     *
     * @param  $callbackUrl Callback for 'Sign in with FitBit'
     * @param  $cookie Use persistent cookie for authorization, or session cookie only
     * @return int (1 - just after successful authorization, 2 - if session already exist)
     */
    public function initSession($callbackUrl, $cookie = true)
    {

        $session = session_id();
        if (empty($session)) {
            session_start();
        }

        if (!isset($_GET['oauth_token']) && $_SESSION['fitbit_Session'] == 1)
            $_SESSION['fitbit_Session'] = 0;


        if ($_SESSION['fitbit_Session'] == 0) {

            $request_token_info = $this->oauth->getRequestToken($this->requestTokenUrl, $callbackUrl);

            $_SESSION['fitbit_Secret'] = $request_token_info['oauth_token_secret'];
            $_SESSION['fitbit_Session'] = 1;

            header('Location: ' . $this->authUrl . '?oauth_token=' . $request_token_info['oauth_token']);
            exit;

        } else if ($_SESSION['fitbit_Session'] == 1) {

            $this->oauth->setToken($_GET['oauth_token'], $_SESSION['fitbit_Secret']);
            $access_token_info = $this->oauth->getAccessToken($this->accessTokenUrl);

            $_SESSION['fitbit_Session'] = 2;
            $_SESSION['fitbit_Token'] = $access_token_info['oauth_token'];
            $_SESSION['fitbit_Secret'] = $access_token_info['oauth_token_secret'];

            $this->setOAuthDetails($_SESSION['fitbit_Token'], $_SESSION['fitbit_Secret']);
            return 1;

        } else if ($_SESSION['fitbit_Session'] == 2) {
            $this->setOAuthDetails($_SESSION['fitbit_Token'], $_SESSION['fitbit_Secret']);
            return 2;
        }
    }


    /**
     * Sets OAuth token/secret. Use if library used in internal calls without session handling
     *
     * @param  $token
     * @param  $secret
     * @return void
     */
    public function setOAuthDetails($token, $secret)
    {
        $this->oauth_Token = $token;
        $this->oauth_Secret = $secret;

        $this->oauth->setToken($this->oauth_Token, $this->oauth_Secret);
    }

    /**
     * Get OAuth token
     *
     * @return string
     */
    public function getOAuthToken()
    {
        return $this->oauth_Token;
    }

    /**
     * Get OAuth secret
     *
     * @return string
     */
    public function getOAuthSecret()
    {
        return $this->oauth_Secret;
    }


    /**
     * Set FitBit userId for future API calls
     *
     * @param  $userId 'XXXXX'
     * @return void
     */
    public function setUser($userId)
    {
        $this->userId = $userId;
    }


    /**
     * Set Unit System for all future calls (see http://wiki.fitbit.com/display/API/API-Unit-System)
     * 0 (Metric), 1 (en_US), 2 (en_GB)
     *
     * @param int $metric
     * @return void
     */
    public function setMetric($metric)
    {
        $this->metric = $metric;
    }


    /**
     * API wrappers
     *
     */

    /**
     * Get user profile
     *
     * @throws FitBitException
     * @param string $userId UserId of public profile, if none using set with setUser or '-' by default
     * @return SimpleXMLElement
     */
    public function getProfile()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/profile.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Update user profile
     *
     * @throws FitBitException
     * @param string $gender 'FEMALE', 'MALE' or 'NA'
     * @param DateTime $birthday Date of birth
     * @param string $height Height in cm/inches (as set with setMetric)
     * @param string $nickname Nickname
     * @param string $fullName Full name
     * @param string $timezone Timezone in the format 'America/Los_Angeles'
     * @return SimpleXMLElement
     */
    public function updateProfile($gender = null, $birthday = null, $height = null, $nickname = null, $fullName = null, $timezone = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        if (isset($gender))
            $parameters['gender'] = $gender;
        if (isset($birthday))
            $parameters['birthday'] = $birthday->format('Y-m-d');
        if (isset($height))
            $parameters['height'] = $height;
        if (isset($nickname))
            $parameters['nickname'] = $nickname;
        if (isset($fullName))
            $parameters['fullName'] = $fullName;
        if (isset($timezone))
            $parameters['timezone'] = $timezone;

        $this->oauth->fetch($this->baseApiUrl . "user/-/profile.xml",
                            $parameters, OAUTH_HTTP_METHOD_POST, $headers);

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user activities for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @return SimpleXMLElement
     */
    public function getActivities($date)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/activities/date/" . $date->format('Y-m-d') . ".xml",
                            null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get full description of specific activity
     *
     * @throws FitBitException
     * @param  string $id Activity log Id
     * @return SimpleXMLElement
     */
    public function getActivity($id)
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "activities/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user recent activities
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getRecentActivities()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/activities/recent.xml", null,
                            OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user frequent activities
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getFrequentActivities()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/activities/frequent.xml", null,
                            OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user favorite activities
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getFavoriteActivities()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/activities/favorite.xml", null,
                            OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user activity
     *
     * @throws FitBitException
     * @param DateTime $date Activity date and time (set proper timezone, which could be fetched via getProfile)
     * @param string $activityId Activity Id (or Intensity Level Id) from activities database,
     *                                  see http://wiki.fitbit.com/display/API/API-Log-Activity
     * @param string $duration Duration millis
     * @param string $calories Manual calories to override FitBit estimate
     * @param string $distance Distance in km/miles (as set with setMetric)
     * @param string $distanceUnit Distance unit string (see http://wiki.fitbit.com/display/API/API-Distance-Unit)
     * @return bool
     */
    public function logActivity($date, $activityId, $duration, $calories = null, $distance = null, $distanceUnit = null)
    {
        $distanceUnits = array('Centimeter', 'Foot', 'Inch', 'Kilometer', 'Meter', 'Mile', 'Millimeter', 'Steps', 'Yards');

        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['startTime'] = $date->format('H:i');
        $parameters['activityId'] = $activityId;
        $parameters['durationMillis'] = $duration;
        if (isset($calories))
            $parameters['manualCalories'] = $calories;
        if (isset($distance))
            $parameters['distance'] = $distance;
        if (isset($distanceUnit) && in_array($distanceUnit, $distanceUnits))
            $parameters['distanceUnit'] = $distanceUnit;

        $this->oauth->fetch($this->baseApiUrl . "user/-/activities.xml", $parameters,
                            OAUTH_HTTP_METHOD_POST, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user activity
     *
     * @throws FitBitException
     * @param string $id Activity log id
     * @return bool
     */
    public function deleteActivity($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/activities/" . $id . ".xml", null,
                            OAUTH_HTTP_METHOD_DELETE, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Add user favorite activity
     *
     * @throws FitBitException
     * @param string $id Activity log id
     * @return bool
     */
    public function addFavoriteActivity($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/activities/log/favorite/" . $id . ".xml",
                            null, OAUTH_HTTP_METHOD_POST, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user favorite activity
     *
     * @throws FitBitException
     * @param string $id Activity log id
     * @return bool
     */
    public function deleteFavoriteActivity($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/activities/log/favorite/" . $id . ".xml",
                            null, OAUTH_HTTP_METHOD_DELETE, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user foods for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @return SimpleXMLElement
     */
    public function getFoods($date)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/foods/log/date/" . $date->format('Y-m-d') . ".xml",
                            null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user recent foods
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getRecentFoods()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/recent.xml", null,
                            OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user frequent foods
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getFrequentFoods()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/frequent.xml", null,
                            OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user favorite foods
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getFavoriteFoods()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/favorite.xml", null,
                            OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user food
     *
     * @throws FitBitException
     * @param DateTime $date Food log date
     * @param string $foodId Food Id from foods database (see searchFoods)
     * @param string $mealTypeId Meal Type Id from foods database (see searchFoods)
     * @param string $unitId Unit Id, should be allowed for this food (see getFoodUnits and searchFoods)
     * @param string $amount Amount in specified units
     * @return bool
     */
    public function logFood($date, $foodId, $mealTypeId, $unitId, $amount)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['foodId'] = $foodId;
        $parameters['mealTypeId'] = $mealTypeId;
        $parameters['unitId'] = $unitId;
        $parameters['amount'] = $amount;
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log.xml", $parameters,
                            OAUTH_HTTP_METHOD_POST, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Add user favorite food
     *
     * @throws FitBitException
     * @param string $id Food log id
     * @return bool
     */
    public function addFavoriteFood($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/favorite/" . $id . ".xml", null,
                            OAUTH_HTTP_METHOD_POST, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user favorite food
     *
     * @throws FitBitException
     * @param string $id Food log id
     * @return bool
     */
    public function deleteFavoriteFood($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/favorite/" . $id . ".xml",
                            null, OAUTH_HTTP_METHOD_DELETE, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user meal sets
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getMeals()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/meals.xml",
                            null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get food units library
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getFoodUnits()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "foods/units.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Search for foods in foods database
     *
     * @throws FitBitException
     * @param string $query Search query
     * @return SimpleXMLElement
     */
    public function searchFoods($query)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "foods/search.xml?query=" . rawurlencode($query), null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Create private foods for a user
     *
     * @throws FitBitException
     * @param string $name Food name
     * @param string $defaultFoodMeasurementUnitId Unit id of the default measurement unit
     * @param string $defaultServingSize Default serving size in measurement units
     * @param string $calories Calories in default serving
     * @param string $description
     * @param string $formType ("LIQUID" or "DRY)
     * @param string $nutrition Array of nutritional values, see http://wiki.fitbit.com/display/API/API-Create-Food
     * @return SimpleXMLElement
     */
    public function createFood($name, $defaultFoodMeasurementUnitId, $defaultServingSize, $calories, $description = null, $formType = null, $nutrition = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['name'] = $name;
        $parameters['defaultFoodMeasurementUnitId'] = $defaultFoodMeasurementUnitId;
        $parameters['defaultServingSize'] = $defaultServingSize;
        $parameters['calories'] = $calories;
        if (isset($description))
            $parameters['description'] = $description;
        if (isset($formType))
            $parameters['formType'] = $formType;
        if (isset($nutrition)) {
            foreach ($nutrition as $i => $value) {
                $parameters[$i] = $nutrition[$i];
            }
        }

        $this->oauth->fetch($this->baseApiUrl . "foods.xml", $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user water log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @return SimpleXMLElement
     */
    public function getWater($date)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/water/date/" . $date->format('Y-m-d') . ".xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user water
     *
     * @throws FitBitException
     * @param DateTime $date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @param string $amount Amount in ml/fl oz (as set with setMetric) or waterUnit
     * @param string $waterUnit Water Unit ("ml", "fl oz" or "cup")
     * @return SimpleXMLElement
     */
    public function logWater($date, $amount, $waterUnit = null)
    {
        $waterUnits = array('ml', 'fl oz', 'cup');

        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['amount'] = $amount;
        if (isset($waterUnit) && in_array($waterUnit, $waterUnits))
            $parameters['unit'] = $waterUnit;

        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/water.xml", $parameters,
                            OAUTH_HTTP_METHOD_POST, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user water record
     *
     * @throws FitBitException
     * @param string $id Water log id
     * @return bool
     */
    public function deleteWater($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/water/" . $id . ".xml", null,
                            OAUTH_HTTP_METHOD_DELETE, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user sleep log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @return SimpleXMLElement
     */
    public function getSleep($date)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/sleep/date/" . $date->format('Y-m-d') . ".xml",
                            null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user sleep
     *
     * @throws FitBitException
     * @param DateTime $date Sleep date and time (set proper timezone, which could be fetched via getProfile)
     * @param string $duration Duration millis
     * @return SimpleXMLElement
     */
    public function logSleep($date, $duration)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['startTime'] = $date->format('H:i');
        $parameters['duration'] = $duration;

        $this->oauth->fetch($this->baseApiUrl . "user/-/sleep.xml", $parameters,
                            OAUTH_HTTP_METHOD_POST, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user sleep record
     *
     * @throws FitBitException
     * @param string $id Activity log id
     * @return bool
     */
    public function deleteSleep($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/sleep/" . $id . ".xml", null,
                            OAUTH_HTTP_METHOD_DELETE, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user body measurements
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @return SimpleXMLElement
     */
    public function getBody($date)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/body/date/" . $date->format('Y-m-d') . ".xml",
                            null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user weight
     *
     * @throws FitBitException
     * @param string $weight Float number. For en_GB units, provide floating number of stones (i.e. 11 st. 4 lbs = 11.2857143)
     * @param DateTime $date If present, date for which logged, now by default
     * @return bool
     */
    public function logWeight($weight, $date = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['weight'] = $weight;
        if (isset($date))
            $parameters['date'] = $date->format('Y-m-d');

        $this->oauth->fetch($this->baseApiUrl . "user/-/body/weight.xml",
                            $parameters, OAUTH_HTTP_METHOD_POST, $headers);

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }

    /**
     * Launch TimeSeries requests
     *
     * Allowed types are: 'caloriesIn', 'caloriesOut', 'steps', 'distance',
     *            'minutesSedentary', 'minutesLightlyActive', 'minutesFairlyActive', 'minutesVeryActive',
     *            'activeScore', 'activityCalories',
     *            'minutesAsleep', 'minutesAwake', 'awakeningsCount', 'timeInBed',
     *            'weight', 'bmi', 'fat'
     *
     * @throws FitBitException
     * @param string $type
     * @param  $basedate DateTime or 'today', to_period
     * @param  $to_period DateTime or '1d, 7d, 30d, 1w, 1m, 3m, 6m, 1y, max'
     * @return array
     */
    public function getTimeSeries($type, $basedate, $to_period)
    {

        switch ($type) {
            case 'caloriesIn':
                $path = '/foods/log/caloriesIn';
                break;

            case 'caloriesOut':
                $path = '/activities/log/calories';
                break;
            case 'steps':
                $path = '/activities/log/steps';
                break;
            case 'distance':
                $path = '/activities/log/distance';
                break;
            case 'minutesSedentary':
                $path = '/activities/log/minutesSedentary';
                break;
            case 'minutesLightlyActive':
                $path = '/activities/log/minutesLightlyActive';
                break;
            case 'minutesFairlyActive':
                $path = '/activities/log/minutesFairlyActive';
                break;
            case 'minutesVeryActive':
                $path = '/activities/log/minutesVeryActive';
                break;
            case 'activeScore':
                $path = '/activities/log/activeScore';
                break;
            case 'activityCalories':
                $path = '/activities/log/activityCalories';
                break;

            case 'minutesAsleep':
                $path = '/sleep/minutesAsleep';
                break;
            case 'minutesAwake':
                $path = '/sleep/minutesAwake';
                break;
            case 'awakeningsCount':
                $path = '/sleep/awakeningsCount';
                break;
            case 'timeInBed':
                $path = '/sleep/timeInBed';
                break;

            case 'weight':
                $path = '/body/weight';
                break;
            case 'bmi':
                $path = '/body/bmi';
                break;
            case 'fat':
                $path = '/body/fat';
                break;

            default:
                return false;
        }


        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . $path . '/date/' . (is_string($basedate) ? $basedate : $basedate->format('Y-m-d')) . "/" . (is_string($to_period) ? $to_period : $to_period->format('Y-m-d')) . ".json", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $json = json_decode($response);
            $path = str_replace('/', '-', substr($path, 1));
            return $json->$path;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get list of devices and their properties
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getDevices()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/devices.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get specific device info
     *
     * @throws FitBitException
     * @param string $id Device Id
     * @return SimpleXMLElement
     */
    public function getDevice($id)
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/devices/" . $id . ".xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user friends
     *
     * @throws FitBitException
     * @return SimpleXMLElement
     */
    public function getFriends()
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/friends.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user's friends leaderboard
     *
     * @throws FitBitException
     * @param string $period Depth ('7d' or '30d')
     * @return SimpleXMLElement
     */
    public function getFriendsLeaderboard($period = '7d')
    {
        $headers = $this->getHeaders();
        $this->oauth->fetch($this->baseApiUrl . "user/-/friends/leaders/" . $period . ".xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Invite user to become friends
     *
     * @throws FitBitException
     * @param string $userId Invite user by id
     * @param string $email Invite user by email address (could be already Fitbit member or not)
     * @return SimpleXMLElement
     */
    public function inviteFriend($userId = null, $email = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        if (isset($userId))
            $parameters['invitedUserId'] = $userId;
        if (isset($email))
            $parameters['invitedUserEmail'] = $email;

        $this->oauth->fetch($this->baseApiUrl . "user/-/friends/invitations.xml", $parameters, OAUTH_HTTP_METHOD_POST, $headers);

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Accept invite to become friends from user
     *
     * @throws FitBitException
     * @param string $userId Id of the inviting user
     * @return SimpleXMLElement
     */
    public function acceptFriend($userId)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['accept'] = 'true';

        $this->oauth->fetch($this->baseApiUrl . "user/-/friends/invitations/" . $userId . ".xml", $parameters, OAUTH_HTTP_METHOD_POST, $headers);

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Accept invite to become friends from user
     *
     * @throws FitBitException
     * @param string $userId Id of the inviting user
     * @return SimpleXMLElement
     */
    public function rejectFriend($userId)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['accept'] = 'true';

        $this->oauth->fetch($this->baseApiUrl . "user/-/friends/invitations/" . $userId . ".xml", $parameters, OAUTH_HTTP_METHOD_POST, $headers);

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Add subscription
     *
     * @throws FitBitException
     * @param string $id Subscription Id
     * @param string $path Subscription resource path (beginning with slash). Omit to subscribe to all user updates.
     * @return
     */
    public function addSubscription($id, $path = null, $subscriberId = null)
    {
        $headers = $this->getHeaders();
        $userHeaders = array();
        if ($subscriberId)
            $userHeaders['X-Fitbit-Subscriber-Id'] = $subscriberId;
        $headers = array_merge($headers, $userHeaders);


        if (isset($path))
            $path = '/' . $path;
        else
            $path = '';
        $this->oauth->fetch($this->baseApiUrl . "user/-" . $path . "/apiSubscriptions/" . $id . ".xml", null, OAUTH_HTTP_METHOD_POST, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200') || !strcmp($responseInfo['http_code'], '201')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user subscription
     *
     * @throws FitBitException
     * @param string $id Subscription Id
     * @param string $path Subscription resource path (beginning with slash)
     * @return bool
     */
    public function deleteSubscription($id, $path = null)
    {
        $headers = $this->getHeaders();
        if (isset($path))
            $path = '/' . $path;
        else
            $path = '';
        $this->oauth->fetch($this->baseApiUrl . "user/-" . $path . "/apiSubscriptions/" . $id . ".xml", null, OAUTH_HTTP_METHOD_DELETE, $headers);
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get list of user's subscriptions for this application
     *
     * @throws FitBitException
     * @return
     */
    public function getSubscriptions()
    {
        $headers = $this->getHeaders();

        $this->oauth->fetch($this->baseApiUrl . "user/-/apiSubscriptions.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xml = simplexml_load_string($response);
            return $xml;
        } else {
            throw new FitBitException('FitBit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Make custom call to any API endpoint
     *
     * @param string $url Endpoint url after '.../1/'
     * @param array $parameters Request parameters
     * @param string $method (OAUTH_HTTP_METHOD_GET, OAUTH_HTTP_METHOD_POST, OAUTH_HTTP_METHOD_PUT, OAUTH_HTTP_METHOD_DELETE)
     * @param array $userHeaders Additional custom headers
     * @return FitBitResponse
     */
    public function customCall($url, $parameters, $method, $userHeaders = array())
    {
        $headers = $this->getHeaders();
        $headers = array_merge($headers, $userHeaders);

        $this->oauth->fetch($this->baseApiUrl . $url, $parameters, $method, $headers);
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        return new FitBitResponse($response, $responseInfo['http_code']);
    }


    /**
     * @return array
     */
    private function getHeaders()
    {
        $headers = array();
        $headers['User-Agent'] = $this->userAgent;

        if ($this->metric == 1) {
            $headers['Accept-Language'] = 'en_US';
        } else if ($this->metric == 2) {
            $headers['Accept-Language'] = 'en_GB';
        }

        return $headers;
    }


}


/**
 * FitBit API communication exception
 *
 */
class FitBitException extends Exception
{
}


/**
 * Basic response wrapper for customCall
 *
 */
class FitBitResponse
{
    public $response;
    public $code;

    /**
     * @param  $response string
     * @param  $code string
     */
    public function __construct($response, $code)
    {
        $this->response = $response;
        $this->code = $code;
    }

}

