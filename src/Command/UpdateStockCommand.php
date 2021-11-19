<?php
namespace App\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\Object_;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

use App\Entity\StockItem;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateStockCommand extends Command
{
    protected static $defaultName = 'app:update-stock';
    public function __construct($projectDir, EntityManagerInterface $entityManager)
    {
        $this->projectDir = $projectDir;
        $this->entityManager = $entityManager;
        parent::__construct();
    }
    protected function configure()
    {
        $this->setDescription('Update stock record')
            ->addArgument('markup',InputArgument::OPTIONAL,'Percentage markup',20)
            ->addArgument('process_date',InputArgument::OPTIONAL,'Date of the process',date_create()->format('Y-m-d'));

    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $processDate = $input->getArgument('process_date');
        $markup = ($input->getArgument('markup') / 100) + 1;
        // convert csv file content  into interable php 
        $supplierProducts = $this->getCsvRowsAsArray($processDate);
        /** @var StockItemRepository $stockItemRepo */
        $stockItemRepo = $this->entityManager->getRepository(StockItem::class);

        //loop over records 
        foreach($supplierProducts as $supplierProduct){
        //Update if matching records  found in DB
            /** @var StockItem $existingStockItem */
            if($existingStockItem = $stockItemRepo->findOneBy(['itemNumber' => $supplierProduct['item_number']])){
                $this->updateStockItem($existingStockItem,$supplierProduct,$markup);
                continue;
            }
            
        // Create new records if matching records found in DB
        $this->createNewStockItem($supplierProduct,$markup);
        
        }
        $this->entityManager->flush();
        $io = new SymfonyStyle($input,$output);

        $io->success('it worked');

        return Command::SUCCESS;
    }
    public function getCsvRowsAsArray($processDate){
        $inputFile = $this->projectDir.'/public/'.$processDate.'.csv';
        $decoder = new Serializer([new ObjectNormalizer()],[new CsvEncoder()]);
        return $decoder->decode(file_get_contents($inputFile),'csv');
    }
    public function createNewStockItem($supplierProduct,$markup){
        $newStickItem = new StockItem();
        $newStickItem->setItemNumber($supplierProduct['item_number']);
        $newStickItem->setItemName($supplierProduct['item_name']);
        $newStickItem->setItemDescription($supplierProduct['description']);
        $newStickItem->setSupplierCost($supplierProduct['cost']);
        $newStickItem->setPrice($supplierProduct['cost'] * $markup);
        $this->entityManager->persist($newStickItem);

    }
    public function updateStockItem($existingStockItem,$supplierProduct,$markup){
        $existingStockItem->setSupplierCost($supplierProduct['cost']);
        $existingStockItem->setPrice($supplierProduct['price'] * $markup);
        $this->entityManager->persist($existingStockItem);
    }
} 