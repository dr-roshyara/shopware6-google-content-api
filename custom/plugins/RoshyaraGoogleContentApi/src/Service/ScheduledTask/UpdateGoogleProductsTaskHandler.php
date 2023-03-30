<?php declare(strict_types=1);

namespace Roshyara\GoogleContentApi\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class UpdateGoogleProductsTaskHandler extends ScheduledTaskHandler
{
    public static function getHandledMessages(): iterable
    {
        return [ UpdateGoogleProductsTask::class ];
    }

    public function run(): void
    {
        // file_put_contents('some/where/some/file.md', 'example');
	// php bin/console update:google-products;
	echo "test schedule\n";	
    }
}
