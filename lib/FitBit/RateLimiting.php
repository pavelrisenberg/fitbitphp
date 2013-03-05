<?php  
/**
 * Wrapper for rate limiting quota
 *
 */
 
namespace FitBit;

class RateLimiting
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