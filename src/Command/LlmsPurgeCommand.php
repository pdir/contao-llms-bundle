<?php

namespace Pdir\ContaoLlmsBundle\Command;

use Pdir\ContaoLlmsBundle\Cache\LlmsCacheInvalidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Discards all generated content of the LLMs files, e.g. after an update of
 * the bundle, so it is generated again on the next request.
 */
#[AsCommand(name: 'pdir:llms:purge', description: 'Purges the generated content of the llms.txt and llms-full.txt.')]
class LlmsPurgeCommand extends Command
{
    public function __construct(private readonly LlmsCacheInvalidator $cacheInvalidator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->cacheInvalidator->purge();

        (new SymfonyStyle($input, $output))->success(sprintf(
            'Purged the LLMs data: %d pages, %d news and events. Manually overridden page descriptions were kept.',
            $result['pages'],
            $result['records'],
        ));

        return Command::SUCCESS;
    }
}
