<?php

/*
|--------------------------------------------------------------------------
| Role-based abilities
|--------------------------------------------------------------------------
|
| Each ability maps to the roles allowed to perform it. Abilities are
| registered as Gates in AppServiceProvider and enforced on every route via
| the "can:" middleware and in policies.
|
| Administrators (Administrator / Inventory Officer) are granted every ability
| through Gate::before, so only the requester's abilities are listed here.
| An empty list means "administrators only".
|
*/

return [
    'items.view' => ['requester'],
    'items.manage' => [],
    'masterdata.manage' => [],

    'stockcards.view' => [],
    'stock.receive' => [],
    'stock.adjust' => [],
    'stock.reverse' => [],

    'requisitions.create' => ['requester'],
    'requisitions.view-all' => [],
    'requisitions.forward' => [],
    'requisitions.approve' => [],
    'requisitions.issue' => [],

    'issuances.view' => [],
    'reports.view' => [],
    'notifications.send' => [],

    'users.manage' => [],
    'audit.view' => [],
    'settings.manage' => [],
];
