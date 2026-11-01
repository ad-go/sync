<?php

declare(strict_types=1);

/**
 * Test bootstrap of ad-go/sync: the core classes run against in-memory SQLite without booting an application.
 */

namespace {
    // The core classes only need a database connection; no CI4 application is booted.
    require dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Common.php';
}

// Minimal stand-ins for the app-level config classes the framework looks up.
namespace Config {
    class Modules extends \CodeIgniter\Modules\Modules
    {
        public $enabled = false;
    }

    class Feature extends \CodeIgniter\Config\BaseConfig
    {
        public bool $limitZeroAsAll = true;
        public bool $autoRoutesImproved = true;
        public bool $oldFilterOrder = false;
        public bool $multipleFilters = false;
        public bool $views = true;
    }
}

namespace {
    // Database queries trigger the 'DBQuery' event, whose initialisation needs a full app.
    // Mark the event system as initialised so unit tests can run without one.
    (new \ReflectionProperty(\CodeIgniter\Events\Events::class, 'initialized'))->setValue(null, true);
}
