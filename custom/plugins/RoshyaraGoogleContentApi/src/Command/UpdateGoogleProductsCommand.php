<?php 
namespace Roshyara\GoogleContentApi\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class UpdateGoogleProductsCommand extends Command
{
   /*** 
 * protected function configure(): void
    {
        $this->setName('example')->setDescription('Simple example');
    }
 **/
    protected static $defaultName = 'update:google-products';
    protected static $defaultDescription = 'Show a listing of all current users';
    //
    protected function execute(
        InputInterface $input,
        OutputInterface $output): int {
        $output->writeln('Hello ! We are going to update google products !');
        return 0;
    }
}
