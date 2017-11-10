<?php

class DesktopWikihowCategoryPage extends CategoryPage {
	
	const STARTING_CHUNKS = 20;
	// const PULL_CHUNKS = 5;
	const PULL_CHUNKS = 25;
	const SINGLE_WIDTH = 163; // (article_shell width - 2*article_inner padding - 3*SINGLE_SPACING)/4
	const SINGLE_HEIGHT = 119; //should be .73*SINGLE_WIDTH
	const SINGLE_SPACING = 16;
	const CAT_CHUNK_SIZE = 4;

	var $catStream;

	public function view() {
		global $wgHooks, $wgSquidMaxage;
		
		$ctx = $this->getContext();
		$req = $ctx->getRequest();
		$out = $ctx->getOutput();
		$title = $ctx->getTitle();

		if (!$title->exists()) {
			parent::view();
			return;
		}
		
		if (count($req->getVal('diff')) > 0) {
			return Article::view();
		}
		
		$restAction = $req->getVal('restaction');
		if ($restAction == 'pull-chunk') {
			$out->setArticleBodyOnly(true);
			$out->setSquidMaxage($wgSquidMaxage);
			$start = $req->getInt('start');
			if (!$start) return;
			$categoryViewer = new WikihowCategoryViewer($title, $this->getContext());
			$this->catStream = new WikihowArticleStream($categoryViewer, $this->getContext(), $start);
			$html = $this->catStream->getChunks(self::PULL_CHUNKS, DesktopWikihowCategoryPage::SINGLE_WIDTH, DesktopWikihowCategoryPage::SINGLE_SPACING, DesktopWikihowCategoryPage::SINGLE_HEIGHT);
			$ret = json_encode( array('html' => $html) );
			$out->addHTML($ret);
		} else {
			$out->setRobotPolicy('index,follow', 'Category Page');
			$out->setPageTitle($title->getText());
			if ($req->getVal('viewMode',0)) {
				$from = $req->getVal( 'from' );
				$until = $req->getVal( 'until' );
				$viewer = new WikihowCategoryViewer( $this->mTitle, $this->getContext(), $from, $until );
				$viewer->clearState();
				$viewer->doQuery();
				$viewer->finaliseCategoryState();
				$out->addHtml('<div class="section minor_section">');
				$out->addHtml('<ul><li>');
				if (is_array($viewer->articles_fa)) {
					$articles = array_merge($viewer->articles_fa, $viewer->articles);
				} else {
					$articles = $viewer->articles;
				}
				$out->addHtml( implode("</li>\n<li>", $articles) );
				$out->addHtml('</li></ul>');
				$out->addHtml('</div>');
			}
			else {
				//don't have a ?pg=1 page
				if ($req->getInt('pg') == 1) $out->redirect($title->getFullURL());
				
				//get pg and start info
				$pg = $req->getInt('pg',1);
				$start = ($pg > 0) ? (($pg - 1) * self::PULL_CHUNKS * self::CAT_CHUNK_SIZE) : 0;
				//$out->addHtml('<script>gScrollContextPage = ' . $pg . ';</script>');
				
				$viewer = new WikihowCategoryViewer($title, $this->getContext());
				$this->catStream = new WikihowArticleStream($viewer, $this->getContext(), $start);
				// $html = $this->catStream->getChunks(self::STARTING_CHUNKS, WikihowCategoryPage::SINGLE_WIDTH, WikihowCategoryPage::SINGLE_SPACING, WikihowCategoryPage::SINGLE_HEIGHT);
				$html = $this->catStream->getChunks(self::PULL_CHUNKS, DesktopWikihowCategoryPage::SINGLE_WIDTH, DesktopWikihowCategoryPage::SINGLE_SPACING, DesktopWikihowCategoryPage::SINGLE_HEIGHT);
				
				if (!$html) {
					//nothin'
					$out->setStatusCode(404);
					$out->setRobotpolicy('noindex,nofollow');
					return;
				} else {
					$total = count($this->catStream->articles);
					$out->addModules('ext.wikihow.desktop_category_page');
					$out->addHTML($html);
					$out->addHtml('<noscript>'.$this->getPaginationHTML($pg,$total).'</noscript>');
				}
			}


			$sk = $this->getContext()->getSkin();
			$subCats = $viewer->shortListRD( $viewer->children, $viewer->children_start_char );
			if ($subCats != "") {
				$subCats  = "<h3>{$this->mTitle->getText()}</h3>{$subCats}";
				$sk->addWidget($subCats);
			}

			$furtherEditing = $viewer->getArticlesFurtherEditing($viewer->articles, $viewer->article_info);
			if ($furtherEditing != "") {
				$sk->addWidget($furtherEditing);
			}
		}
	}

	public function isFileCacheable() {
		return true;
	}

	public static function onArticleFromTitle(&$title, &$page) {
		switch ($title->getNamespace()) {
			case NS_CATEGORY:
				$page = new DesktopWikihowCategoryPage($title);
		}
		return true;
	}
	
	private function getPaginationHTML($pg, $total) {
		global $wgCanonicalServer, $wgCategoryPagingLimit;

		$ctx = $this->getContext();
		$out = $ctx->getOutput();
		$title = $ctx->getTitle();

		// Dalek: "CAL-CU-LATE!!!"
		$here = str_replace(' ','-','/'.$this->getContext()->getTitle()->getPrefixedText());
		$perPage = (self::PULL_CHUNKS * self::CAT_CHUNK_SIZE);
		$numOfPages = ($perPage > 0) ? ceil($total / $perPage) : 0;

		// prev & next links
		if ($pg > 1) {
			$prev_page = ($pg == 2) ? '' : '?pg='.($pg-1);
			$prev = '<a rel="prev" href="'.$here.$prev_page.'" class="button buttonleft primary pag_prev">'.wfMessage('lsearch_previous')->text().'</a>';
		}
		else {
			$prev = '<a class="button buttonleft primary pag_prev disabled">'.wfMessage('lsearch_previous')->text().'</a>';
		}
		if ($pg < $numOfPages) {
			$next = '<a rel="next" href="'.$here.'?pg='.($pg+1).'" class="button buttonright pag_next primary">'.wfMessage('lsearch_next')->text().'</a>';
		}
		else {
			$next = '<a class="button buttonright pag_next primary disabled">'.wfMessage('lsearch_next')->text().'</a>';
		}
		$html = $prev.$next;

		// set <head> links for SEO
		if ($pg > 1) $out->setCanonicalUrl($title->getFullURL().'?pg='.$pg);
		if ($pg == 2) {
			$out->addHeadItem('prev_pagination','<link rel="prev" href="'.$wgCanonicalServer.$here.'" />');
		}
		else if ($pg > 2) {
			$out->addHeadItem('prev_pagination','<link rel="prev" href="'.$wgCanonicalServer.$here.'?pg='.($pg-1).'" />');
		}
		if ($pg < $numOfPages) $out->addHeadItem('next_pagination','<link rel="next" href="'.$wgCanonicalServer.$here.'?pg='.($pg+1).'" />');
		
		$html .= '<ul class="pagination">';
		for ($i=1; $i<=$numOfPages; $i++) {
			if ($i == ($pg-1)) {
				$rel = 'rel="prev"';
			}
			elseif ($i == ($pg+1)) {
				$rel = 'rel="next"';
			}
			else {
				$rel = '';
			}
			
			if ($pg == $i) {
				$html .= '<li>'.$i.'</li>';
			}
			else if ($i == 1) {
				$html .= '<li><a '.$rel.' href="'.$here.'">'.$i.'</a></li>';
			}
			else {
				$html .= '<li><a '.$rel.' href="'.$here.'?pg='.$i.'">'.$i.'</a></li>';
			}
		}
		$html .= '</ul>';
		return $html;
	}

}
