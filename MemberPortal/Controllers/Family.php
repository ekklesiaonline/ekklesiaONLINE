<?php

namespace MemberPortal\Controllers;

/**
 * Singular naming adapter for legacy Families controller.
 *
 * Extends FamiliesPages so every `Family::*` route string resolves the
 * page endpoints (index/view/store/update/delete) as well as the member
 * operations inherited from Families.
 */
class Family extends FamiliesPages {}
