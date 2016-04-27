<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;


class ListingType extends AbstractType {

	private $em;
	private $available;

	public function __construct(EntityManager $em, $available) {
		$this->em = $em;
		$this->available = $available;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'listing_12354',
			'data_class'		=> 'BM2\SiteBundle\Entity\Listing',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {

		$builder->add('name', 'text', array(
			'required' => true,
		));
		$builder->add('public', 'checkbox', array(
			'required' => false,
		));

		$available = $this->available;
		if (!empty($available)) {
			$builder->add('inheritFrom', 'entity', array(
				'required' => false,
				'placeholder'=>'form.none',
				'class'=>'BM2SiteBundle:Listing', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($available) {
					return $er->createQueryBuilder('l')->where('l IN (:avail)')->setParameter('avail', $available);
				}
			));
		}

		$builder->add('members', 'collection', array(
			'type'		=> new ListMemberType($this->em, $builder->getData()),
			'allow_add'	=> true,
			'allow_delete' => true,
		));
	}

	public function getName() {
		return 'listing';
	}
}
