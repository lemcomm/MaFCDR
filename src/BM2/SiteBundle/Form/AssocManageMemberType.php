<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

use BM2\SiteBundle\Entity\AssociationType;

class AssocManageMemberType extends AbstractType {

	private $ranks;
	private $me;

	public function __construct($ranks, $me) {
		$this->ranks = $ranks;
		$this->me = $me;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'updatembr_1779',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$ranks = $this->ranks;
		$me = $this->me;
		$builder->add('rank', EntityType::class, array(
			'label'=>'assoc.form.member.rank',
			'required'=>true,
			'placeholder' => 'assoc.form.select',
			'class' => 'BM2SiteBundle:AssociationRank',
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'choices' => $ranks,
			'data' => $me?$me->getRank():null
		));
		$builder->add('submit', SubmitType::class, array('label'=>'assoc.form.submit'));
	}

	public function getName() {
		return 'assocmember';
	}
}
