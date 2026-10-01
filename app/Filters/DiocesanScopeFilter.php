<?php

namespace App\Filters;

/**
 * Backward-compatible name for the canonical diocesan scope filter.
 *
 * The Diocese module rename made DioceseScopeFilter the configured class. Keep
 * this alias for older route declarations and documentation that still use
 * DiocesanScopeFilter; all enforcement remains in one implementation.
 */
class DiocesanScopeFilter extends DioceseScopeFilter
{
}
