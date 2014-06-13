var toolURL = "/Special:Spellchecker";
var articleId = 0;
var wordArray;
var wordArrayIdx = 0;
var currentWord = "";
var currentWordIdx = 0;
var exclusionArray;
var quickEditUrl;
var misspell = "misspell";
var SC_STANDINGS_TABLE_REFRESH = 600;
var retries = 0;
var MAX_RETRIES = 3;


$("document").ready(function() {
	// Test for old IEs
	validIE = true;
	isIE = false;
	var clientPC = navigator.userAgent.toLowerCase(); // Get client info
	if (/msie (\d+\.\d+);/.test(clientPC)) { //test for MSIE x.x;
		 validIE  = 8 <= (new Number(RegExp.$1)); // capture x.x portion and store as a number
		 isIE = true;
	}
	if (!validIE) {
		$("#spch-snippet").html('Error: You have an outdated browser. Please upgrade to the latest version.');
		disableTopButtons();
		$('.spch-waiting').hide();
		return;
	}
	// Change auto summary
	gAutoSummaryText = mw.message('spch-qe-summary').text();

	initToolTitle();

	if (mw.config.get('wgUserId') > 0)  {
		$('#spch-qe').css('visibility', 'visible');
	}
	
	$("#bodycontents .article_inner").removeClass("article_inner");
	articleName = extractParamFromUri(window.location.href, "a");
	
	getNextSpellchecker(articleName);
	
	$(document).on('click', '#spch-skip', function (e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {

			var sentence = loadNextWord();
			if (sentence.length == 0) {
				$("#spch-preview").hide();
				$(".spch-waiting").show();
				submitArticle();
			}
		}
	});

	$(document).on('click', '#spch-no', function (e) {
		e.preventDefault();

		if (!jQuery(this).hasClass('clickfail')) {
			wordArray[currentWordIdx]['correction'] = currentWord;

			var sentence = loadNextWord();
			if (sentence.length == 0) {
				$("#spch-preview").hide();
				$(".spch-waiting").show();
				submitArticle();
			}
		}
	});

	function enterEditMode() {
		toggleTopButtons();
		toggleEditButtons();
		$('.misspell').addClass('editable').prop('contenteditable', 'true');
		placeCaretAtEnd($('.misspell').get(0));
	}

	$(document).on('click', '#spch-yes', function(e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {
			enterEditMode();
		}
	});

	$(document).on('dblclick', '#spch-snippet', function(e) {
		e.preventDefault();
		if (!$('#spch-yes').hasClass('clickfail')) {
			enterEditMode();
		}
	});

	$(document).on('click', '#spch-cancel', function(e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {
			$('.misspell').prop('contenteditable', 'false').removeClass('editable').text(currentWord);
			toggleEditButtons();
			toggleTopButtons();
		}
	});

	$(document).on('click', '#spch-next', function(e) {
		e.preventDefault();
		if (!jQuery(this).hasClass('clickfail')) {
			if ($('.misspell').text() != currentWord) {
				wordArray[currentWordIdx]['correction'] = $('.misspell').text();
			}
			toggleEditButtons();
			toggleTopButtons();

			var sentence = loadNextWord();
			if (sentence.length == 0) {
				$("#spch-preview").hide();
				$(".spch-waiting").show();
				submitArticle();
			}
		}
	});

	$(document).on('click', '#spch-qe', function (e) {
		e.preventDefault();
		if (!$(this).hasClass('clickfail')) {
			initPopupEdit(quickEditUrl);
		}
	});
});

function initToolTitle() {
	$(".firstHeading").before("<h5>" + $(".firstHeading").html() + "</h5>")
}

// asks the backend for a new article
//to edit and loads it in the page
function getNextSpellchecker(articleName) {
	if (retries < MAX_RETRIES) {
		retries++;
		$.get(toolURL,
			{getNext: true,
			 a: articleName
			},
			function (result) {
				loadResult(result);
			},
			'json'
		);
	} else {
		$('#spch-snippet').html(mw.message('spch-error-noarticles').text());
		disableTopButtons();
	}
}

/**
 * Loads the next article into the page
 * 
 **/
function loadResult(result) {
	debugResult(result);

	if (result['error'] != undefined) {
		$('#spch-snippet').html(result['error']);
		$('.spch-waiting').hide();
		disableTopButtons();
	}
	else {
		quickEditUrl = result['qeurl'];
		wordArray = result['words'];
		wordArrayIdx = 0;
		exclusionArray = result['exclusions'];
		articleId = result['articleId'];
		$("#spch-id").html(articleId);
		var articleText = result['html'];
		$("#spch-preview").html(result['html']);

		// If we can't find a sentence, just skip the article
		var sentence = loadNextWord();
		if (sentence.length == 0) {
			$("#spch-preview").hide();
			$(".spch-waiting").show();
			getNextSpellchecker();
			return;
		}

		$("h1.firstHeading").html(result['title']);
		// Used to trigger wikivideo init
		$(document).trigger('rcdataloaded');
		$('.spch-waiting').hide();
		$("#spch-preview").show();

		enableTopButtons();
		retries = 0;
	}
}


function submitArticle() {
	$(".spch-waiting").show();
	$("#spch-snippet").text(mw.message('spch-loading-next').text());
	disableTopButtons();
	var id = $('#spch-id').html();
	$.post(toolURL,
		{submit: 1, articleId: id, words: wordArray},
		function(result) {
			updateStats();
			loadResult(result);
			enableTopButtons();
			$(".spch-waiting").hide();
		},
		'json'
	);
}

function loadNextWord() {
	var sentence = "";
	var word = "";
	var misspelledWord = "";
	var key = "";
	if (wordArrayIdx < wordArray.length) {

		do {
			word = wordArray[wordArrayIdx];
			misspelledWord = word['misspelled'];
			key = word['key'];
			//console.log(key);
			sentence = findSentenceContaining(key);
			wordArrayIdx++;
		} while (wordArrayIdx < wordArray.length && sentence.length == 0);

		currentWord = misspelledWord;
		currentWordIdx = wordArrayIdx - 1;
		sentence = wrapMisspelledWord(sentence, key, misspelledWord);

		$('#spch-snippet').html(sentence);
	}
	return sentence;
}

function debugResult(result) {
	// adds debugging log data to the debug console if exists
	if (WH.consoleDebug) {
		WH.consoleDebug(result['debug']);
	}
}

function findSentenceContaining(key) {
	var sentence = '';
	var selectors = [
		'#intro p:contains(' + key + ')',
		'.section:not(.sourcesandcitations):not(.relatedwikihows):not(.video) div.section_text li:contains(' + key + ')',
		'.section:not(.sourcesandcitations):not(.relatedwikihows):not(.video) div.section_text p:contains(' + key + ')'
	];

	for (var i = 0; i < selectors.length; i++) {
		var elem = $(selectors[i]).clone();
		if ($(elem).length) {
			$(elem).find('a').remove();
			$(elem).find('ul').remove();
			$(elem).find('.step_num').remove();
			$(elem).find('.whvid_cont').remove();
			sentence =  $(elem).text();
			break;
		}
	}

	return sentence;
}

function wrapMisspelledWord(sentence, key, word) {
	replacementKey = key.replace(word, '<div class="misspell inline">' + word + '</div>');
	return sentence.replace(key, replacementKey);
}

//function saveArticle() {
//	$(".spch-waiting").show();
//	$("#spch-edit").hide();
//	$(window).scrollTop(0);
//	checkLineBreaks();
//	$.post(toolURL, {
//		submitEditForm: true,
//		articleId: articleId,
//		wpTextbox1: jQuery("#spch-content").html(),
//		wpSummary: jQuery("#wpSummary").val(),
//		isIE: isIE
//		},
//		function (result) {
//			updateStats();
//			loadResult(result);
//		},
//		'json'
//	);
//	window.oTrackUserAction();
//}

function placeCaretAtEnd(el) {
	el.focus();
	if (typeof window.getSelection != "undefined"
		&& typeof document.createRange != "undefined") {
		var range = document.createRange();
		range.selectNodeContents(el);
		range.collapse(false);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	} else if (typeof document.body.createTextRange != "undefined") {
		var textRange = document.body.createTextRange();
		textRange.moveToElementText(el);
		textRange.collapse(false);
		textRange.select();
	}
}

function disableTopButtons() {
	//disable edit/skip choices
	$('#spch-yes').addClass('clickfail');	
	$('#spch-skip').addClass('clickfail');
	$('#spch-no').addClass('clickfail');
	$('#spch-qe').addClass('clickfail');
}

function enableTopButtons() {
	//disable edit/skip choices
	$('#spch-yes').removeClass('clickfail');	
	$('#spch-skip').removeClass('clickfail');
	$('#spch-no').removeClass('clickfail');
	$('#spch-qe').removeClass('clickfail');
}

function toggleTopButtons() {
	//disable edit/skip choices
	$('#spch-options').toggle();
}

function toggleEditButtons() {
	//disable edit/skip choices
	$('#spch-edit-buttons').toggle();
}

function updateStats(){
	var statboxes = '#iia_stats_today_spellchecked,#iia_stats_week_spellchecked,#iia_stats_all_spellchecked,#iia_stats_group';
	$(statboxes).each(function(index, elem) {
			$(this).fadeOut(function () {
				var cur = parseInt($(this).html());
				$(this).html(cur + 1);
				$(this).fadeIn();
			});
		}
	);
}

updateStandingsTable = function() {
    var url = '/Special:Standings/SpellcheckerStandingsGroup';
    jQuery.get(url, function (data) {
        jQuery('#iia_standings_table').html(data['html']);
    },
	'json'
	);
	$("#stup").html(SC_STANDINGS_TABLE_REFRESH / 60);
	//reset timer
	window.setTimeout(updateStandingsTable, 1000 * SC_STANDINGS_TABLE_REFRESH);
}

window.setTimeout(updateWidgetTimer, 60*1000);
window.setTimeout(updateStandingsTable, 1000 * SC_STANDINGS_TABLE_REFRESH);

function updateWidgetTimer() {
    updateTimer('stup');
    window.setTimeout(updateWidgetTimer, 60*1000);
}