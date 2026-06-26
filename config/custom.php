<?php

return [
    'status_inactive' => 'Inactive',
    'status_active' => 'Active',
    'status' => [
        'Active',
        'Deleted',
        'Inactive',
    ],
    'user' => [
        'status_inactive' => 'Inactive',
        'status_active' => 'Active',
        'status' => [
            'Active',
            'Deleted',
            'Inactive',
            'Not Verified',
        ],
        'role_super_admin' => 'Super Admin',
        'role_admin' => 'Admin',
        'role_customer' => 'Customer',
        'roles' => [
            'Super Admin',
            'Admin',
            'Customer',
        ],
    ],
    'agency' => [
        'status_active' => 'Active',
        'status_suspended' => 'Suspended',
        'status' => [
            'Active',
            'Suspended',
        ],
    ],
    'account' => [
        'status_active' => 'Active',
        'status_suspended' => 'Suspended',
        'status' => [
            'Active',
            'Suspended',
        ],
        'role_owner' => 'Owner',
        'role_admin' => 'Admin',
        'role_user' => 'User',
        'roles' => [
            'Owner',
            'Admin',
            'User',
        ],
    ],
    'contact' => [
        'status_lead' => 'Lead',
        'status_customer' => 'Customer',
        'status_inactive' => 'Inactive',
        'status' => [
            'Lead',
            'Customer',
            'Inactive',
        ],
    ],
    'opportunity' => [
        'status_open' => 'Open',
        'status_won' => 'Won',
        'status_lost' => 'Lost',
        'status' => [
            'Open',
            'Won',
            'Lost',
        ],
    ],
    'job' => [
        'status_scheduled' => 'Scheduled',
        'status_in_progress' => 'In Progress',
        'status_completed' => 'Completed',
        'status_cancelled' => 'Cancelled',
        'status' => [
            'Scheduled',
            'In Progress',
            'Completed',
            'Cancelled',
        ],
    ],
    'automation' => [
        // Events the engine listens for. Each carries a "contact" in context.
        'event_contact_created' => 'contact.created',
        'event_opportunity_won' => 'opportunity.won',
        'event_job_completed' => 'job.completed',
        'events' => [
            'contact.created',
            'opportunity.won',
            'job.completed',
        ],
        // What an action can do.
        'action_add_tag' => 'add_tag',
        'action_set_contact_status' => 'set_contact_status',
        'action_create_job' => 'create_job',
        'actions' => [
            'add_tag',
            'set_contact_status',
            'create_job',
        ],
    ],
    'pipeline' => [
        // Seeded when a pipeline is created without explicit stages.
        'default_stages' => [
            'New',
            'Contacted',
            'Qualified',
            'Proposal',
            'Won',
            'Lost',
        ],
    ],
    'funnel' => [
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'status_draft' => 'Draft',
        'status' => [
            'Active',
            'Inactive',
            'Draft',
        ],
    ],
    'page' => [
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'status_draft' => 'Draft',
        'status' => [
            'Active',
            'Inactive',
            'Draft',
        ],
        'type_landing' => 'Landing',
        'types' => [
            'Landing',
            'Optin',
            'Sales',
            'Checkout',
            'Upsell',
            'Downsell',
            'ThankYou',
        ],
    ],
    'plan' => [
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'status' => [
            'Active',
            'Inactive',
        ],
        'interval_monthly' => 'Monthly',
        'interval_yearly' => 'Yearly',
        'intervals' => [
            'Monthly',
            'Yearly',
        ],
    ],
    'subscription' => [
        'status_pending' => 'Pending',
        'status_active' => 'Active',
        'status_cancelled' => 'Cancelled',
        'status_expired' => 'Expired',
        'status' => [
            'Pending',
            'Active',
            'Cancelled',
            'Expired',
        ],
    ],
];
