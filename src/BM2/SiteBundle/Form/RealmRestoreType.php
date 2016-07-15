<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class RealmRestoreType extends AbstractType {

    private $realm;

    public function __construct($realm) {
        $this->realm = $realm;
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'intention'       => 'realmrestore_13535',
            'translation_domain' => 'politics',
            'data_class'		=> 'BM2\SiteBundle\Entity\Realm',
        ));
    }

    //FIXME: Find out what the random numbers are above and what they mean!

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $realm = $this->realm;

        $builder->add('type', 'entity', array(
            'required'=>true,
            'placeholder'=>'diplomacy.restore.empty',
            'label'=>'realm.deadrealm',
            'class'=>'BM2SiteBundle:Realm',
            'choice_label'=>'name'
            'query_builder'=>function(EntityRepository $er) use ($realm) {
                $qb = $er->createQueryBuilder('r');
                $qb->where('e.realm = :realm')->setParameters(array('superior' => $realm, 'active' => false);
                $qb->orderBy('r.name', 'ASC');
                return $qb;
            }
        ));

        $builder->add('submit', 'submit', array('label'=>'realm.restore.submit'));

    }

    public function getName() {
        return 'realmrestore';
    }
}
