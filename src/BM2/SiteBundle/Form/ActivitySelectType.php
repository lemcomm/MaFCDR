<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\EquipmentType;

class ActivitySelectType extends AbstractType {

	private $action;
	private $maxdistance;
	private $me;
	private $subselect;

	public function __construct($action, $maxdistance, $me, $subselect) {
		$this->action = $action;
		$this->maxdistance = $maxdistance;
		$this->me = $me;
		$this->subselect = $subselect;
	}

	public function getName() {
		return 'interaction';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'interaction_12331',
			'translation_domain' 	=> 'activity',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', TextType::class, array(
			'label'=>$this->action.'.form.name',
			'required'=>false
		));

		$method = $this->action."Fields";
		if (method_exists(__CLASS__, $method)) {
			$this->$method($builder, $options);
		}

		$builder->add('submit', SubmitType::class, [
			'label'=>$this->action.'.form.submit'
		]);
	}

	private function duelFields(FormBuilderInterface $builder, array $options) {
		$me = $this->me;
		$maxdistance = $this->maxdistance;

		$builder->add('target', EntityType::class,[
			'label'=>'duel.form.challenger',
			'placeholder'=>null,
			'multiple'=>false,
			'expanded'=>false,
			'required'=>true,
			'class'=>Character::class,
			'choice_label'=>'name',
			'query_builder'=>function(EntityRepository $er) use ($me, $maxdistance) {
				$qb = $er->createQueryBuilder('c');
				$qb->from(Character::class, 'me');
				$qb->where('c.alive = true');
				$qb->andWhere('c.prisoner_of IS NULL');
				$qb->andWhere('c.system NOT LIKE :gm OR c.system IS NULL')->setParameter('gm', 'GM');
				$qb->andWhere('me = :me')->andWhere('c != me')->setParameter('me', $me);
				if ($maxdistance) {
					$qb->andWhere('ST_Distance(me.location, c.location) < :maxdistance')->setParameter('maxdistance', $maxdistance);
				}
				if (!$me->getInsideSettlement()) {
					// if I am not inside a settlement, I can only attack others who are outside as well
					$qb->andWhere('c.inside_settlement IS NULL');
				}
				$qb->orderBy('c.name', 'ASC');
				return $qb;
		}]);
		$builder->add('context', ChoiceType::class, array(
			'label'=>'duel.form.context',
			'required'=>false,
			'choices'=>array(
				'first blood' => 'duel.form.firstblood',
				'wound' => 'duel.form.wound',
				'surrender' => 'duel.form.surrender',
				'death' => 'duel.form.death',
			),
			'placeholder'=> 'duel.form.choose'
		));
		$builder->add('sameWeapon', CheckboxType::class, array(
			'label'=>'duel.form.sameWeapon',
			'required'=>false
		));
		$builder->add('weapon', EntityType::class, [
			'class'=>EquipmentType::class,
                        'choice_label'=>'nameTrans',
                        'choice_translation_domain' => 'messages',
                        'choices'=>$this->subselect,
                        'label'=>'loadout.weapon',
                        'placeholder'=>'loadout.none',
			'translation_domain'=>'settings'
		]);
		$builder->add('weaponOnly', CheckboxType::class, array(
			'label'=>'duel.form.weaponOnly',
			'required'=>false
		));
	}

}
