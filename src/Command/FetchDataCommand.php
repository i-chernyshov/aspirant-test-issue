<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Movie;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchDataCommand extends Command
{
    private const SOURCE = 'https://trailers.apple.com/trailers/home/rss/newtrailers.rss';
    private const COUNT = 10;

    protected static $defaultName = 'fetch:trailers';

    private ClientInterface $httpClient;
    private LoggerInterface $logger;
    private EntityManagerInterface $doctrine;

    /**
     * FetchDataCommand constructor.
     *
     * @param ClientInterface        $httpClient
     * @param LoggerInterface        $logger
     * @param EntityManagerInterface $em
     * @param string|null            $name
     */
    public function __construct(
        ClientInterface $httpClient, LoggerInterface $logger, EntityManagerInterface $em, string $name = null
    ) {
        parent::__construct($name);
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->doctrine = $em;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Fetch data from iTunes Movie Trailers')
            ->addOption('source', null, InputArgument::OPTIONAL, 'Overwrite source')
            ->addOption('count', null, InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(sprintf(
            'Start %s at %s', __CLASS__, date_create()->format(DATE_ATOM)
        ));
        $source = $input->getOption('source') ?: self::SOURCE;
        $count = $input->getOption('count') ?: self::COUNT;

        if (!is_string($source)) {
            throw new RuntimeException('Source must be string');
        }
        if (!is_numeric($count)) {
            throw new RuntimeException('Count must be number');
        } else {
            $count = intval($count);
        }
        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Fetch data from %s', $source));

        try {
            $response = $this->httpClient->sendRequest(new Request('GET', $source));
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException($e->getMessage());
        }
        if (($status = $response->getStatusCode()) !== 200) {
            throw new RuntimeException(sprintf('Response status is %d, expected %d', $status, 200));
        }
        $this->processXml($response->getBody()->getContents(), $count);

        $this->logger->info(sprintf('End %s at %s', __CLASS__, date_create()->format(DATE_ATOM)));

        return 0;
    }

    protected function processXml(string $data, int $count): void
    {
        try {
            $xml = (new \SimpleXMLElement($data))->children();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }

        if (!property_exists($xml, 'channel')) {
            throw new RuntimeException('Could not find \'channel\' element in feed');
        }
        for ($i = 0; $i < $count; ++$i) {
            $item = $xml->channel->item[$i];
            $trailer = $this->getMovie((string) $item->title)
                ->setTitle((string) $item->title)
                ->setDescription((string) $item->description)
                ->setLink((string) $item->link)
                ->setImage($item->link . '/images/background.jpg')
                ->setPubDate($this->parseDate((string) $item->pubDate));

            $this->doctrine->persist($trailer);
        }

        $this->doctrine->flush();
    }

    protected function parseDate(string $date): \DateTime
    {
        try {
            return new \DateTime($date);
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    protected function getMovie(string $title): Movie
    {
        $item = $this->doctrine->getRepository(Movie::class)->findOneBy(['title' => $title]);

        if ($item === null) {
            $this->logger->info('Create new Movie', ['title' => $title]);
            $item = new Movie();
        } else {
            $this->logger->info('Move found', ['title' => $title]);
        }

        if (!($item instanceof Movie)) {
            throw new RuntimeException('Wrong type!');
        }

        return $item;
    }
}
