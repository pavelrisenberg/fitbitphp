<?php
/**
 * Fitbit API communication exception
 *
 */
 
namespace FitBit;

class Exception extends \Exception
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