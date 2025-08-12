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
use MauticPlugin\MauticCustomImportBundle\Exception\InvalidImportException;
use MauticPlugin\MauticCustomImportBundle\Import\CustomImportFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RemoveTagsCommand extends Command
{
    /**
     * @var CustomImportFactory
     */
    private $customImportFactory;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * ImportFromDirectoryCommand constructor.
     *
     * @param CustomImportFactory $customImportFactory
     * @param TranslatorInterface $translator
     */
    public function __construct(CustomImportFactory $customImportFactory, TranslatorInterface $translator)
    {
        parent::__construct();
        $this->customImportFactory = $customImportFactory;
        $this->translator = $translator;
    // parent constructor removed for Mautic 5; locking deps are DI'd into parent in core
}

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mautic:remove:tags')
            ->setDescription('Remove tags from contacts');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $removedTags = $this->customImportFactory->removeContactsTags();
            if (!empty($removedTags)) {
                $output->writeln(
                    $this->translator->trans('mautic.custom.import.tags.removed', ['%s' => implode(', ', $removedTags)])
                );
            }
        } catch (InvalidImportException $importException) {
            $output->writeln($importException->getMessage());
        }

        return 0;
    }
}
