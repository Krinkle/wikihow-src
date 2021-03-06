<?php

namespace CirrusSearch\Search;

use \CirrusSearch\InterwikiSearcher;
use \CirrusSearch\Util;
use \CirrusSearch\Searcher;
use \MWTimestamp;
use \SearchResult;
use \Title;

/**
 * An individual search result from Elasticsearch.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class Result extends SearchResult {
	private $titleSnippet = '';
	private $redirectTitle = null, $redirectSnipppet = '';
	private $sectionTitle = null, $sectionSnippet = '';
	private $textSnippet = '', $isFileMatch = false;
	private $interwiki = '', $interwikiNamespace = '';
	private $wordCount;
	private $byteSize;
	private $timestamp;

	// George 2015-04-14
	// wikiHow-specific fields
	private $score = null;
	private $titusData = null;
	private $template = null;

	// George 2015-04-15
	// TODO: Keep this around for the duration of the Elasticsearch project.
	// Remove when done with testing, or improve and move to better location (Finner?).
	public function printExplanation($expl, $indent=2) {
		print str_repeat(' ', $indent) . $expl['value'] . ' = ' . $expl['description'] . "\n";
		if (isset($expl['details'])) {
			foreach ($expl['details'] as $deets) {
				$this->printExplanation($deets, $indent + 2);
			}
		}
	}

	/**
	 * Build the result.
	 * @param $results \Elastica\ResultSet containing all search results
	 * @param $result \Elastica\Result containing the given search result
	 * @param string $interwiki Interwiki prefix, if any
	 * @param $result \Elastic\Result containing information about the result this class should represent
	 */
	public function __construct( $results, $result, $interwiki = '' ) {
		if ( $interwiki ) {
			$this->setInterwiki( $result, $interwiki );
		}
		$this->mTitle = Title::makeTitle( $result->namespace, $result->title, '', $this->interwiki );
		if ( $this->getTitle()->getNamespace() == NS_FILE ) {
			$this->mImage = wfFindFile( $this->mTitle );
		}

		// George 2015-04-15
		// TODO: Keep this around for the duration of the Elasticsearch project.
		// print "EXPLANATIONDUMP\n";
		// $this->printExplanation($result->getExplanation());

		// George 2015-04-14
		// Set Titus data
		if (isset($result->titus)) {
			$this->titusData = $result->titus;
		}

		// George 2015-05-12
		// Set templates
		if (isset($result->template)) {
			$this->template = $result->template;
		}

		$fields = $result->getFields();
		// Not all results requested a word count. Just pretend we have none if so
		$this->wordCount = isset( $fields['text.word_count'] ) ? $fields['text.word_count'][ 0 ] : 0;
		$this->byteSize = $result->text_bytes;
		$this->timestamp = new MWTimestamp( $result->timestamp );
		$highlights = $result->getHighlights();
		// TODO remove when Elasticsearch issue 3757 is fixed
		$highlights = $this->swapInPlainHighlighting( $highlights, 'redirect.title' );
		$highlights = $this->swapInPlainHighlighting( $highlights, 'heading' );
		if ( isset( $highlights[ 'title' ] ) ) {
			$nstext = $this->getTitle()->getNamespace() === 0 ? '' :
				Util::getNamespaceText( $this->getTitle() ) . ':';
			$this->titleSnippet = $nstext . $this->escapeHighlightedText( $highlights[ 'title' ][ 0 ] );
		} elseif ( $this->mTitle->isExternal() ) {
			// Interwiki searches are weird. They won't have title highlights by design, but
			// if we don't return a title snippet we'll get weird display results.
			$nsText = $this->getInterwikiNamespaceText();
			$titleText = $this->mTitle->getText();
			$this->titleSnippet = $nsText ? "$nsText:$titleText" : $titleText;
		}

		if ( !isset( $highlights[ 'title' ] ) && isset( $highlights[ 'redirect.title' ] ) ) {
			// Make sure to find the redirect title before escaping because escaping breaks it....
			$redirects = $result->redirect;
			$this->redirectTitle = $this->findRedirectTitle( $highlights[ 'redirect.title' ][ 0 ], $redirects );
			$this->redirectSnipppet = $this->escapeHighlightedText( $highlights[ 'redirect.title' ][ 0 ] );
		}

		$this->textSnippet = $this->escapeHighlightedText( $this->pickTextSnippet( $highlights ) );

		if ( isset( $highlights[ 'heading' ] ) ) {
			$this->sectionSnippet = $this->escapeHighlightedText( $highlights[ 'heading' ][ 0 ] );
			$this->sectionTitle = $this->findSectionTitle();
		}

		// George 2015-04-14
		// Set the relevance score for display in search results
		$maxScore = $results->getMaxScore();
		if ($maxScore > 0 && !is_array($result->getScore())) {
			$this->score = $result->getScore() / $results->getMaxScore();
		} elseif (!is_array($result->getScore())) {
			$this->score = $result->getScore();
		} else {
			$this->score = NULL;
		}
	}

	private function pickTextSnippet( $highlights ) {
		$mainSnippet = '';
		if ( isset( $highlights[ 'text' ] ) ) {
			$mainSnippet = $highlights[ 'text' ][ 0 ];
			if ( $this->containsMatches( $mainSnippet ) ) {
				return $mainSnippet;
			}
		} else {
			// This can get skipped if there the page was sent to Elasticsearch without text.
			// This could be a bug or it could be that the page simply doesn't have any text.
			$mainSnipppet = '';
		}
		if ( isset( $highlights[ 'auxiliary_text' ] ) ) {
			$auxSnippet = $highlights[ 'auxiliary_text' ][ 0 ];
			if ( $this->containsMatches( $auxSnippet ) ) {
				return $auxSnippet;
			}
		}
		if ( isset( $highlights[ 'file_text' ] ) ) {
			$fileSnippet = $highlights[ 'file_text' ][ 0 ];
			if ( $this->containsMatches( $fileSnippet ) ) {
				$this->isFileMatch = true;
				return $fileSnippet;
			}
		}
		return $mainSnippet;
	}

	/**
	 * Don't bother hitting the revision table and loading extra stuff like
	 * that into memory like the parent does, just return if we've got an idea
	 * about page existence.
	 *
	 * Protects against things like bug 61464, where a page clearly doesn't
	 * exist anymore but we've got something stuck in the index.
	 *
	 * @return bool
	 */
	public function isMissingRevision() {
		return !$this->mTitle->isKnown();
	}

	/**
	 * Swap plain highlighting into the highlighting field if there isn't any normal highlighting.
	 * TODO remove when Elasticsearch issue 3757 is fixed.
	 * @var $highlights array of highlighting results
	 * @var $name string normal field name
	 * @return $highlights with $name replaced with plain field results if $name isn't in $highlights
	 */
	private function swapInPlainHighlighting( $highlights, $name ) {
		if ( !isset( $highlights[ $name ] ) && isset( $highlights[ "$name.plain" ] ) ) {
			$highlights[ $name ] = $highlights[ "$name.plain" ];
		}
		return $highlights;
	}

	/**
	 * Escape highlighted text coming back from Elasticsearch.
	 * @param $snippet string highlighted snippet returned from elasticsearch
	 * @return string $snippet with html escaped _except_ highlighting pre and post tags
	 */
	private function escapeHighlightedText( $snippet ) {
		static $highlightPreEscaped = null, $highlightPostEscaped = null;
		if ( $highlightPreEscaped === null ) {
			$highlightPreEscaped = htmlspecialchars( Searcher::HIGHLIGHT_PRE );
			$highlightPostEscaped = htmlspecialchars( Searcher::HIGHLIGHT_POST );
		}
		return str_replace( array( $highlightPreEscaped, $highlightPostEscaped ),
			array( Searcher::HIGHLIGHT_PRE, Searcher::HIGHLIGHT_POST ),
			htmlspecialchars( $snippet ) );
	}

	/**
	 * Checks if a snippet contains matches by looking for HIGHLIGHT_PRE.
	 * @param string $snippet highlighted snippet returned from elasticsearch
	 * @return boolean true if $snippet contains matches, false otherwise
	 */
	private function containsMatches( $snippet ) {
		return strpos( $snippet, Searcher::HIGHLIGHT_PRE ) !== false;
	}

	/**
	 * Build the redirect title from the highlighted redirect snippet.
	 * @param string highlighted redirect snippet
	 * @param array $redirects Array of redirects stored as arrays with 'title' and 'namespace' keys
	 * @return Title object representing the redirect
	 */
	private function findRedirectTitle( $snippet, $redirects ) {
		$title = $this->stripHighlighting( $snippet );
		// Grab the redirect that matches the highlighted title with the lowest namespace.
		// That is pretty arbitrary but it prioritizes 0 over others.
		$best = null;
		if ( $redirects !== null ) {
			foreach ( $redirects as $redirect ) {
				if ( $redirect[ 'title' ] === $title && ( $best === null || $best[ 'namespace' ] > $redirect ) ) {
					$best = $redirect;
				}
			}
		}
		if ( $best === null ) {
			wfLogWarning( "Search backend highlighted a redirect ($title) but didn't return it." );
			return null;
		}
		return Title::makeTitleSafe( $best[ 'namespace' ], $best[ 'title' ] );
	}

	private function findSectionTitle() {
		$heading = $this->stripHighlighting( $this->sectionSnippet );
		return Title::makeTitle(
			$this->getTitle()->getNamespace(),
			$this->getTitle()->getDBkey(),
			Title::escapeFragmentForURL( $heading )
		);
	}

	private function stripHighlighting( $highlighted ) {
		$markers = array( Searcher::HIGHLIGHT_PRE, Searcher::HIGHLIGHT_POST );
		return str_replace( $markers, '', $highlighted );
	}

	/**
	 * Set interwiki and interwikiNamespace properties
	 * @param \Elastica\Result $result containing the given search result
	 * @param string $interwiki Interwiki prefix, if any
	 */
	private function setInterwiki( $result, $interwiki ) {
		$resultIndex = $result->getIndex();
		$indexBase = InterwikiSearcher::getIndexForInterwiki( $interwiki );
		$pos = strpos( $resultIndex, $indexBase );
		if ( $pos === 0 && $resultIndex[strlen( $indexBase )] == '_' ) {
			$this->interwiki = $interwiki;
			$this->interwikiNamespace = $result->namespace_text ? $result->namespace_text : '';
		}
	}

	public function getTitleSnippet($terms) {
		return $this->titleSnippet;
	}

	public function getRedirectTitle() {
		return $this->redirectTitle;
	}

	public function getRedirectSnippet($terms) {
		return $this->redirectSnipppet;
	}

	public function getTextSnippet( $terms ) {
		return $this->textSnippet;
	}

	public function getSectionSnippet() {
		return $this->sectionSnippet;
	}

	public function getSectionTitle() {
		return $this->sectionTitle;
	}

	// George 2015-04-14
	// Used to display the relevance score in search results
	public function getScore() {
		return $this->score;
	}

	public function getWordCount() {
		return $this->wordCount;
	}

	public function getByteSize() {
		return $this->byteSize;
	}

	public function getTimestamp() {
		return $this->timestamp->getTimestamp( TS_MW );
	}

	public function isFileMatch() {
		return $this->isFileMatch;
	}

	public function getInterwikiPrefix() {
		return $this->interwiki;
	}

	public function getInterwikiNamespaceText() {
		return $this->interwikiNamespace;
	}

	// George 2015-04-14
	public function getTitusData() {
		return $this->titusData;
	}

	// George 2015-05-12
	public function getTemplates() {
		return $this->template;
	}

	public function getTitle() {
		return $this->mTitle;
	}
}
