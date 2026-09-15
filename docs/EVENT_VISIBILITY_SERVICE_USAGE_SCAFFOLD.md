# Event Visibility Service Usage Scaffold

## Purpose

Provide a single, repeatable controller pattern for future Event endpoints in MemberPortal and Diocesan modules.
Use App Services EventVisibilityService as the canonical policy engine for:

- event list visibility
- calendar visibility
- direct event access
- event management checks
- registration management checks

## Canonical Service

- Service class: App Services EventVisibilityService
- Service factory: Config Services eventVisibility

## Required Context Contract

Every controller using EventVisibilityService should build this context shape:

```php
[
    'user_id' => (int) auth()->id(),
    'parish_id' => (int) $operationalParishId,
    'diocese_id' => (int) $operationalDioceseId,
    'can_manage_diocese_scope' => (bool) $canManageDioceseScope,
]
```

## MemberPortal Controller Scaffold

```php
<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use Config\Services;

class Events extends BaseController
{
    protected $eventModel;
    protected $eventRegistrationModel;
    protected $eventVisibilityService;

    public function __construct()
    {
        $this->eventModel = model('EventModel');
        $this->eventRegistrationModel = model('EventRegistrationModel');
        $this->eventVisibilityService = Services::eventVisibility();
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('events.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = (int) $this->resolveDiocesanScopeId();

        $events = $this->eventModel->getParishEventsWithCounts($parishId, $dioceseId, null, null);
        $events = $this->eventVisibilityService->filterVisibleEvents($events, $this->getEventVisibilityContext());

        return view('MemberPortal\\Views\\Events/index', [
            'events' => $events,
        ]);
    }

    public function view(int $id)
    {
        if ($redirect = $this->requirePermission('events.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $event = $this->eventModel->getEventWithCounts($id);
        if (!is_array($event) || !$this->eventVisibilityService->isVisibleEvent($event, $this->getEventVisibilityContext())) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        return view('MemberPortal\\Views\\Events/view', ['event' => $event]);
    }

    private function getEventVisibilityContext(): array
    {
        return [
            'user_id' => (int) (auth()->id() ?? 0),
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'diocese_id' => (int) $this->resolveDiocesanScopeId(),
            'can_manage_diocese_scope' => auth()->user()->can('admin.diocese'),
        ];
    }
}
```

## Diocesan Controller Scaffold

```php
<?php

namespace Diocesan\Controllers;

use App\Controllers\BaseController;
use Config\Services;

class Events extends BaseController
{
    protected $eventModel;
    protected $eventVisibilityService;

    public function __construct()
    {
        $this->eventModel = model('EventModel');
        $this->eventVisibilityService = Services::eventVisibility();
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('events.view')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = (int) $this->resolveDiocesanScopeId();

        $events = $this->eventModel->getParishEventsWithCounts($parishId, $dioceseId, null, null);
        $events = $this->eventVisibilityService->filterVisibleEvents($events, $this->getEventVisibilityContext());

        return view('Diocesan\\Views\\Events/index', ['events' => $events]);
    }

    private function getEventVisibilityContext(): array
    {
        return [
            'user_id' => (int) (auth()->id() ?? 0),
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'diocese_id' => (int) $this->resolveDiocesanScopeId(),
            'can_manage_diocese_scope' => true,
        ];
    }
}
```

## Integration Checklist

1. Register module routes with permission filters first.
2. Fetch candidate rows from EventModel by scope.
3. Always apply EventVisibilityService filterVisibleEvents before rendering lists.
4. Always apply EventVisibilityService isVisibleEvent for direct event endpoints.
5. Use EventVisibilityService canManageEvent for edit and delete actions.
6. Use EventVisibilityService canManageRegistrationForEvent for registration status changes.
7. Do not re-implement visibility checks in controllers or models.

## Notes

- Keep EventModel focused on data retrieval only.
- Keep visibility and access decisions in EventVisibilityService only.
- If new audience levels are added, update EventVisibilityService first, then reuse without endpoint-specific rule forks.
