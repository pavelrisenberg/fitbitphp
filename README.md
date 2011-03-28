## FitBitPHP ##

Library provides basic wrapper for OAuth-based [FitBit](http://fitbit.com) [REST API](http://dev.fitbit.com), which just launched in BETA and in rapid development. Seek more information on API developments at [dev.fitbit.com](http://dev.fitbit.com).

Library is in BETA as well as API and still could be buggy. We're looking forward to update library as API moves forward, hopefully it will not break backward compatibility. That being said, feel free to fork, add features and send pull request to us if you need them right now, we'll be happy to include them if well done.

Current notes:

 * setMetric() method provides a way to select preferred unit system (USA, UK, Metric by default), however still buggy on FitBit side, which could lead to several bugs,
 * Library has basic methods to add/delete subscriptions, unfortunately it's your headache to track them and deploy endpoints for FitBit updates as well as register endpoints at [http://dev.fitbit.com](http://dev.fitbit.com). See [Subscriptions-API](http://wiki.fitbit.com/display/API/Subscriptions-API) for more.


## Usage ##

First, don't forget to register your application at http://dev.fitbit.com and obtain cinsumer KEY and SECRET for your application.

Library itself handles all OAuth application authorization workflow for you as well as session tracking. This could be used further to provide 'Sign with Fitbit' like feature or just to authorize application to act with FitBit on user's behalf.

Example use case on frontend could look like:

    <?php

	require 'fitbitphp.php'

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET);

    $fitbit->initSession('http://example.com/callback');
    $xml = $fitbit->getProfile();

    print_r($xml);

Note, that unconditional call to 'initSession' would completely hide your page from the eyes of unauthorized visitor. You could also track if user already authorized FitBit access without automatic authorization start if not:

    if($fitbit->sessionStatus())
        <authorized user>


Secondly, if you want to implement some API calls on user's behalf later, when you've already stored OAuth credentials somewhere, you could do exactly that:

	require 'fitbitphp.php'

    $fitbit = new FitBitPHP(FITBIT_KEY, FITBIT_SECRET, DEBUG, VERSION);
    $fitbit->setOAuthDetails('token_stored_for_user', 'secret_stored_for_user');

    $xml = $fitbit->getProfile();

    print_r($xml);

	

## Changelog ##

* Version 0.5: 28 March, 2011:
   * Initial commit
