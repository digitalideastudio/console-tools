<?php
namespace App\Command;

use App\Services\PDFCreator;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class OperaComUaParserCommand extends Command
{
    use LockableTrait;

    const BATCH_SIZE = 1000;
    const AFISHA_URL = 'https://www.opera.com.ua/afisha';

    /** @var PDFCreator */
    protected $pdf;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->pdf = new PDFCreator();
    }

    protected function configure()
    {
        $this->setName('tool:opera-com-ua-afisha')
             ->setDescription('Parses and displays a feed of opera.com.ua')
             // ->addArgument('from', InputArgument::REQUIRED, 'from date')
             // ->addArgument('to', InputArgument::REQUIRED, 'to date')
             ->addOption('pdf', 'E', InputOption::VALUE_NONE, 'Export to PDF')
             ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io   = new SymfonyStyle($input, $output);
        $io->title('opera.com.ua parser');
        $output->write('Parsing pages... ');

        $html = $this->getUrlContents(self::AFISHA_URL);
        $crawler    = new Crawler($html);
        $pagerItem         = $crawler->filterXPath('//*[@id="block-system-main"]/div/div/div[2]/ul/li[@class="pager-item"]');
        $pagesCount = $pagerItem->count() + 1;
        $output->writeln(sprintf('%s pages found.', $pagesCount));

        $headers = ['Date', 'Category', 'Time start', 'Time finish', 'Photo', 'Author', 'Title'];
        $rows = [];
        for ($i = 0; $i <= $pagesCount; $i++) {
            $rows += $this->getResultsFromPage($io, $output, $i);
        }

        $table = new Table($output);

        $table->setHeaders($headers);
        $table->setColumnWidths([3, 5, 2, 2, 10, 5, 5]);

        $table->setRows($rows);
        $table->render();

        if ($input->getOption('pdf')) {
            $this->pdf->setTitle('Afisha: opera.com.ua');
            $this->pdf->setColumnWidths([60, 120, 50, 50, 100, 200, 200]);
            
            array_walk_recursive($rows, function(&$item, $key) {
                if ($key == 4) {
                    $item = '<img src="' . $item . '"/>';    
                }
            });

            $this->pdf->setTable($headers, $rows);
            $this->pdf->save(getcwd() . '/result.pdf');
        }

        $io->success('Parsing Finished!');
    }

    private function getUrlContents(string $url): string
    {
        $client = new Client();

        return $client->get($url)->getBody()->getContents();
    }

    private function getResultsFromPage($io, $output, $pageNum) {
        $output->write(sprintf('Retrieving results from page %d... ', $pageNum));
        
        $html = $this->getUrlContents(self::AFISHA_URL . '?page=' . $pageNum);
        $crawler    = new Crawler($html);
        $eventItems         = $crawler->filter('.views-row');

        $eventsCount = $eventItems->count();
        $output->writeln(sprintf('%d events found.', $eventsCount));

        $progressBar = new ProgressBar($output, $eventsCount);
        $progressBar->setBarCharacter('<fg=magenta>=</>');
        $progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");

        $rows = [];

        $eventItems->each(function(Crawler $node, $i) use ($progressBar, &$rows, $pageNum) {
            $date         = $node->filter('.left_part .date')->text();
            $category         = $node->filter('.left_part .row')->eq(0)->text();
            $timeStart         = $node->filter('.left_part .row_date b')->eq(0)->text();
            $timeFinish         = $node->filter('.left_part .row_date b')->eq(1)->text();

            $photoUrl         = $node->filter('.photo a img')->attr('src');

            $author         = $node->filter('.right_part .author')->getNode(0) ? $node->filter('.right_part .author')->text() : '';
            $title         = $node->filter('.right_part .title a')->text();

            $rows[] = [
                trim($date),
                trim($category),
                trim($timeStart),
                trim($timeFinish),
                trim($photoUrl),
                trim($author),
                trim($title),
            ];

            $progressBar->advance();
        });

        $progressBar->finish();
        $io->newLine();

        return $rows;
    }
}
