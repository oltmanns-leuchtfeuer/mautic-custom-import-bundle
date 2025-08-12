<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticCustomImportBundle\Command;

use Symfony\Component\Console\Command\Command;
use Mautic\CoreBundle\Helper\ProgressBarHelper;
use MauticPlugin\MauticCustomImportBundle\Exception\InvalidImportException;
use MauticPlugin\MauticCustomImportBundle\Import\CustomImportFactory;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ParallelImportCommand extends Command
{

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var CustomImportFactory
     */
    private $customImportFactory;

    /**
     * ParallelImportCommand constructor.
     *
     * @param CustomImportFactory $customImportFactory
     * @param TranslatorInterface $translator
     */
    public function __construct(CustomImportFactory $customImportFactory, TranslatorInterface $translator)
    {
        parent::__construct();
        $this->translator          = $translator;
        $this->customImportFactory = $customImportFactory;
    // parent constructor removed for Mautic 5; locking deps are DI'd into parent in core
}

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mautic:import:parallel')
            ->setDescription('Parallel import for Mautic')
            ->setHelp('This command processed parallel imports')
            ->addOption(
                '--output_from_import',
                null,
                InputOption::VALUE_NONE,
                'Display output from each mautic:import processes.'
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $processSet = $this->customImportFactory->processParallelImport();
            $this->processParallelCommandsOutput($input, $output, $processSet);
        } catch (InvalidImportException $importException) {
            $output->writeln($importException->getMessage());
        }
        return 0;
    }

    /**
     * @param Input  $input
     * @param Output $output
     * @param array  $processSet
     */
    private function processParallelCommandsOutput(Input $input, Output $output, array $processSet)
    {
        $processCount = count($processSet);
        if (empty($processCount)) {
            return;
        }

        $output->writeln(
            sprintf(
                "<info>%s</info>",
                $this->translator->trans('mautic.custom.import.csv.import.parallel.start', ['%s' => $processCount])
            )
        );

        $progress = ProgressBarHelper::init($output, count($processSet));
        $progress->start();
        $response = [];
        while (!empty($processSet)) {
            foreach ($processSet as $index => &$process) {
                $process->checkTimeout();
                // Not running, let's display result
                if (!$process->isRunning()) {
                    unset($processSet[$index]);
                    $progress->advance();
                    if (!$process->isSuccessful()) {
                        $output->writeln(sprintf("<error>%s</error>", $process->getErrorOutput()));
                    } else {
                        $response[] = $process->getOutput();
                    }
                }
            }
            sleep(1);
        }

        $progress->finish();

        if ($input->getOption('output_from_import')) {
            $output->writeln('');
            foreach ($response as $res) {
                $output->writeln($res);
            }
        }
    }
}
