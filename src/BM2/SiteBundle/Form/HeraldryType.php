<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class HeraldryType extends AbstractType {

	private $colours = array(
		'metals' => array(
			"rgb(240,240,240)" => "argent",
			"rgb(255,220,10)" => "or",
			"rgb(184,115,51)" => "copper",
			"rgb(161,157,148)" => "iron",
			"rgb(68,79,83)" => "lead",
			"rgb(230,178,115)" => "buff",
		),
		'colours' => array(
			"rgb(0,150,0)" => "vert",
			"rgb(127,255,212)" => "aquamarine",
			"rgb(0,0,255)" => "azure",
			"rgb(150,200,250)" => "blue celeste",
			"rgb(176,196,222)" => "eisen-farbe",
			"rgb(128,128,128)" => "cendree",
			"rgb(255,255,255)" => "white",
			"rgb(203,157,6)" => "ochre",
			"rgb(145,56,50)" => "red ochre",
			"rgb(237,205,194)" => "carnation",
			"rgb(241,156,187)" => "amaranth",
			"rgb(255,0,127)" => "rose",
			"rgb(255,0,0)" => "gules",
			"rgb(170,0,170)" => "purpure",
			"rgb(0,0,0)" => "sable",
		),
		'stains' => array(
			"rgb(140,0,75)" => "murrey",
			"rgb(190,0,0)" => "sanguine",
			"rgb(250,150,50)" => "tenne",
			"rgb(101,67,33)" => "brunatre",
		)
	);


	private $shields = array(
		'badge', 'french', 'german', 'italian', 'polish', 'spanish', 'swiss', 'draconian'
	);

	private $patterns = array(
		"base", "bend", "bend_sinister", "chevron", "chief", "cross", "fess", "flaunches", "gryon",  "pale",
		"per_bend", "per_bend_sinister", "per_chevron", "per_fess", "per_pale", "per_saltire",
		"pile", "quarterly", "saltire", "shakefork",
	);

	private $charges = array(
		'beasts' => array(
			"bear_head_couped", "bear_head_erased", "bear_head_muzzled", "bear_passant", "bear_rampant", "bear_sejant_erect", "bear_statant",
			"boar_head_couped", "boar_head_erased", "boar_passant", "boar_rampant", "boar_statant",
			"buck_head_couped",
			"catamount_passant_guardant", "catamount_sejant_guardant", "catamount_sejant_guardant_erect",
			"coney",
			"dragon_passant", "dragon_rampant", "dragon_statant",
			"eagle_displayed",
			"falcon",
			"fox_mask", "fox_passant", "fox_sejant",
			"hind",
			"horse_courant", "horse_passant", "horse_rampant",
			"lion_rampant",
			"lynx_coward",
			"martlet_volant",
			"pegasus_passant",
			"reindeer",
			"serpent_nowed",
			"squirrel_sejant_erect",
			"stag-atgaze", "stag-lodged", "stag-springing", "stag-statant", "stag-trippant",
			"stagshead-caboshed", "stagshead-erased",
			"unicorn_rampant",
			"winged_stag_rampant",
			"wolf_courant", "wolf_passant", "wolf_rampant", "wolf_salient", "wolf_statant",
		),
		'objects' => array(
			"arm_cubit_habited", "arm_cubit_in_armor", "arm_embowed_in_armor",
			"battle_axe",
			"broad_arrow",
			"caltrap",
			"chess_rook",
			"chevalier_on_horseback",
			"church_bell",
			"crescent", "decrescent",
			"fluer_de_lis",
			"javelin",
			"scymitar",
			"sun_in_splendor",
			"sword",
		)
	);

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'heraldry_561561',
			'data_class'		=> 'BM2\SiteBundle\Entity\Heraldry',
			'translation_domain' => 'heraldry'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {

		$builder->add('name', 'text', array(
			'label' => "label.name"
		));

		$builder->add('shield', 'choice', array(
			'label' => "label.shield",
			'required' => true,
			'placeholder' => 'form.choose',
			'choices' => array_combine($this->shields, $this->shields)
		));
		$builder->add('shield_colour', 'choice', array(
			'label' => "label.shieldc",
			'required' => true,
			'placeholder' => 'form.choose',
			'choices' => $this->colours
		));

		$builder->add('pattern', 'choice', array(
			'label' => "label.pattern",
			'required' => false,
			'placeholder' => 'form.choose',
			'choices' => array_combine($this->patterns, $this->patterns)
		));
		$builder->add('pattern_colour', 'choice', array(
			'label' => "label.patternc",
			'required' => false,
			'placeholder' => 'form.choose',
			'choices' => $this->colours
		));

		$charges = array();
		foreach ($this->charges as $key=>$data) {
			$charges[$key] = array_combine($data, $data);
		}
		$builder->add('charge', 'choice', array(
			'label' => "label.charge",
			'required' => false,
			'placeholder' => 'form.choose',
			'choices' => $charges
		));
		$builder->add('charge_colour', 'choice', array(
			'label' => "label.chargec",
			'required' => false,
			'placeholder' => 'form.choose',
			'choices' => $this->colours
		));

		$builder->add('shading', 'checkbox', array(
			'label' => "label.shading",
			'required' => false,
		));
	}

	public function getName() {
		return 'heraldry';
	}
}
