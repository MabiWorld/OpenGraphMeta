<?php
/**
 * OpenGraphMeta
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
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

	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parserOutput ) {
		global $wgLogo, $wgSitename, $wgXhtmlNamespaces, $egFacebookAppId, $egFacebookAdmins, $egTwitterSite;

		$setMainImage = $parserOutput->getExtensionData( 'setmainimage' );
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

		if ( $hasImage ) {
			if ( is_object( $mainImage ) ) {
				$meta['og:image'] = wfExpandUrl( $mainImage->createThumb( 100 * 3, 100 ) );
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
