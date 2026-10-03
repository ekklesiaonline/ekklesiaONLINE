<?php

namespace Volunteer\Controllers;

/**
 * Singular naming adapter for legacy Volunteers controller.
 *
 * Extends VolunteerSchedules so every `Volunteer::*` route string resolves
 * the schedule endpoints as well as the team operations inherited from
 * Volunteers.
 */
class Volunteer extends VolunteerSchedules {}
