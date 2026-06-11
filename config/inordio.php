<?php

return [

    /**
     * Pin this app instance to a single tenant (dedicated/egg deployments).
     * When set, tenancy is initialized to this tenant on every request and
     * users belonging to any other tenant are rejected. When null, the
     * tenant is resolved from the authenticated user (shared instance).
     */
    'tenant_id' => env('TENANT_ID'),

];
