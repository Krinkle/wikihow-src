<?php

class SchemaMarkup {

	private static function getDatePublished( $title ) {
		$result = array();

		if ( !$title ) {
			return $result;
		}

		$dp = date_create( $title->getEarliestRevTime() );

		if ( !$dp ) {
			return $result;
		}

		$result['datePublished'] = $dp->format( 'Y-m-d' );
		return $result;
	}

	private static function getDateModified( $out ) {
		$result = array();

		if ( !$out ) {
			return $result;
		}

		$timestamp = $out->getFetchedRevisionTimestamp();
		if ( !$timestamp )  {
			return $result;
		}

		$dm = date_create( $timestamp );

		if ( !$dm ) {
			return $result;
		}

		$result['dateModified'] = $dm->format( 'Y-m-d' );
		return $result;
	}

	private static function getNutritionInformation( $t ) {
		$result = array();

		if ( !$t ) {
			return $result;
		}

		$id = $t->getArticleID();
		if ( $id == 3177283 ) {
			$result = [
				"@type" => "NutritionInformation",
				"calories" => "260",
				"servingSize" => "2 Cabbage Rolls"
			];
			$result = [ 'nutrition' => $result ];
		}

		return $result;
	}

	private static function getPrepTime( $t ) {
		$result = array();

		if ( !$t ) {
			return $result;
		}

		$id = $t->getArticleID();
		if ( $id == 3177283 ) {
			$result = [ "prepTime" => "PT20M" ];
		}

		return $result;
	}

	private static function getCookTime( $t ) {
		$result = array();

		if ( !$t ) {
			return $result;
		}

		$id = $t->getArticleID();
		if ( $id == 3177283 ) {
			$result = [ "cookTime" => "PT1H" ];
		}

		return $result;
	}

	private static function getWikihowOrganization() {
		$url = wfGetPad( '/skins/owl/images/wikihow_logo_name_green_60.png' );

		$logo = [
			'@type' => 'ImageObject',
			'url' => $url,
			'width' => 326,
			'height' => 60,
		];

		$result = [
			"@type" => "Organization",
			"name" => "wikiHow",
			"url" => "http://www.wikihow.com",
			"logo" => $logo
		];
		return $result;
	}

	private static function getPublisher() {
		$result = [ 'publisher' => self::getWikihowOrganization() ];
		return $result;
	}

	private static function getAggregateRating( $title ) {
		global $wgLanguageCode;
		$result = array();

		if ( !$title ) {
			return $result;
		}

		if ( $wgLanguageCode != 'en' ) {
			return $result;
		}

		if ( !SocialProofStats::okToShowRating( $title ) ) {
			return $result;
		}

		$data = SocialProofStats::getPageRatingData( $title->getArticleID() );

		if ( !$data || !$data->ratingCount || !$data->rating ) {
			return $result;
		}

		$rating = [
			'@type' => 'AggregateRating',
			'bestRating' => 100,
			'ratingCount' => $data->ratingCount,
			'ratingValue' => $data->rating
		];

		$result = [ 'aggregateRating' => $rating ];
		return $result;
	}

	private static function getIngredients( $title ) {
		$wikihow = WikihowArticleEditor::newFromTitle( $title );
		$ingredients = $wikihow->getSection("ingredients");

		// if we have a subsection or multiple ingredient subsections, just get data from first one
		$filtered = array();
		foreach ( explode( "\n", $ingredients ) as $key => $line ) {
			if ( substr( $line, 0, 3 ) == "===" ) {
				if ( $key == 0 ) {
					continue;
				} else {
					break;
				}
			} else {
				$filtered[] = $line;
			}
		}
		$ingredients = implode( "\n", $filtered );
		$ingredients = array_values( array_filter( array_map( 'trim', explode( '*', $ingredients ) ) ) );
		foreach ( $ingredients as $k=>$val ) {
			$ingredients[$k] = strtok( $val, "\n" );
			$ingredients[$k] = Sanitizer::removeHTMLtags( $ingredients[$k] );
		}
		return $ingredients;
	}

	private static function getContributors( $t ) {
		$result = [];

		$verifiers = VerifyData::getByPageId( $t->getArticleID() );

		if ( empty( $verifiers ) ) {
			return $result;
		}

		$verifier = array_pop( $verifiers );
		if ( empty( $verifier ) || $verifier->name == '' ) {
			return $result;
		}

		$result['contributor'] = [ '@type' => 'Person', 'name' => $verifier->name ];
		return $result;
	}

	private static function getAuthors( $t ) {
		$result = [ 'author' => self::getWikihowOrganization() ];

		return $result;
	}

	private static function getSchemaImage() {
		global $wgIsDevServer;

		$result = array();
		$thumb = ArticleMetaInfo::getTitleImageThumb();
		if ( !$thumb ) {
			$thumb = Wikitext::getDefaultTitleImage();
		}

		$url = wfGetPad( $thumb->getUrl() );
		if ( !$url ) {
			return $result;
		}

		if ( $wgIsDevServer && !preg_match('@^https?:@', $url) ) {
			// just use a valid url for testing purposes
			$url = "http://www.wikihow.com" . $url;
		}

		$image = [ '@type' => 'ImageObject',
			'url' => $url,
			'width' => $thumb->getWidth(),
			'height' => $thumb->getHeight()
		];

		$result = [ 'image' => $image ];
		return $result;
	}

	private static function okToShowSchema( $out ) {

		// sanity check the input
		if ( !$out ) {
			return false;
		}

		// getting the wikipage does not work for special pages so
		// do more sanity checking
		$title = $out->getTitle();
		if ( !$title || $title->getNamespace() != NS_MAIN ) {
			return false;
		}

		return true;
	}

	// get schema for amp pages which may differ from the regular page
	public static function getAmpSchema( $out ) {
		$pageId = $out->getTitle()->getArticleID();
		$testPages = [ 2305417, 11774, 669011, 134766 ];
		$testPages = array_flip( $testPages );
		if ( isset( $testPages[$pageId] ) ) {
			return self::getRecipeSchema( $out );
		}

		return self::getArticleSchema( $out );
	}

	// get the json schema to put on the page if applicable
	// uses $out to get title and wikipage but does not write to $out
	public static function getSchema( $out ) {
		// for now we are restricting this to recipe category pages
		if ( !self::okToShowSchema( $out ) ) {
			return '';
		}

		$title = $out->getTitle();
		$pageId = $title->getArticleID();
		$schema = "";
		if ( self::isRecipeSchemaTestPage( $pageId ) ) {
			$data = self::getTestRecipeData( $pageId );
			$schema = self::getRecipeSchema( $out, $data );
		} else if ( class_exists('Microdata') && Microdata::showRecipeTags() ) {
			$schema = self::getRecipeSchema( $out );
		} else {
			$schema = self::getArticleSchema( $out );
		}
		return $schema;
	}

	private static function isRecipeSchemaTestPage( $pageId ) {
		$testPages = [ 2305417, 11774, 669011, 134766, 1124589, 40743, 197125, 8450, 151863, 159420, 294606, 2261514, 59634, 4357, 1905462, 265761, 2450462, 2871990, 27976, 696344, 126991, 1623698, 150727, 313989, 35395, 657926, 64180 ];
		$testPages = array_flip( $testPages );
		return isset( $testPages[$pageId] );
	}

	private static function getMainEntityOfPage( $title ) {
		$result = array();
		if ( !$title ) {
			return $result;
		}

		$canonical = WikihowMobileTools::getNonMobileSite() . '/' . $title->getPrefixedURL();
		$canonical = wfExpandUrl( $canonical, PROTO_CANONICAL );

		if ( !$canonical ) {
			return $result;
		}

		$result = [ "mainEntityOfPage" => [ '@type' => 'WebPage', 'id' => $canonical] ];

		return $result;
	}

	public static function getArticleSchema( $out ) {
		// does sanity checks on the title and wikipage and $out
		if ( !self::okToShowSchema( $out ) ) {
			return '';
		}

		$title = $out->getTitle();
		$wikiPage = $out->getWikiPage();

		// TODO do we want the headline to say How to??
		$data = [
			"@context"=> "http://schema.org",
			"@type" => "Article",
			"headline" => $title->getText(),
			"name" => "How to " . $title->getText(),
		];

		$data += self::getMainEntityOfPage( $title );
		$data += self::getSchemaImage();
		$data += self::getAuthors( $title );
		$data += self::getAggregateRating( $title );
		$data += self::getDatePublished( $title );
		$data += self::getDateModified( $out );
		$data += self::getPublisher();
		$data += self::getContributors( $title );

		$data['description'] = ArticleMetaInfo::getCurrentTitleMetaDescription();

		wfRunHooks( 'SchemaMarkupAfterGetData', array( &$data ) );

		$schema = Html::rawElement( 'script', [ 'type'=>'application/ld+json' ], json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		return $schema;
	}


	public static function getTestRecipeData( $pageId ) {
		global $IP;
		$result = array();
		$csv = file_get_contents($IP .'/extensions/wikihow/schema/testRecipeData.csv');
		$elements = array_map("str_getcsv", explode("\n", $csv));

		// the first line has the keys we will use
		$firstLine = array_shift( $elements );
		foreach ( $elements as $el ) {
			$line = [ 'nutrition'=> [ '@type'=>'NutritionInformation' ] ];
			$pos = 0;
			$id = $el[0];
			foreach ( $firstLine as $key ) {
				$pos++;
				if ( !$el[$pos-1] || !in_array( $key, [ "calories", "servingSize", "prepTime", "cookTime", "recipeYield" ] ) ) {
					continue;
				}
				if ( in_array( $key, [ "calories", "servingSize" ] ) ) {
					$line['nutrition'][$key] = $el[$pos - 1];
				} else {
					$line[$key] = $el[$pos - 1];
				}
			}
			// add the line to the result only if it has calorie information
			if ( $line['nutrition']['calories'] ) {
				$result[$id] = $line;
			}
		}

		return $result[$pageId];
	}

	/**
	 * get an html script tag of ld+json schema for a Recipe article
	 *
	 *
	 * @param Output out used to get the title and wikipage for finding the schema data
	 * @param Array schema additional starter schema to start with
	 * @return string script tag with json data or empty string if we can't create one
	 */
	public static function getRecipeSchema( $out, $data = array() ) {
		// does sanity checks on the title and wikipage and $out
		if ( !self::okToShowSchema( $out ) ) {
			return '';
		}

		$title = $out->getTitle();
		$wikiPage = $out->getWikiPage();

		$data = $data + [
			"@context"=> "http://schema.org",
			"@type" => "Recipe",
			"name" => $title->getText(),
		];

		$data += self::getMainEntityOfPage( $title );
		$data += self::getSchemaImage();
		$data += self::getAuthors( $title );
		$data += self::getAggregateRating( $title );
		$data += self::getDatePublished( $title );
		$data += self::getDateModified( $out );
		$data += self::getNutritionInformation( $title );
		$data += self::getPrepTime( $title );
		$data += self::getCookTime( $title );

		$data['description'] = ArticleMetaInfo::getCurrentTitleMetaDescription();

		$data['ingredients'] = self::getIngredients( $title );

		// TODO other values like those on this example page:
		// https://github.com/ampproject/amphtml/blob/master/examples/metadata-examples/recipe-json-ld.amp.html
		// like recipeInstructions or publisher or prepTime etc
		$schema = Html::rawElement( 'script', [ 'type'=>'application/ld+json' ], json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		return $schema;
	}

}

