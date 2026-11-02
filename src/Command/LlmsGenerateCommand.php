<?php

namespace Pdir\ContaoLlmsBundle\Command;

use Doctrine\DBAL\Connection;
use Pdir\ContaoLlmsBundle\Service\LlmsGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Generates the content of the LLMs files in advance, e.g. in a cronjob, so
 * no visitor has to wait for it. The files are requested through the kernel,
 * which bypasses the HTTP cache and the time limits of the web server.
 */
#[AsCommand(name: 'pdir:llms:generate', description: 'Generates the content of the llms.txt and llms-full.txt in advance.')]
class LlmsGenerateCommand extends Command
{
    /**
     * Below the maximum age of the generator, so a daily cronjob also renews
     * the contents it generated the day before, before they expire.
     */
    private const REFRESH_AGE = 72000;

    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly LlmsGenerator $generator,
        private readonly Connection $connection,
        private readonly RequestContext $requestContext,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = false;
        $generated = 0;

        $this->generator->setMaxAge(self::REFRESH_AGE);

        foreach ($this->getBaseUrls($io, $failed) as $baseUrl) {
            // The llms-full.txt comes first, because the llms.txt leaves out pages it found to be empty
            foreach (['llms-full.txt', 'llms.txt'] as $file) {
                $url = $baseUrl.'/'.$file;
                $request = Request::create($url);
                $response = $this->kernel->handle($request);
                $status = $response->getStatusCode();

                if ($this->kernel instanceof TerminableInterface) {
                    $this->kernel->terminate($request, $response);
                }

                if (Response::HTTP_OK === $status) {
                    ++$generated;
                    $io->writeln('Generated '.$url);
                } elseif (Response::HTTP_NOT_FOUND === $status) {
                    // The file is disabled in the root page (or the root page with this URL prefix)
                    $io->writeln('Skipped '.$url, OutputInterface::VERBOSITY_VERBOSE);
                } else {
                    $failed = true;
                    $io->error(sprintf('Could not generate %s (HTTP %d).', $url, $status));
                }
            }
        }

        if ($this->generator->getFailures() > 0) {
            $failed = true;
            $io->error(sprintf('%d entries could not be fetched and keep their stored content, see the log for details.', $this->generator->getFailures()));
        }

        if ($failed) {
            return Command::FAILURE;
        }

        $io->success(sprintf('Generated %d LLMs files.', $generated));

        return Command::SUCCESS;
    }

    /**
     * Returns the base URL of every domain of the published root pages and,
     * for root pages with a URL prefix, the base URL with the prefix (e.g.
     * https://example.org/de for /de/llms.txt). Root pages without a domain
     * use the default URI of the application.
     *
     * @return list<string>
     */
    private function getBaseUrls(SymfonyStyle $io, bool &$failed): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT dns, useSSL, urlPrefix FROM tl_page WHERE type = ? AND published = ? ORDER BY sorting', ['root', 1]);
        $hosts = [];
        $prefixes = [];

        foreach ($rows as $row) {
            $dns = (string) $row['dns'];
            $hosts[$dns] = ($hosts[$dns] ?? false) || (bool) $row['useSSL'];

            if ('' !== ($urlPrefix = (string) ($row['urlPrefix'] ?? ''))) {
                $prefixes[$dns][] = $urlPrefix;
            }
        }

        $domainUrls = [];
        $prefixUrls = [];

        foreach ($hosts as $dns => $useSSL) {
            if ('' !== $dns) {
                $baseUrl = ($useSSL ? 'https://' : 'http://').$dns;
            } elseif ('localhost' === $this->requestContext->getHost()) {
                $failed = true;
                $io->error('A root page has no domain. Enter one in the root page or set the DEFAULT_URI environment variable.');

                continue;
            } else {
                $baseUrl = $this->requestContext->getScheme().'://'.$this->requestContext->getHost();
            }

            $domainUrls[] = $baseUrl;

            foreach ($prefixes[$dns] ?? [] as $urlPrefix) {
                $prefixUrls[] = $baseUrl.'/'.$urlPrefix;
            }
        }

        // The files of the whole domain come first, the language-specific ones reuse their contents
        return array_values(array_unique([...$domainUrls, ...$prefixUrls]));
    }
}
