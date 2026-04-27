<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Modules\Cms\Providers\CmsServiceProvider;
use Modules\Contact\Providers\ContactServiceProvider;
use Modules\Orders\Providers\OrdersServiceProvider;
use Modules\Pim\Providers\PimServiceProvider;

return [
    AppServiceProvider::class,
    CmsServiceProvider::class,
    ContactServiceProvider::class,
    OrdersServiceProvider::class,
    PimServiceProvider::class,
];
