<?php
/**
 * FitbitPHP v.0.72. Basic Fitbit API wrapper for PHP using OAuth
 *
 * Note: Library is in beta and provided as-is. We hope to add features as API grows, however
 *       feel free to fork, extend and send pull requests to us.
 *
 * - https://github.com/heyitspavel/fitbitphp
 *
 *
 * Date: 2013/03/20
 * Requires OAuth 1.0.0, SimpleXML
 * @version 0.72 ($Id$)
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
    protected $oauthToken, $oauthSecret;

    protected $responseFormat;

    protected $userId = '-';

    protected $metric = 0;
    protected $userAgent = 'FitbitPHP 0.72';
    protected $debug;

    protected $clientDebug;


    /**
     * @param string $consumer_key Application consumer key for Fitbit API
     * @param string $consumer_secret Application secret
     * @param int $debug Debug mode (0/1) enables OAuth internal debug
     * @param string $user_agent User-agent to use in API calls
     * @param string $response_format Response format (json or xml) to use in API calls
     */
    public function __construct($consumer_key, $consumer_secret, $debug = 1, $user_agent = null, $response_format = 'xml')
    {
        $this->initUrls();

        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
        $this->oauth = new OAuth($consumer_key, $consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);

        $this->debug = $debug;
        if ($debug)
            $this->oauth->enableDebug();

        if (isset($user_agent))
            $this->userAgent = $user_agent;

        $this->responseFormat = $response_format;
    }


    /**
     * @param string $consumer_key Application consumer key for Fitbit API
     * @param string $consumer_secret Application secret
     */
    public function reinit($consumer_key, $consumer_secret)
    {

        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;

        $this->oauth = new OAuth($consumer_key, $consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);

        if ($this->debug)
            $this->oauth->enableDebug();
    }


    /**
     * @param string $apiHost API host, i.e. api.fitbit.com (do you know any others?)
     * @param string $authHost Auth host, i.e. www.fitbit.com
     */
    public function setEndpointBase($apiHost, $authHost, $https = true, $httpsApi = false)
    {
        $this->apiHost = $apiHost;
        $this->authHost = $authHost;

        $this->initUrls($https, $httpsApi);
    }

    private function initUrls($https = true, $httpsApi = false)
    {

        if ($httpsApi)
            $this->baseApiUrl = 'https://' . $this->apiHost . '/1/';
        else
            $this->baseApiUrl = 'http://' . $this->apiHost . '/1/';

        if ($https) {
            $this->authUrl = 'https://' . $this->authHost . '/oauth/authorize';
            $this->requestTokenUrl = 'https://' . $this->apiHost . '/oauth/request_token';
            $this->accessTokenUrl = 'https://' . $this->apiHost . '/oauth/access_token';
        } else {
            $this->authUrl = 'http://' . $this->authHost . '/oauth/authorize';
            $this->requestTokenUrl = 'http://' . $this->apiHost . '/oauth/request_token';
            $this->accessTokenUrl = 'http://' . $this->apiHost . '/oauth/access_token';
        }
    }

    /**
     * @return OAuth debugInfo object for previous call. Debug should be enabled in __construct
     */
    public function oauthDebug()
    {
        return $this->oauth->debugInfo;
    }

    /**
     * @return OAuth debugInfo object for previous client_customCall. Debug should be enabled in __construct
     */
    public function client_oauthDebug()
    {
        return $this->clientDebug;
    }


    /**
     * Returns Fitbit session status for frontend (i.e. 'Sign in with Fitbit' implementations)
     *
     * @return int (0 - no session, 1 - just after successful authorization, 2 - session exist)
     */
    public static function sessionStatus()
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
     * Initialize session. Inits OAuth session, handles redirects to Fitbit login/authorization if needed
     *
     * @param  $callbackUrl Callback for 'Sign in with Fitbit'
     * @param  $cookie Use persistent cookie for authorization, or session cookie only
     * @return int (1 - just after successful authorization, 2 - if session already exist)
     */
    public function initSession($callbackUrl, $cookie = true)
    {

        $session = session_id();
        if (empty($session)) {
            session_start();
        }

        if (empty($_SESSION['fitbit_Session']))
            $_SESSION['fitbit_Session'] = 0;


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
     * Reset session
     *
     * @return void
     */
    public function resetSession()
    {
        $_SESSION['fitbit_Session'] = 0;
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
        $this->oauthToken = $token;
        $this->oauthSecret = $secret;

        $this->oauth->setToken($this->oauthToken, $this->oauthSecret);
    }

    /**
     * Get OAuth token
     *
     * @return string
     */
    public function getOAuthToken()
    {
        return $this->oauthToken;
    }

    /**
     * Get OAuth secret
     *
     * @return string
     */
    public function getOAuthSecret()
    {
        return $this->oauthSecret;
    }


    /**
     * Set Fitbit response format for future API calls
     *
     * @param  $response_format 'json' or 'xml'
     * @return void
     */
    public function setResponseFormat($response_format)
    {
        $this->responseFormat = $response_format;
    }


    /**
     * Set Fitbit userId for future API calls
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
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getProfile()
    {
        $headers = $this->getHeaders();

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/profile." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
     * @return mixed SimpleXMLElement or the value encoded in json as an object
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/profile." . $this->responseFormat,
                                $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);                
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user activities for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getActivities($date, $dateStr = null)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/activities/date/" . $dateStr . "." . $this->responseFormat,
                                null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user recent activities
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getRecentActivities()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities/recent." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user frequent activities
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFrequentActivities()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities/frequent." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);
            
            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user favorite activities
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFavoriteActivities()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities/favorite." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);
            
            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
     * @param string $calories Manual calories to override Fitbit estimate
     * @param string $distance Distance in km/miles (as set with setMetric)
     * @param string $distanceUnit Distance unit string (see http://wiki.fitbit.com/display/API/API-Distance-Unit)
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logActivity($date, $activityId, $duration, $calories = null, $distance = null, $distanceUnit = null, $activityName = null)
    {
        $distanceUnits = array('Centimeter', 'Foot', 'Inch', 'Kilometer', 'Meter', 'Mile', 'Millimeter', 'Steps', 'Yards');

        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['startTime'] = $date->format('H:i');
        if (isset($activityName)) {
            $parameters['activityName'] = $activityName;
            $parameters['manualCalories'] = $calories;
        } else {
            $parameters['activityId'] = $activityId;
            if (isset($calories))
                $parameters['manualCalories'] = $calories;
        }
        $parameters['durationMillis'] = $duration;
        if (isset($distance))
            $parameters['distance'] = $distance;
        if (isset($distanceUnit) && in_array($distanceUnit, $distanceUnits))
            $parameters['distanceUnit'] = $distanceUnit;

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);
            
            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities/log/favorite/" . $id . "." . $this->responseFormat,
                                null, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/activities/log/favorite/" . $id . ".xml",
                                null, OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get full description of specific activity
     *
     * @throws FitBitException
     * @param  string $id Activity log Id
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getActivity($id)
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "activities/" . $id . "." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get a tree of all valid Fitbit public activities as well as private custom activities the user createds
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function browseActivities()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "activities." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);
            
            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user foods for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFoods($date, $dateStr = null)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/foods/log/date/" . $dateStr . "." . $this->responseFormat,
                                null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user recent foods
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getRecentFoods()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/recent." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user frequent foods
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFrequentFoods()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/frequent." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user favorite foods
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFavoriteFoods()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/favorite." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logFood($date, $foodId, $mealTypeId, $unitId, $amount, $foodName = null, $calories = null, $brandName = null, $nutrition = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        if (isset($foodName)) {
            $parameters['foodName'] = $foodName;
            $parameters['calories'] = $calories;
            if (isset($brandName))
                $parameters['brandName'] = $brandName;
            if (isset($nutrition)) {
                foreach ($nutrition as $i => $value) {
                    $parameters[$i] = $nutrition[$i];
                }
            }
        } else {
            $parameters['foodId'] = $foodId;
        }
        $parameters['mealTypeId'] = $mealTypeId;
        $parameters['unitId'] = $unitId;
        $parameters['amount'] = $amount;

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user food
     *
     * @throws FitBitException
     * @param string $id Food log id
     * @return bool
     */
    public function deleteFood($id)
    {
        $headers = $this->getHeaders();

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/favorite/" . $id . "." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/favorite/" . $id . ".xml",
                                null, OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user meal sets
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getMeals()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/meals." . $this->responseFormat,
                                null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get food units library
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFoodUnits()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "foods/units." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Search for foods in foods database
     *
     * @throws FitBitException
     * @param string $query Search query
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function searchFoods($query)
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "foods/search." . $this->responseFormat . "?query=" . rawurlencode($query), null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get description of specific food from food db (or private for the user)
     *
     * @throws FitBitException
     * @param  string $id Food Id
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFood($id)
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "foods/" . $id . "." . $this->responseFormat, null,
                                OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
     * @return mixed SimpleXMLElement or the value encoded in json as an object
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "foods." . $this->responseFormat, $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);
            if (!$xml)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user water log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getWater($date, $dateStr = null)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/water/date/" . $dateStr . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user water
     *
     * @throws FitBitException
     * @param DateTime $date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @param string $amount Amount in ml/fl oz (as set with setMetric) or waterUnit
     * @param string $waterUnit Water Unit ("ml", "fl oz" or "cup")
     * @return mixed SimpleXMLElement or the value encoded in json as an object
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/water." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/foods/log/water/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user sleep log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getSleep($date, $dateStr = null)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/sleep/date/" . $dateStr . "." . $this->responseFormat,
                                null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user sleep
     *
     * @throws FitBitException
     * @param DateTime $date Sleep date and time (set proper timezone, which could be fetched via getProfile)
     * @param string $duration Duration millis
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logSleep($date, $duration)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['startTime'] = $date->format('H:i');
        $parameters['duration'] = $duration;

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/sleep." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/sleep/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user body measurements
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getBody($date, $dateStr = null)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/body/date/" . $dateStr . "." . $this->responseFormat,
                                null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }

    /**
     * Log user body measurements
     *
     * @throws FitBitException
     * @param string $weight Float number. For en_GB units, provide floating number of stones (i.e. 11 st. 4 lbs = 11.2857143)
     * @param string $fat Float number
     * @param string $bicep Float number
     * @param string $calf Float number
     * @param string $chest Float number
     * @param string $forearm Float number
     * @param string $hips Float number
     * @param string $neck Float number
     * @param string $thigh Float number
     * @param string $waist Float number
     * @param DateTime $date Date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */

    public function logBody($date, $weight = null, $fat = null, $bicep = null, $calf = null, $chest = null, $forearm = null, $hips = null, $neck = null, $thigh = null, $waist = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');

        if (isset($weight))
            $parameters['weight'] = $weight;
        if (isset($fat))
            $parameters['fat'] = $fat;
        if (isset($bicep))
            $parameters['bicep'] = $bicep;
        if (isset($calf))
            $parameters['calf'] = $calf;
        if (isset($chest))
            $parameters['chest'] = $chest;
        if (isset($forearm))
            $parameters['forearm'] = $forearm;
        if (isset($hips))
            $parameters['hips'] = $hips;
        if (isset($neck))
            $parameters['neck'] = $neck;
        if (isset($thigh))
            $parameters['thigh'] = $thigh;
        if (isset($waist))
            $parameters['waist'] = $waist;

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/body." . $this->responseFormat,
                                $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user weight
     *
     * @throws FitBitException
     * @param string $weight Float number. For en_GB units, provide floating number of stones (i.e. 11 st. 4 lbs = 11.2857143)
     * @param DateTime $date If present, log entry date, now by default (set proper timezone, which could be fetched via getProfile)
     * @return bool
     */
    public function logWeight($weight, $date = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['weight'] = $weight;
        if (isset($date))
            $parameters['date'] = $date->format('Y-m-d');

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/body/weight." . $this->responseFormat,
                                $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user blood pressure log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getBloodPressure($date, $dateStr)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/bp/date/" . $dateStr . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user blood pressure
     *
     * @throws FitBitException
     * @param DateTime $date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @param string $systolic Systolic measurement
     * @param string $diastolic Diastolic measurement
     * @param DateTime $time Time of the measurement (set proper timezone, which could be fetched via getProfile)
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logBloodPressure($date, $systolic, $diastolic, $time = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['systolic'] = $systolic;
        $parameters['diastolic'] = $diastolic;
        if (isset($time))
            $parameters['time'] = $time->format('H:i');

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/bp." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user blood pressure record
     *
     * @throws FitBitException
     * @param string $id Blood pressure log id
     * @return bool
     */
    public function deleteBloodPressure($id)
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/bp/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user glucose log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getGlucose($date, $dateStr)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/glucose/date/" . $dateStr . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }

    /**
     * Log user glucose and HbA1c
     *
     * @throws FitBitException
     * @param DateTime $date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @param string $tracker Name of the glucose tracker
     * @param string $glucose Glucose measurement
     * @param string $hba1c Glucose measurement
     * @param DateTime $time Time of the measurement (set proper timezone, which could be fetched via getProfile)
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logGlucose($date, $tracker, $glucose, $hba1c = null, $time = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['tracker'] = $tracker;
        $parameters['glucose'] = $glucose;
        if (isset($hba1c))
            $parameters['hba1c'] = $hba1c;
        if (isset($time))
            $parameters['time'] = $time->format('H:i');

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/glucose." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user heart rate log entries for specific date
     *
     * @throws FitBitException
     * @param  DateTime $date
     * @param  String $dateStr
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getHeartRate($date, $dateStr = null)
    {
        $headers = $this->getHeaders();
        if (!isset($dateStr)) {
            $dateStr = $date->format('Y-m-d');
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/heart/date/" . $dateStr . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Log user heart rate
     *
     * @throws FitBitException
     * @param DateTime $date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @param string $tracker Name of the glucose tracker
     * @param string $heartRate Heart rate measurement
     * @param DateTime $time Time of the measurement (set proper timezone, which could be fetched via getProfile)
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logHeartRate($date, $tracker, $heartRate, $time = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['tracker'] = $tracker;
        $parameters['heartRate'] = $heartRate;
        if (isset($time))
            $parameters['time'] = $time->format('H:i');

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/heart." . $this->responseFormat, $parameters,
                                OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Delete user heart rate record
     *
     * @throws FitBitException
     * @param string $id Heart rate log id
     * @return bool
     */
    public function deleteHeartRate($id)
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/heart/" . $id . ".xml", null,
                                OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Launch TimeSeries requests
     *
     * Allowed types are:
     *            'caloriesIn', 'water'
     *
     *            'caloriesOut', 'steps', 'distance', 'floors', 'elevation'
     *            'minutesSedentary', 'minutesLightlyActive', 'minutesFairlyActive', 'minutesVeryActive',
     *            'activeScore', 'activityCalories',
     *
     *            'tracker_caloriesOut', 'tracker_steps', 'tracker_distance', 'tracker_floors', 'tracker_elevation'
     *            'tracker_activeScore'
     *
     *            'startTime', 'timeInBed', 'minutesAsleep', 'minutesAwake', 'awakeningsCount',
     *            'minutesToFallAsleep', 'minutesAfterWakeup',
     *            'efficiency'
     *
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
            case 'water':
                $path = '/foods/log/water';
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
            case 'floors':
                $path = '/activities/log/floors';
                break;
            case 'elevation':
                $path = '/activities/log/elevation';
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

            case 'tracker_caloriesOut':
                $path = '/activities/log/tracker/calories';
                break;
            case 'tracker_steps':
                $path = '/activities/log/tracker/steps';
                break;
            case 'tracker_distance':
                $path = '/activities/log/tracker/distance';
                break;
            case 'tracker_floors':
                $path = '/activities/log/tracker/floors';
                break;
            case 'tracker_elevation':
                $path = '/activities/log/tracker/elevation';
                break;
            case 'tracker_activeScore':
                $path = '/activities/log/tracker/activeScore';
                break;

            case 'startTime':
                $path = '/sleep/startTime';
                break;
            case 'timeInBed':
                $path = '/sleep/timeInBed';
                break;
            case 'minutesAsleep':
                $path = '/sleep/minutesAsleep';
                break;
            case 'awakeningsCount':
                $path = '/sleep/awakeningsCount';
                break;
            case 'minutesAwake':
                $path = '/sleep/minutesAwake';
                break;
            case 'minutesToFallAsleep':
                $path = '/sleep/minutesToFallAsleep';
                break;
            case 'minutesAfterWakeup':
                $path = '/sleep/minutesAfterWakeup';
                break;
            case 'efficiency':
                $path = '/sleep/efficiency';
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
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . $path . '/date/' . (is_string($basedate) ? $basedate : $basedate->format('Y-m-d')) . "/" . (is_string($to_period) ? $to_period : $to_period->format('Y-m-d')) . ".json", null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $json = json_decode($response);
            $path = str_replace('/', '-', substr($path, 1));
            return $json->$path;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Launch IntradayTimeSeries requests
     *
     * Allowed types are:
     *            'caloriesOut', 'steps', 'floors', 'elevation'
     *
     * @throws FitBitException
     * @param string $type
     * @param  $date DateTime or 'today'
     * @param  $start_time DateTime
     * @param  $end_time DateTime
     * @return object
     */
    public function getIntradayTimeSeries($type, $date, $start_time = null, $end_time = null)
    {
        switch ($type) {
            case 'caloriesOut':
                $path = '/activities/log/calories';
                break;
            case 'steps':
                $path = '/activities/log/steps';
                break;
            case 'floors':
                $path = '/activities/log/floors';
                break;
            case 'elevation':
                $path = '/activities/log/elevation';
                break;

            default:
                return false;
        }


        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-" . $path . "/date/" . (is_string($date) ? $date : $date->format('Y-m-d')) . "/1d" . ((!empty($start_time) && !empty($end_time)) ? "/time/" . $start_time->format('H:i') . "/" . $end_time->format('H:i') : "") . ".json", null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $json = json_decode($response);
            $path = str_replace('/', '-', substr($path, 1)) . "-intraday";
            return (isset($json->$path)) ? $json->$path : NULL;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user's activity statistics (lifetime statistics from the tracker device and total numbers including the manual activity log entries)
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getActivityStats()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/activities." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get list of devices and their properties
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getDevices()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/devices." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }

    /**
     * Get user friends
     *
     * @throws FitBitException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFriends()
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/" . $this->userId . "/friends." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get user's friends leaderboard
     *
     * @throws FitBitException
     * @param string $period Depth ('7d' or '30d')
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getFriendsLeaderboard($period = '7d')
    {
        $headers = $this->getHeaders();
        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/friends/leaders/" . $period . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Invite user to become friends
     *
     * @throws FitBitException
     * @param string $userId Invite user by id
     * @param string $email Invite user by email address (could be already Fitbit member or not)
     * @return bool
     */
    public function inviteFriend($userId = null, $email = null)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        if (isset($userId))
            $parameters['invitedUserId'] = $userId;
        if (isset($email))
            $parameters['invitedUserEmail'] = $email;

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/friends/invitations." . $this->responseFormat, $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '201')) {
            return true;
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Accept invite to become friends from user
     *
     * @throws FitBitException
     * @param string $userId Id of the inviting user
     * @return bool
     */
    public function acceptFriend($userId)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['accept'] = 'true';

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/friends/invitations/" . $userId . "." . $this->responseFormat, $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Accept invite to become friends from user
     *
     * @throws FitBitException
     * @param string $userId Id of the inviting user
     * @return bool
     */
    public function rejectFriend($userId)
    {
        $headers = $this->getHeaders();
        $parameters = array();
        $parameters['accept'] = 'false';

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/friends/invitations/" . $userId . "." . $this->responseFormat, $parameters, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            $response = $this->parseResponse($response);

            if (!$response)
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
            else
                throw new FitBitException($responseInfo['http_code'], $response->message, 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-" . $path . "/apiSubscriptions/" . $id . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_POST, $headers);
        } catch (Exception $E) {
        }

        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200') || !strcmp($responseInfo['http_code'], '201')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-" . $path . "/apiSubscriptions/" . $id . ".xml", null, OAUTH_HTTP_METHOD_DELETE, $headers);
        } catch (Exception $E) {
        }

        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '204')) {
            return true;
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
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

        try {
            $this->oauth->fetch($this->baseApiUrl . "user/-/apiSubscriptions." . $this->responseFormat, null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $response = $this->parseResponse($response);

            if ($response)
                return $response;
            else
                throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
    }


    /**
     * Get CLIENT+VIEWER and CLIENT rate limiting quota status
     *
     * @throws FitBitException
     * @return FitBitRateLimiting
     */
    public function getRateLimit()
    {
        $headers = $this->getHeaders();

        try {
            $this->oauth->fetch($this->baseApiUrl . "account/clientAndViewerRateLimitStatus.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xmlClientAndUser = simplexml_load_string($response);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
        try {
            $this->oauth->fetch($this->baseApiUrl . "account/clientRateLimitStatus.xml", null, OAUTH_HTTP_METHOD_GET, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        if (!strcmp($responseInfo['http_code'], '200')) {
            $xmlClient = simplexml_load_string($response);
        } else {
            throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
        }
        return new FitBitRateLimiting(
            $xmlClientAndUser->rateLimitStatus->remainingHits,
            $xmlClient->rateLimitStatus->remainingHits,
            $xmlClientAndUser->rateLimitStatus->resetTime,
            $xmlClient->rateLimitStatus->resetTime,
            $xmlClientAndUser->rateLimitStatus->hourlyLimit,
            $xmlClient->rateLimitStatus->hourlyLimit
        );
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

        try {
            $this->oauth->fetch($this->baseApiUrl . $url, $parameters, $method, $headers);
        } catch (Exception $E) {
        }
        $response = $this->oauth->getLastResponse();
        $responseInfo = $this->oauth->getLastResponseInfo();
        return new FitBitResponse($response, $responseInfo['http_code']);
    }


    /**
     * Make custom call to any API endpoint, signed with consumer_key only (on behalf of CLIENT)
     *
     * @param string $url Endpoint url after '.../1/'
     * @param array $parameters Request parameters
     * @param string $method (OAUTH_HTTP_METHOD_GET, OAUTH_HTTP_METHOD_POST, OAUTH_HTTP_METHOD_PUT, OAUTH_HTTP_METHOD_DELETE)
     * @param array $userHeaders Additional custom headers
     * @return FitBitResponse
     */
    public function client_customCall($url, $parameters, $method, $userHeaders = array())
    {
        $OAuthConsumer = new OAuth($this->consumer_key, $this->consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);

        if ($debug)
            $OAuthConsumer->enableDebug();

        $headers = $this->getHeaders();
        $headers = array_merge($headers, $userHeaders);

        try {
            $OAuthConsumer->fetch($this->baseApiUrl . $url, $parameters, $method, $headers);
        } catch (Exception $E) {
        }
        $response = $OAuthConsumer->getLastResponse();
        $responseInfo = $OAuthConsumer->getLastResponseInfo();
        $this->clientDebug = print_r($OAuthConsumer->debugInfo, true);
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
    
    
    /**
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    private function parseResponse($response)
    {
        if ($this->responseFormat == 'xml')
            $response = (isset($response->errors)) ? $response->errors->apiError : simplexml_load_string($response);
        else if ($this->responseFormat == 'json')
            $response = (isset($response->errors)) ? $response->errors : json_decode($response);

        return $response;
    }


}


/**
 * Fitbit API communication exception
 *
 */
class FitBitException extends Exception
{
    public $fbMessage = '';
    public $httpcode;

    public function __construct($code, $fbMessage = null, $message = null)
    {

        $this->fbMessage = $fbMessage;
        $this->httpcode = $code;

        if (isset($fbMessage) && !isset($message))
            $message = $fbMessage;

        try {
            $code = (int)$code;
        } catch (Exception $E) {
            $code = 0;
        }

        parent::__construct($message, $code);
    }

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

/**
 * Wrapper for rate limiting quota
 *
 */
class FitBitRateLimiting
{
    public $viewer;
    public $viewerReset;
    public $viewerQuota;
    public $client;
    public $clientReset;
    public $clientQuota;

    public function __construct($viewer, $client, $viewerReset = null, $clientReset = null, $viewerQuota = null, $clientQuota = null)
    {
        $this->viewer = $viewer;
        $this->viewerReset = $viewerReset;
        $this->viewerQuota = $viewerQuota;
        $this->client = $client;
        $this->clientReset = $clientReset;
        $this->clientQuota = $clientQuota;
    }

}



