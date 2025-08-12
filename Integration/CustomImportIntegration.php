<?php

namespace MauticPlugin\MauticCustomImportBundle\Integration;

use Mautic\LeadBundle\Form\Type\TagType;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticCustomImportBundle\Form\Type\ImportListType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Mautic\UserBundle\Entity\User;

class CustomImportIntegration extends AbstractIntegration
{
    /**
     * @return string
     */
    public function getName(): string
    {
        // should be the name of the integration
        return 'CustomImport';
    }

    public function getDisplayName(): string
    {
        return 'Custom Import';
    }

    /**
     * @return string
     */
    public function getAuthenticationType(): string
    {
        /* @see \Mautic\PluginBundle\Integration\AbstractIntegration::getAuthenticationType */
        return 'none';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'plugins/MauticCustomImportBundle/Assets/img/icon.png';
    }

    /**
     * @param \Mautic\PluginBundle\Integration\Form|FormBuilder $builder
     * @param array                                             $data
     * @param string                                            $formArea
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ($formArea == 'features') {
            $builder->add(
                'template_from_import',
                ImportListType::class,
                [
                    'label'      => 'mautic.custom.import.form.template_from_import',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'    => 'form-control',
                    ],
                    'multiple'    => false,
                    'required'    => true,
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            );
            
// Build user choices for the dropdown
$users = $this->em->getRepository(User::class)
    ->createQueryBuilder('u')
    ->select('u.id, u.firstName, u.lastName, u.username, u.email')
    ->orderBy('u.firstName', 'ASC')
    ->addOrderBy('u.lastName', 'ASC')
    ->getQuery()
    ->getArrayResult();

$ownerChoices = [];
foreach ($users as $u) {
    $name  = trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''));
    $label = $name !== '' ? $name : ($u['username'] ?? ($u['email'] ?? ('User #'.$u['id'])));
    $ownerChoices[sprintf('%s (#%d)', $label, (int) $u['id'])] = (int) $u['id'];
}


$builder->add(
    'owner_id',
    ChoiceType::class,
    [
        'label'       => 'mautic.custom.import.owner_id',
        'label_attr'  => ['class' => 'control-label'],
        'choices'     => $ownerChoices,
        'placeholder' => 'mautic.custom.import.owner_id.placeholder',
        'required'    => true,
        'attr'        => [
            'class' => 'form-control',
            'data-placeholder' => $this->translator->trans('mautic.custom.import.owner_id.placeholder'),
        ],
    ]
);
$builder->add(
                'path_to_directory_csv',
                TextType::class,
                [
                    'label'      => 'mautic.custom.import.form.path_to_directory_csv',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'        => 'form-control',
                    ],
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            );

            $builder->add(
                'limit',
                NumberType::class,
                [
                    'label'      => 'mautic.custom.import.parallel.records.limit',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'tooltip' => 'mautic.custom.import.parallel.records.limit.tooltip',
                        'class'        => 'form-control',
                    ],
                    'constraints' => [
                        new NotBlank(),
                    ],
                ]
            );

            $builder->add(
                'tagsToRemove',
                TagType::class,
                [
                    'add_transformer' => true,
                    'by_reference'    => false,
                    'label' => 'mautic.custom.import.remove.tags',
                    'attr'            => [
                        'data-placeholder'     => $this->translator->trans('mautic.lead.tags.select_or_create'),
                        'data-no-results-text' => $this->translator->trans('mautic.lead.tags.enter_to_create'),
                        'data-allow-add'       => 'true',
                        'onchange'             => 'Mautic.createLeadTag(this)',
                    ],
                ]
            );
        }
    }

}
