<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\User;
use BM2\SiteBundle\Entity\Culture;


class CultureType extends AbstractType {

	private $user;
	private $available;
	private $old_culture;

	public function __construct(User $user, $available=true, Culture $old_culture=null) {
		$this->user = $user;
		$this->available = $available;
		$this->old_culture = $old_culture;
	}

	public function getName() {
		return 'culture';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'culture_9413',
			'attr'		=> array('class'=>'wide')
		));
	}


	public function buildForm(FormBuilderInterface $builder, array $options) {
		$user = $this->user;
		$available = $this->available;
		$old_culture = $this->old_culture;

		if ($available) {
			$builder->add('culture', 'entity', array(
				'label' => 'settlement.culture',
				'required' => true,
				'choice_translation_domain' => true,
				'class'=>'BM2SiteBundle:Culture',
				'query_builder'=>function(EntityRepository $er) use ($user, $old_culture) {
					$qb = $er->createQueryBuilder('c');
					$qb->leftJoin('c.users', 'u')
						->where('u = :me')->setParameter('me', $user)
						->orWhere('c.free = true');
					if ($old_culture) {
						$qb->orWhere('c = :old')->setParameter('old', $old_culture);
					}
					return $qb;
				},
			));
			$builder->add('submit', 'submit', array('label'=>'account.culture.change'));
		} else {
			$builder->add('culture', 'entity', array(
				'label' => 'settlement.culture',
				'multiple' => true,
				'expanded' => true,
				'required' => true,
				'choice_translation_domain' => true,
				'class'=>'BM2SiteBundle:Culture',
				'query_builder'=>function(EntityRepository $er) use ($user) {
					$qb = $er->createQueryBuilder('c');
					$qb->where('c.free = false');
					$owned = array();
					foreach ($user->getCultures() as $culture) {
						$owned[]=$culture->getId();
					}
					if (!empty($owned)) {
						$qb->andWhere('c NOT IN (:owned)')->setParameter('owned',$owned);
					}
					return $qb;
				},
			));			
			$builder->add('submit', 'submit', array('label'=>'account.culture.submit'));
		}

	}

}
