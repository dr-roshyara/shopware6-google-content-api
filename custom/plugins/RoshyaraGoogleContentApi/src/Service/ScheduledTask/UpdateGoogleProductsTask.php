<?php declare(strict_types=1);
namespace Roshyara\GoogleContentApi\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class UpdateGoogleProductsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'roshyara.update_google_products_task';
    }

    public static function getDefaultInterval(): int
    {
        return 10; // 5 minutes
    }
}
