## FitBitPHP ##

Basic wrapper for OAuth-based [FitBit](http://fitbit.com) [REST API](http://dev.fitbit.com), which have just launched in BETA and in rapid development. Seek more information on API developments at [dev.fitbit.com](http://dev.fitbit.com).

Library is in BETA as well as the API, so still could be buggy. We're looking forward to update library as API moves forward, doing best not to break backward compatibility. That being said, feel free to fork, add features and send pull request to us if you need more awesomness right now, we'll be happy to include them if well done.

**Current notes:**

 * *Subscriptions*: Library has basic methods to add/delete subscriptions, unfortunately it's your headache to track the list and deploy endpoints for FitBit updates as well as register endpoints at [http://dev.fitbit.com](http://dev.fitbit.com). See [Subscriptions-API](http://wiki.fitbit.com/display/API/Subscriptions-API) for more thoughts on that,
 * *Unauthenticated calls*: for now all calls should be made on behalf of authorized user with his token credentials (via internal session tracking or tokens provided – see examples), looking forward to waive this for general reference calls like `searchFoods`, `getFoodUnits` etc. as API develops stable attitude in this respect.


## Usage ##

First, as always don't forget to register your application at http://dev.fitbit.com and obtain consumer key and secret for your application.

Library itself handles whole OAuth application authorization workflow for you as well as session tracking between page views. This could be used further to provide 'Sign with Fitbit' like feature (look at next code sample) or just to authorize application to act with FitBit API on user's behalf.

Example snippet on frontend could look like:

    <?php

    require 'fitbitphp.php'

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET);

    $fitbit->initSession('http://example.com/callback.php');
    $xml = $fitbit->getProfile();

    print_r($xml);

Note, that unconditional call to 'initSession' in each page will completely hide them from the eyes of unauthorized visitor. Don't be amazed, however, it's not a right way to make area private on your site. On the other hand, you could just track if user already authorized access to FitBit without any additional workflow, if it was not true:

    if($fitbit->sessionStatus() == 2)
        <you_are_authorized_user_yes_you_are>


Second, if you want to implement some API calls on user's behalf later (say daemon with no frontend), when you've already stored OAuth credentials somewhere, you could do exactly that:

	require 'fitbitphp.php'

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET);
    $fitbit->setOAuthDetails('token_stored_for_user', 'secret_stored_for_user');

    $xml = $fitbit->getProfile();

    print_r($xml);


**Note.** By default, all requests are made in respect of resources of authorized user, however you can use `setUser` method to set another user for next calls, but this would work only for resources/transactions that are available on behalf of authorized user (i.e. to fetch public user profiles).



## Changelog ##

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
