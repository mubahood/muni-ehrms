<?php

namespace App\Support;

/**
 * Constants of the demo sandbox (App\Services\DemoSandbox). Demo rows are
 * marked is_demo; their simulated clock-ins carry this event source.
 */
class DemoManifest
{
    /** event_logs.source of simulated clock-ins */
    public const EVENT_SOURCE = 'demo';
}
