<?php
namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class TroostwijkAuctionParserCommand extends Command
{
    use LockableTrait;

    const BATCH_SIZE = 1000;
    const API_URL = 'https://beta.troostwijkauctions.com/api/searchlot/lots?batchSize=%d&offset=%d&searchTerm=%s&type=lots';
    const LOT_URL = 'https://beta.troostwijkauctions.com/uk';

    protected function configure()
    {
        $this->setName('tool:show-troostwijk-auctions')
             ->setDescription('Parses and displays a list of Troostwijk Auctions')
             ->addArgument('term', InputArgument::REQUIRED, 'Search term');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $term = $input->getArgument('term');
        $io   = new SymfonyStyle($input, $output);
        $io->title('Troostwijk Auctions parser');

        $output->write('Retrieving results... ');
        $results = $this->getLotsByTerm($term);
        $results = $results->results;

        $count = count($results);

        if ( ! $count) {
            $io->caution('Nothing found.');
        }

        $output->writeln(sprintf('%s results found.', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->setBarCharacter('<fg=magenta>=</>');
        $progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");

        $rows = [];

        foreach ($results as $result) {
            $url = self::LOT_URL . $result->url;

            if ($io->isVerbose()) {
                $io->text('Processing URL ' . $url);
            }

            $html = $this->getUrlContents($url);

            $crawler    = new Crawler($html);
            $h1         = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[1]/div/h1');
            $bids_count = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[3]/div[1]/div/span');
            $cur_bid    = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[3]/div[2]/div[1]/span');
            $start_bid  = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[3]/div[2]/div[2]/span');
            // $desc = $crawler->filterXPath('//*[@id="description"]');
            $close = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[1]/div/span[2]/span');

            $bids_count = $bids_count->count() ? intval(trim($bids_count->text())) : 0;

            if (!$bids_count) {
                $bids_count = "  - ";
            } elseif ($bids_count >= 10) {
                $bids_count .= " \xF0\x9F\x94\xA5 ";
            }

            $rows[] = [
                trim($h1->text()),
                $bids_count,
                trim($cur_bid->text()),
                trim($start_bid->text()),
                //trim($desc->text()),
                trim($close->text()),
                $url
            ];
            $progressBar->advance();
        }

        $progressBar->finish();
        $table = new Table($output);

        $table->setHeaders(
            ['Lot title', 'Bids', 'Current', 'Start', 'Closes', 'Link']
        );
        $table->setColumnWidths([7, 3, 3, 3, 5, 5, 10]);

        $table->setRows($rows);
        $table->render();

        $io->success('Parsing Finished!');
    }

    private function getLotsByTerm($term): \stdClass
    {
        $url = $this->buildURL($term);

        return \GuzzleHttp\json_decode($this->getUrlContents($url));
    }

    private function buildURL(string $term, int $offset = 0, int $batchSize = self::BATCH_SIZE): string
    {
        return sprintf(self::API_URL,
            $batchSize,
            $offset,
            $term
        );
    }

    private function getUrlContents(string $url): string
    {
        $client = new Client();

        return $client->get($url)->getBody()->getContents();
    }
}
