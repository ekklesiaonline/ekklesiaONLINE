<?php

return [
    // Access control messages
    'accessDeniedProvinceRequired' => 'Access denied. Province admin privileges required.',
    'accessDenied' => 'Access denied',
    'scopeNotConfigured' => 'Your province assignment is missing or invalid. Contact a system administrator.',
    'healthThreshold' => 'Dioceses shown need attention based on a {0}-day service-reporting threshold.',
    'noHealthIssues' => 'No dioceses currently need attention.',
    'eventCreated' => 'Province event created successfully.',
    'eventUpdated' => 'Province event updated successfully.',
    'eventDeleted' => 'Province event deleted successfully.',
    'eventSaveFailed' => 'Unable to save the province event. Please check the parish assignment.',
    'eventScope' => 'Scope',
    'calendarProvinceLabel' => 'Province-wide',
    'calendarNationalLabel' => 'National',
    'organizerLabel' => 'Organizer',
    'hostParishLabel' => 'Host parish',
    'manageCalendars' => 'Manage Calendars',

    // No parishes message
    'noParishesHeading' => 'No Parishes In Province Scope',
    'noParishesMessage' => 'No parishes are available for the selected province scope. Please review province and diocese assignments.',

    // Success messages
    'dioceseCreatedSuccess' => 'Diocese created successfully.',
    'userCreatedSuccess' => 'User created successfully.',

    // Error messages
    'validationError' => 'Validation error',
    'errorPrefix' => 'Error',
    'failedCreateDiocese' => 'Failed to create diocese',
    'failedCreateUser' => 'Failed to create user',
    'bulkDeleteDiocesesSelectAtLeastOne' => 'Select at least one diocese to delete.',
    'bulkDeleteDiocesesResult' => 'Bulk diocese delete completed. Deleted: {0}, Skipped: {1}.',
];
