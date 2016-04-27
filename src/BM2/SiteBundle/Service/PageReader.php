<?php

namespace BM2\SiteBundle\Service;


class PageReader {

	// this assumes both $section and $locale are clean, i.e. not user input
	public function getPage($section, $pagename, $locale) {
		$file = __DIR__."/../Resources/pages/{$locale}/{$section}/".basename($pagename).".md";

		if (file_exists($file)) {
			return file_get_contents($file);
		}

		// check if we have the english version:
		$file = __DIR__."/../Resources/pages/en/{$section}/".basename($pagename).".md";

		if (file_exists($file)) {
			// FIXME: prefix a translated "sorry, not yet available in your language, here's the english version" blurb
			return file_get_contents($file);
		}


		// FIXME: return a translated "page not found" string
		return false;
	}

}
