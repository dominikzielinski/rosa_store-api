<?php

declare(strict_types=1);

// Module RouteServiceProviders register their own groups.
// This file exists so `api: ...` in bootstrap/app.php works even when
// no root-level API routes are defined.
