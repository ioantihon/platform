<?php

namespace Oro\Bundle\SecurityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PrivilegeCollectionType extends AbstractType
{
    const NAME = 'oro_acl_collection';

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['privileges_config'] = $options['options']['privileges_config'];
        //TODO: Removing 'SHARE' from config. Remove this code after sharing is implemented.
        if (in_array('SHARE', $view->vars['privileges_config']['permissions'])) {
            array_pop($view->vars['privileges_config']['permissions']);
        }

        $view->vars['page_component_module'] = $options['page_component_module'];
        $view->vars['page_component_options'] = $options['page_component_options'];
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'page_component_module' => 'orosecurity/js/app/components/security-access-levels-component',
            'page_component_options' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'collection';
    }
}
