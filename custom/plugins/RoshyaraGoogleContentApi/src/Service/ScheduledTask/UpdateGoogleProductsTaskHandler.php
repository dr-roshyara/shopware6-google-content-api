<?php declare(strict_types=1);

namespace Roshyara\GoogleContentApi\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Roshyara\GoogleContentApi\Service\UpdateGoogleProductsService;
class UpdateGoogleProductsTaskHandler extends ScheduledTaskHandler
{
     //
    private $updateService;		
    public static function getHandledMessages(): iterable
    {
        return [ UpdateGoogleProductsTask::class ];
    }
     private  function setter(){
	 echo "here is setter \n";    
	 $this->updateService =new UpdateGoogleProductsService();
     }
    public function run(): void
    {
         // file_put_contents('some/where/some/file.md', 'example');
	// php bin/console update:google-products;
	$this->setter();    
	//$this->updateService->getGoogleMerchantProducts();
	echo "test schedule\n";	
	echo "\n-----------------\n";
    }
}
