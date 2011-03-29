## FitBitPHP ##

Boring basic wrapper for OAuth-based [FitBit](http://fitbit.com) [REST API](http://dev.fitbit.com), which have just launched in BETA and in rapid development. Seek more information on API developments at [dev.fitbit.com](http://dev.fitbit.com).

Library is in BETA as well as the API, so still could be buggy. We're looking forward to update library as API moves forward, doing best not to break backward compatibility. That being said, feel free to fork, add features and send pull request to us if you need more awesomness right now, we'll be happy to include them if well done.

**Current notes:**

 * *Units*: setMetric() method provides a way to select preferred unit system for request/response (USA, UK, Metric by default), however still buggy on FitBit side, which would lead to several bugs,
 * *Subscriptions*: Library has basic methods to add/delete subscriptions, unfortunately it's your headache to track the list and deploy endpoints for FitBit updates as well as register endpoints at [http://dev.fitbit.com](http://dev.fitbit.com). See [Subscriptions-API](http://wiki.fitbit.com/display/API/Subscriptions-API) for more thoughts on that,
 * *Un-authenticated calls*: for now all calls should be made on behalf of authorized user with his token credentials, looking forward to waive this for general calls like `searchFoods`, `getFoodUnits` etc. as API develops stable attitude in this respect.


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


**Note.** By default, all requests are made in respect of resources of authorized user, you cab use `setUser` method to set another user, but this would work only for resources/transactions that are available for public.
	

## Changelog ##

* Version 0.5: 29 March, 2011:
   * Initial commit
