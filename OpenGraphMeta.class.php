<?php
/**
 * OpenGraphMeta
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @author Southparkfan
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:OpenGraphMeta Documentation
 */

class OpenGraphMeta {
	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'setmainimage', array( __CLASS__, 'setMainImagePF' ) );
		$parser->setFunctionHook( 'setmaintitle', array( __CLASS__, 'setMainTitlePF' ) );
		return true;
	}

	/**
	 * @param Parser $parser
	 * @param string $mainImage
	 * @return string
	 */
	public static function setMainImagePF( Parser $parser, $mainImage ) {
		$parserOutput = $parser->getOutput();
		$setMainImage = $parserOutput->getExtensionData( 'setmainimage' );
		if ( $setMainImage !== null ) {
			return ''; //$mainImage;
		}

		$file = Title::newFromText( $mainImage, NS_FILE );
		$parserOutput->setExtensionData( 'setmainimage', $mainImage );

		return ''; //$mainImage;
	}

	/**
	* @param Parser $parser
	* @param string $mainTitle
	* @return string
	*/
	public static function setMainTitlePF( Parser $parser, $mainTitle ) {
		$parserOutput = $parser->getOutput();
		$setMainTitle = $parserOutput->getExtensionData( 'setmaintitle' );
		if ( $setMainTitle !== null ) {
			return $mainTitle;
		}

		$parserOutput->setExtensionData( 'setmaintitle', $mainTitle );

		return $mainTitle;
	}

	/**
	* @param OutputPage &$out
	* @param ParserOutput $parserOutput
	* @return bool
	*/
	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parserOutput ) {
		global $wgLogo, $wgSitename, $wgXhtmlNamespaces, $egFacebookAppId, $egFacebookAdmins, $egTwitterSite;

		$setMainImage = $parserOutput->getExtensionData( 'setmainimage' );
		$setMainTitle = $parserOutput->getExtensionData( 'setmaintitle' );

		if ( $setMainImage !== null ) {
			$mainImage = wfFindFile( Title::newFromText( $setMainImage, NS_FILE ) );
		} else {
			$mainImage = false;
		}

		$wgXhtmlNamespaces['og'] = 'http://opengraphprotocol.org/schema/';
		$title = $out->getTitle();
		$isMainpage = $title->isMainPage();
		$hasImage = $mainImage !== false;

		$meta = array();
		$meta["twitter:card"] = "summary";

		if ( $isMainpage ) {
			$meta['og:type'] = 'website';
			$meta['og:title'] = $wgSitename;
		} else {
			$meta['og:type'] = 'article';
			$meta['og:site_name'] = $wgSitename;
			// Try to chose the most appropriate title for showing in news feeds.
			if (
				( defined( 'NS_BLOG_ARTICLE' ) && $title->getNamespace() == NS_BLOG_ARTICLE ) ||
				( defined( 'NS_BLOG_ARTICLE_TALK' ) && $title->getNamespace() == NS_BLOG_ARTICLE_TALK )
			) {
				$ogTitle = $title->getSubpageText();
			} else {
				$ogTitle = $title->getText();
			}
			$meta['og:title'] = str_replace('/', ' - ', $ogTitle);
		}
		$meta["twitter:title"] = $meta["og:title"];

		// {{#setmaintitle}} was used, override og:title value
		if ( $setMainTitle !== null ) {
			$meta['og:title'] = $setMainTitle;
		}

		if ( $hasImage ) {
			if ( is_object( $mainImage ) ) {
				// The official OpenGraph documentation says:
				// - thumbnail previews can't be smaller than 200px x 200px
				// - thumbnail previews look best at 1200px x 630px
				// @see https://developers.facebook.com/docs/sharing/best-practices/
				// @see https://phabricator.wikimedia.org/T193986
				$meta['og:image'] = wfExpandUrl( $mainImage->createThumb( 1200, 630 ) );
			} else {
				// In some edge-cases we won't have defined an object but rather a full URL.
				$meta['og:image'] = $mainImage;
			}
		} elseif ( $isMainpage ) {
			$meta['og:image'] = wfExpandUrl( $wgLogo );
		}
		if ( isset($meta["og:image"]) ) {
			$meta["twitter:image"] = $meta["og:image"];
		}
		$description = $parserOutput->getProperty( 'description' );
		if ( $description !== false ) { // set by Description2 extension, install it if you want proper og:description support
			$meta["twitter:description"] = $meta['og:description'] = $description;
		}
		$meta['og:url'] = $title->getFullURL();
		if ( $egFacebookAppId ) {
			$meta['fb:app_id'] = $egFacebookAppId;
		}
		if ( $egFacebookAdmins ) {
			$meta['fb:admins'] = $egFacebookAdmins;
		}
		if ( $egTwitterSite ) {
			$meta["twitter:site"] = $egTwitterSite;
		}

		foreach( $meta as $property => $value ) {
			if ( $value ) {
				if (substr($property, 0, 8) == "twitter:") {
					$attr = "name";
				} else {
					$attr = "property";
				}
				$out->addHeadItem(
					"meta:$attr:$property",
					'	' . Html::element( 'meta', array(
						$attr => $property,
						'content' => $value
					) ) . "\n"
				);
			}
		}

		return true;
	}

}
