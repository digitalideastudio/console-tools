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

    protected function configure()
    {
        $this->setName('tool:show-troostwijk-auctions')
            ->setDescription('Parses and displays a list of Troostwijk Auctions')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to file with links to the lots.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $io = new SymfonyStyle($input, $output);
        $io->title('Troostwijk Auctions parser');

        if (!is_readable($file)) {
            $io->error(sprintf('%s', 'This file is not exists or not readable.'));
        }

        $output->write('Loading links... ');
        $links = array_filter(explode(PHP_EOL, file_get_contents($file)));
        $count = count($links);

        if (!$links) {
            $io->error('No links available!');
        }

        $output->writeln(sprintf('%s links loaded.', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->setBarCharacter('<fg=magenta>=</>');
        $progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");

        $rows = [];
        $client = new Client();

        for($i = 0; $i < $count; $i++) {
            $url = $links[$i];

            $res = $client->request('GET', $url);
            $html = $res->getBody()->getContents();

            $crawler = new Crawler($html);
            $h1 = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[1]/div/h1');
            $bids_count = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[3]/div[1]/div/span');
            $cur_bid = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[3]/div[2]/div[1]/span');
            $start_bid = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[3]/div[2]/div[2]/span');
            // $desc = $crawler->filterXPath('//*[@id="description"]');
            $close = $crawler->filterXPath('//*[@id="app"]/div/div[2]/div[2]/div[2]/div[3]/div[1]/div/span[2]/span');

            $bids_count = intval(trim($bids_count->text()));

            if ($bids_count >= 10) {
//                $bids_count .= " \xF0\x9F\x8C\xB6 ";
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
}
