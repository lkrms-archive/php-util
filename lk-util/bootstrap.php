<?php declare(strict_types=1);

namespace Lkrms\LkUtil;

use Lkrms\Cli\CliApplication;
use Lkrms\LkUtil\Command\Generate\GenerateBuilder;
use Lkrms\LkUtil\Command\Generate\GenerateFacade;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntity;
use Lkrms\LkUtil\Command\Generate\GenerateSyncProvider;
use Lkrms\LkUtil\Command\Http\SendHttpRequest;
use Lkrms\Sync\Command\CheckSyncProviderHeartbeat;

$loader = require $_composer_autoload_path
    ?? dirname(__DIR__) . '/vendor/autoload.php';

$loader->addPsr4('Lkrms\\LkUtil\\', __DIR__);

(new CliApplication())
    ->resumeCache()
    ->logOutput()
    ->command(['generate', 'builder'], GenerateBuilder::class)
    ->command(['generate', 'facade'], GenerateFacade::class)
    ->command(['generate', 'sync', 'entity'], GenerateSyncEntity::class)
    ->command(['generate', 'sync', 'provider'], GenerateSyncProvider::class)
    ->command(['heartbeat'], CheckSyncProviderHeartbeat::class)
    ->command(['http', 'get'], SendHttpRequest::class)
    ->command(['http', 'head'], SendHttpRequest::class)
    ->command(['http', 'post'], SendHttpRequest::class)
    ->command(['http', 'put'], SendHttpRequest::class)
    ->command(['http', 'delete'], SendHttpRequest::class)
    ->command(['http', 'patch'], SendHttpRequest::class)
    ->runAndExit();
