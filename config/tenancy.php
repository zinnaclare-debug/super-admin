<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | Base domain for tenant subdomains. Example:
    | TENANCY_BASE_DOMAIN=yourdomain.com
    |
    | With this, school1.yourdomain.com resolves to tenant subdomain "school1".
    | Leave null to use fallback extraction for 3+ level hosts.
    |
    */
    'base_domain' => env('TENANCY_BASE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | Hosts that should not be treated as tenant subdomains.
    | Keep your root/admin/API hosts here.
    |
    */
    'central_domains' => array_values(array_filter(array_map(
        static fn ($domain) => strtolower(trim($domain)),
        explode(',', (string) env('CENTRAL_DOMAINS', 'localhost,127.0.0.1'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Require School Users On Tenant Subdomains
    |--------------------------------------------------------------------------
    |
    | When true, school users (school_admin/staff/student) must use a resolved
    | tenant subdomain. Super admins remain central-domain users.
    |
    */
    'require_subdomain_for_school_users' => (bool) env('TENANCY_REQUIRE_SUBDOMAIN', false),

    /*
    |--------------------------------------------------------------------------
    | Request Attribute Key
    |--------------------------------------------------------------------------
    */
    'request_key' => 'tenant_school',
];

