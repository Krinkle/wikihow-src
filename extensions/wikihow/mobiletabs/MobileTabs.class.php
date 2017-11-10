<?php

class MobileTabs {
	const CONFIG_LIST = ["mobile_tag_1", "mobile_tag_2"];
	const CONFIG_INFO = [
		"mobile_tag_1" => [
			0 => [
				"classes" => "default",
				"label" => "mt_steps_1",
				"count" => ".steps_list_2 > li"
			],
			1 => [
				"classes" => "#ur_mobile, #ur_h2, #social_proof_mobile, #sp_h2, .articleinfo",
				"label" => "mt_about",
				"count" => ".ur_review"
			]
		],
		"mobile_tag_2" => [
			0 => [
				"classes" => "default",
				"label" => "mt_steps_1",
				"count" => ".steps_list_2 > li"
			],
			1 => [
				"classes" => ".10secondsummary, .inahurry",
				"label" => "mt_summary"
			],
			2 => [
				"classes" => ".qa",
				"label" => "mt_qa",
				"count" => "#qa_article_questions > li"
			]
		]
	];

	public static function modifyDOM() {
		global $wgTitle, $wgOut;

		if(!self::isMobileTabArticle($wgTitle, $wgOut)) {
			return;
		}

		$tabInfo = self::getTabInfo($wgTitle);

		pq(".section:not('#intro'), #ur_mobile, #ur_h2, #social_proof_mobile, #sp_h2, .articleinfo")->addClass("tab_default tabbed_content");

		$tabHtml = "";
		$defaultIndex = -1;
		$minClasses = "mobile_tab tabs".count($tabInfo);
		foreach($tabInfo as $index => $info) {
			$class = $minClasses;
			if($info['classes'] != "default") {
				pq($info['classes'])->removeClass("tab_default")->addClass("tab_{$index} tabbed_content");
			} else {
				$class .= " mobile_tab_default";
				$defaultIndex = $index;
			}
			if($index == 0) {
				$class .= " first active";
			} else {
				$class .= " inactive";
			}

			$countString = "";
			if(array_key_exists("count", $info)) {
				$count = pq($info['count'])->length;
				$countString = wfMessage("mt_count", $count)->text();
			}

			$tabHtml .= "<a href='#' id='mobile_tab_{$index}' class='{$class}'>" . wfMessage($info['label'], $countString)->text() . "</a>";
		}
		if($defaultIndex != -1) {
			pq(".tab_default")->addClass("tab_{$defaultIndex}");
		}

		$tabHtml = "<div id='mobile_tab_container'>{$tabHtml}</div>";
		pq("#intro")->after($tabHtml);
		pq(".mobile_tab:last")->addClass("last");
	}

	public static function isMobileTabArticle($title, $out) {
		if(!Misc::isMobileMode() || GoogleAmp::isAmpMode($out)) {
			return false;
		}

		$listName = self::getMobileListName($title);
		return $listName !== false;
	}

	public static function getTabInfo($title) {
		$listName = self::getMobileListName($title);
		return self::CONFIG_INFO[$listName];
	}

	public static function getMobileListName($title) {
		if(!$title) {
			return false;
		}
		$articleId = $title->getArticleId();
		if($articleId <= 0) {
			return false;
		}
		foreach(self::CONFIG_LIST as $listName) {
			if(ArticleTagList::hasTag($listName, $articleId)) {
				return $listName;
			}
		}
		return false;
	}

	public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin ) {
		if ($skin->getTitle()->inNamespace(NS_MAIN)) {
			if (Misc::isMobileMode()) {
				$out->addModules('ext.wikihow.mobiletabs');
			}
		}

		return true;
	}
}