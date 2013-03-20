## FitbitPHP ##

Basic wrapper for OAuth-based [FitBit](http://fitbit.com) [REST API](http://dev.fitbit.com), which have just launched in BETA and in rapid development. Seek more information on API developments at [dev.fitbit.com](http://dev.fitbit.com).

Library is in BETA as well as the API, so still could be buggy. We're looking forward to update library as API moves forward, doing best not to break backward compatibility. That being said, feel free to fork, add features and send pull request to us if you need more awesomness right now, we'll be happy to include them if well done.

**Current notes:**

 * *Subscriptions*: Library has basic methods to add/delete subscriptions, unfortunately it's your headache to track the list and deploy server endpoints to receive notifications from Fitbit as well as register them at [http://dev.fitbit.com](http://dev.fitbit.com). See [Subscriptions-API](http://wiki.fitbit.com/display/API/Subscriptions-API) for more thoughts on that,
 * *Unauthenticated calls*: Some methods of Fitbit API grant access to public resources without need for the complete OAuth workflow, `searchFoods` and `getActivities` are two good example of such endpoints. Nevertheless, this calls should be signed with Authentication header as usual, but access_token parameter is omitted from signature base string. In terms of FitbitPHP, you can make such calls, but you shouldn't use `initSession` (so access_token wouldn't be set) and should explicitly set the user to fetch resources from before the call (via `setUser`).  

## Note ##

There is also a port of the library (which might differ in terms of the version available) that Eli ported over to use a pure PHP implementation of OAuth, finally turning it into a composer install-able package. This might be helpful for those who having a trouble installing OAuth library package on their 3rd party hosting. His fork is located on his GitHub here: https://github.com/TheSavior/fitbitphp



## Usage ##

First, as always don't forget to register your application at http://dev.fitbit.com and obtain consumer key and secret for your application.

Library itself handles whole OAuth application authorization workflow for you as well as session tracking between page views. This could be used further to provide 'Sign with Fitbit' like feature (look at next code sample) or just to authorize application to act with FitBit API on user's behalf.

Example snippet on frontend could look like:

    <?php

    require 'fitbitphp.php';

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET);

    $fitbit->initSession('http://example.com/callback.php');
    $xml = $fitbit->getProfile();

    print_r($xml);

Note, that unconditional call to 'initSession' in each page will completely hide them from the eyes of unauthorized visitor. Don't be amazed, however, it's not a right way to make area private on your site. On the other hand, you could just track if user already authorized access to FitBit without any additional workflow, if it was not true:

    if($fitbit->sessionStatus() == 2)
        <you_are_authorized_user_yes_you_are>


Second, if you want to implement some API calls on user's behalf later (say daemon with no frontend), when you've already stored OAuth credentials somewhere, you could do exactly that:

    require 'fitbitphp.php';

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET);
    $fitbit->setOAuthDetails('token_stored_for_user', 'secret_stored_for_user');

    $xml = $fitbit->getProfile();

    print_r($xml);


**Note.** By default, all requests are made to work with resources of authorized user (viewer), however you can use `setUser` method to set another user, this would work only for several endpoints, which grant access to resources of other users and only if that user granted permissions to access his data ("Friends" or "Anyone").

If you want to fetch data without complete OAuth workflow, only using consumer_key without access_token, you can do that also (check which endpoints are okey with such calls on Fitbit API documentation):

    require 'fitbitphp.php';

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET);

    $fitbit->setUser('XXXXXX');
    $xml = $fitbit->getProfile();

    print_r($xml);


## Changelog ##

* Version 0.72: 20 March, 2013:
   * Bugs: minor bugfixes to put this thing together
* Version 0.71: 02 April, 2012:
	* API+: getIntradayTimeSeries
    * API+: setResponseFormat
	* Improvement: support for json response format
    * Bugs: default parameters for getWater and getHeartRate
	* Bugs: minor bugfixes to put this thing together
    * Bugs: minor bugfixes to put this thing together
* Version 0.70: 09 December, 2011:
    * API+: getHeartRate, logHeartRate, deleteHeartRate (Heart logging)
    * API+: Additional resources in getTimeSeries (clean activities from the Tracker)
* Version 0.69: 23 November, 2011:
    * API+: getBloodPressure, logBloodPressure, deleteBloodPressure (BP logging)
    * API+: getGlucose, logGlucose (Glucose logging)
    * API+: activityStats (Lifetime achievements)
    * API+: browseActivities (Activities catalog)
    * API+: Additional resources in getTimeSeries (sleep and water)
    * Improvement: invalid XML responses handling
* Version 0.68: 17 October, 2011:
    * Bugs: minor bugfixes to put this thing together
    * API+: new getTimeSeries resources for Fitbit Ultra
* Version 0.67: 19 September, 2011:
    * API+: getFood
* Version 0.66: 06 September, 2011:
    * API+: support for creating custom activities in logActivity
    * API+: support for creating orphan log entries in logFood
* Version 0.65: 04 August, 2011:
    * Bugs: minor bug fixes to put this thing together
* Version 0.64: 29 July, 2011:
    * Bugs: minor bug fixes to put this thing together
* Version 0.63: 22 July, 2011:
    * API+: new method client_customCall to make requests on behalf of consumer_key only
* Version 0.62: 20 July, 2011:
    * API+: new endpoint for rate limiting status
* Version 0.61: 14 July, 2011:
    * Improvement: new Exceptions handling
    * Deprecated: getDevice
* Version 0.60: 11 July, 2011:
    * API+: getSubscriptions
    * Deprecated: addSubscription, deleteSubscription no longer accept $userId
* Version 0.59: 29 June, 2011:
    * Bugs: bug fixes
    * Deprecated: getProfile no longer accept $userId, use setUser instead
* Version 0.58: 23 June, 2011:
    * API+: getWater, logWater, deleteWater
    * API+: getSleep, logSleep, deleteSleep
* Version 0.57: 06 June, 2011:
    * API+: createFood
    * API+: updateProfile
    * API+: getFriends
    * API+: getFriendsLeaderboard
    * API+: inviteFriend, acceptFriend, rejectFriend
    * Bug: now all calls during OAuth handshake are made through https
    * Bug: en_GB unit system
* Version 0.56: 29 May, 2011:
    * Bug: Search-Foods
    * API+: Added getMeals endpoint to fetch meal sets
* Version 0.55: 22 May, 2011:
    * Update: Update to reflect new document format in Time-Series
* Version 0.54: 29 April, 2011:
    * API+: Added getBody endpoint for body measurements
* Version 0.53: 26 April, 2011:
    * Bug: Correct path for getActivity endpoint
* Version 0.52: 14 April, 2011:
    * Bug: Added X-Fitbit-Client-Version header as a workaround for Fitbit API unit system bug
* Version 0.51: 04 April, 2011:
    * API+: Added manualCalories and distanceUnit to logActivity()
* Version 0.5: 29 March, 2011:
    * Initial commit
