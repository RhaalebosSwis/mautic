<?php

namespace Mautic\CoreBundle\Form\Type;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Form\ChoiceLoader\EntityLookupChoiceLoader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityLookupType extends AbstractType
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ModelFactory
     */
    private $modelFactory;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityLookupChoiceLoader[]
     */
    private $choiceLoaders;

    public function __construct(ModelFactory $modelFactory, TranslatorInterface $translator, Connection $connection, RouterInterface $router)
    {
        $this->translator   = $translator;
        $this->router       = $router;
        $this->connection   = $connection;
        $this->modelFactory = $modelFactory;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Let the form builder notify us about initial/submitted choices
        $formModifier = function (FormEvent $event) {
            $options = $event->getForm()->getConfig()->getOptions();
            $this->choiceLoaders[$options['model']]->setOptions($options);
            $this->choiceLoaders[$options['model']]->onFormPostSetData($event);
        };

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            $formModifier
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            $formModifier
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['model', 'ajax_lookup_action']);
        $resolver->setDefined(['model_lookup_method', 'repo_lookup_method', 'lookup_arguments']);
        $resolver->setDefaults(
            [
                'modal_route'            => false,
                'modal_route_parameters' => ['objectAction' => 'new'],
                'modal_header'           => '',
                'force_popup'            => false,
                'entity_label_column'    => 'name',
                'entity_id_column'       => 'id',
                'choice_loader'          => function (Options $options) {
                    // This class is defined as a service therefore the choice loader has to be unique per field that inherits this class as a parent
                    $this->choiceLoaders[$options['model']] = new EntityLookupChoiceLoader(
                            $this->modelFactory,
                            $this->translator,
                            $this->connection,
                            $options
                        );

                    return $this->choiceLoaders[$options['model']];
                },
                'choice_translation_domain' => false,
                'expanded'                  => false,
                'multiple'                  => false,
                'required'                  => false,
                'placeholder'               => '',
            ]
        );
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $attr =
            [
                'class'              => "form-control {$options['model']}-select",
                'data-chosen-lookup' => $options['ajax_lookup_action'],
                'data-model'         => $options['model'],
            ];

        if (!empty($options['modal_route'])) {
            $attr = array_merge(
                $attr,
                [
                    'data-new-route'          => $this->router->generate($options['modal_route'], $options['modal_route_parameters']),
                    'data-header'             => $options['modal_header'] ? $this->translator->trans($options['modal_header']) : 'false',
                    'data-chosen-placeholder' => $this->translator->trans('mautic.core.lookup.search_options', [], 'javascript'),
                ]
            );
        }

        if ($options['force_popup']) {
            $attr['data-popup'] = 'true';
        }

        $view->vars['attr'] = array_merge($view->vars['attr'], $attr);
    }
}
